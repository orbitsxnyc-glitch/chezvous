<?php

namespace App\Services\Commercial;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CommercialRuleException;
use App\Models\OfflinePayment;
use App\Models\PremiumListing;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Règlements HORS LIGNE (Wave, Orange Money, virement, espèces). Aucun paiement en ligne.
 * - non réglé : aucun enregistrement actif ;
 * - déclaré : l'annonceur indique avoir payé (n'ouvre AUCUN droit) ;
 * - confirmé : seul un administrateur confirme (ou enregistre directement un encaissement confirmé).
 */
class PaymentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * L'annonceur déclare un règlement : statut « déclaré », jamais « confirmé ».
     *
     * @param  array<string, mixed>  $data
     */
    public function declare(Subscription|PremiumListing $payable, User $actor, array $data, ?UploadedFile $proof = null): OfflinePayment
    {
        if (! $this->isAwaitingPayment($payable)) {
            throw new CommercialRuleException('not_awaiting_payment', 'Cette demande n\'attend plus de règlement.');
        }

        return $this->create($payable, $actor, $data, $proof, PaymentStatus::Declared);
    }

    /**
     * L'administrateur enregistre un encaissement déjà reçu : directement « confirmé ».
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Subscription|PremiumListing $payable, User $admin, array $data, ?UploadedFile $proof = null): OfflinePayment
    {
        $payment = $this->create($payable, $admin, $data, $proof, PaymentStatus::Confirmed);
        $payment->forceFill(['reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();

        return $payment;
    }

    public function confirm(OfflinePayment $payment, User $admin): OfflinePayment
    {
        $this->assertDeclared($payment);
        $payment->forceFill(['status' => PaymentStatus::Confirmed, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
        $this->audit->record('payment.confirmed', $payment, ['status' => 'declared'], ['status' => 'confirmed', 'amount_xof' => $payment->amount_xof], $admin->id);

        return $payment;
    }

    public function reject(OfflinePayment $payment, User $admin, string $note): OfflinePayment
    {
        $this->assertDeclared($payment);
        $payment->forceFill(['status' => PaymentStatus::Rejected, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'note' => $note])->save();
        $this->audit->record('payment.rejected', $payment, ['status' => 'declared'], ['status' => 'rejected'], $admin->id);

        return $payment;
    }

    /** non réglé | déclaré | confirmé */
    public function settlementStatus(Subscription|PremiumListing $payable): string
    {
        $statuses = $this->payments($payable)->pluck('status')->map(fn ($s) => $s instanceof PaymentStatus ? $s->value : $s);

        return match (true) {
            $statuses->contains('confirmed') => 'confirmed',
            $statuses->contains('declared') => 'declared',
            default => 'unpaid',
        };
    }

    /** Montant confirmé cumulé. */
    public function confirmedAmount(Subscription|PremiumListing $payable): int
    {
        return (int) $this->payments($payable)->filter(fn ($p) => $p->status === PaymentStatus::Confirmed)->sum('amount_xof');
    }

    /** @return Collection<int, OfflinePayment> */
    public function payments(Subscription|PremiumListing $payable)
    {
        return OfflinePayment::where('payable_type', $this->type($payable))->where('payable_id', $payable->id)->orderBy('id')->get();
    }

    public function proofUrl(OfflinePayment $payment): ?string
    {
        return $payment->proof_path === null ? null
            : URL::temporarySignedRoute('payment-proofs.download', now()->addMinutes(config('chezvous.commercial.proof_link_minutes')), ['payment' => $payment->ulid]);
    }

    public function type(Subscription|PremiumListing $payable): string
    {
        return $payable instanceof Subscription ? 'subscription' : 'premium_listing';
    }

    private function isAwaitingPayment(Subscription|PremiumListing $payable): bool
    {
        return $payable->status->value === 'pending';
    }

    /** @param array<string, mixed> $data */
    private function create(Subscription|PremiumListing $payable, User $by, array $data, ?UploadedFile $proof, PaymentStatus $status): OfflinePayment
    {
        $method = PaymentMethod::from($data['method']);
        $path = $proof !== null ? $this->storeProof($proof) : null;

        return DB::transaction(function () use ($payable, $by, $data, $method, $path, $status) {
            $payment = new OfflinePayment([
                'payable_type' => $this->type($payable), 'payable_id' => $payable->id, 'method' => $method,
                'amount_xof' => (int) $data['amount_xof'], 'external_reference' => $data['external_reference'] ?? null,
                'received_at' => $data['received_at'] ?? now(), 'proof_path' => $path, 'recorded_by' => $by->id, 'note' => $data['note'] ?? null,
            ]);
            $payment->forceFill(['status' => $status])->save();
            // Le montant et la référence sont journalisés ; jamais le justificatif.
            $this->audit->record($status === PaymentStatus::Declared ? 'payment.declared' : 'payment.recorded', $payment, null,
                ['status' => $status->value, 'amount_xof' => $payment->amount_xof, 'method' => $method->value, 'payable' => $this->type($payable)], $by->id);

            return $payment;
        });
    }

    private function storeProof(UploadedFile $file): string
    {
        $mime = $file->getMimeType(); // type réel détecté sur le contenu
        if (! in_array($mime, config('chezvous.commercial.proof_allowed_mimes'), true) || $file->getSize() > config('chezvous.commercial.proof_max_kb') * 1024) {
            throw new CommercialRuleException('invalid_proof', 'Justificatif refusé : PDF, JPEG ou PNG de 5 Mo maximum.');
        }
        $ext = match ($mime) {
            'application/pdf' => 'pdf', 'image/png' => 'png', default => 'jpg',
        };
        $path = 'payment-proofs/'.Str::random(40).".{$ext}";
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path)); // privé

        return $path;
    }

    private function assertDeclared(OfflinePayment $payment): void
    {
        if ($payment->status !== PaymentStatus::Declared) {
            throw new CommercialRuleException('invalid_transition', 'Ce règlement a déjà été traité.');
        }
    }
}
