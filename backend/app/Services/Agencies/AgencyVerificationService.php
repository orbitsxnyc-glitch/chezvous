<?php

namespace App\Services\Agencies;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\DocumentKind;
use App\Enums\VerificationRequestStatus;
use App\Exceptions\AgencyRuleException;
use App\Models\Agency;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\VerificationRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Vérification manuelle d'une agence sur justificatifs (NINEA + RCCM, H8).
 * Les pièces sont stockées sur le disque PRIVÉ ; l'accès se fait par URL signée de courte durée, réservée aux administrateurs.
 * « Contrôlé » ne vaut pas garantie juridique.
 */
class AgencyVerificationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, UploadedFile>  $files  indexés par type : ninea, rccm, other
     */
    public function submit(Agency $agency, User $by, array $files): VerificationRequest
    {
        foreach ([DocumentKind::Ninea, DocumentKind::Rccm] as $required) {
            if (! isset($files[$required->value])) {
                throw new AgencyRuleException('document_missing', 'Le NINEA et le RCCM sont obligatoires.');
            }
        }
        foreach ($files as $file) {
            $mime = $file->getMimeType(); // type réel détecté sur le contenu
            if (! in_array($mime, config('chezvous.agencies.verification_allowed_mimes'), true) || $file->getSize() > config('chezvous.agencies.verification_max_kb') * 1024) {
                throw new AgencyRuleException('invalid_document', 'Document refusé : PDF, JPEG ou PNG de 5 Mo maximum.');
            }
        }

        try {
            return DB::transaction(function () use ($agency, $by, $files) {
                $request = VerificationRequest::create(['agency_id' => $agency->id, 'submitted_by' => $by->id, 'status' => VerificationRequestStatus::Pending]);
                $disk = Storage::disk('local'); // privé : jamais d'URL publique
                foreach ($files as $kind => $file) {
                    $ext = match ($file->getMimeType()) {
                        'application/pdf' => 'pdf', 'image/png' => 'png', default => 'jpg',
                    };
                    $path = "agency-verification/{$agency->ulid}/".Str::random(32).".{$ext}";
                    $disk->putFileAs(dirname($path), $file, basename($path));
                    VerificationDocument::create([
                        'verification_request_id' => $request->id, 'kind' => $kind, 'path' => $path,
                        'mime' => (string) $file->getMimeType(), 'size_bytes' => (int) $file->getSize(),
                    ]);
                }
                $this->audit->record('agency.verification_submitted', $agency, null, ['request' => $request->ulid], $by->id);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            throw new AgencyRuleException('verification_pending', 'Une demande de vérification est déjà en cours d\'examen.');
        }
    }

    public function approve(VerificationRequest $request, User $admin): VerificationRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $admin) {
            $request->forceFill(['status' => VerificationRequestStatus::Approved, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
            $request->agency->forceFill(['verified_at' => now()])->save();
            DB::table('verification_checks')->insert([
                'subject_type' => 'agency', 'subject_id' => $request->agency_id, 'check_type' => CheckType::Professional->value,
                'status' => CheckStatus::Passed->value, 'performed_by' => $admin->id, 'performed_at' => now(),
                'note' => 'NINEA et RCCM examinés', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record('agency.verification_approved', $request->agency, null, ['request' => $request->ulid], $admin->id);

            return $request;
        });
    }

    public function reject(VerificationRequest $request, User $admin, string $note): VerificationRequest
    {
        $this->assertPending($request);
        $request->forceFill(['status' => VerificationRequestStatus::Rejected, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'note' => $note])->save();
        $this->audit->record('agency.verification_rejected', $request->agency, null, ['request' => $request->ulid], $admin->id);

        return $request;
    }

    /** URL signée et temporaire vers un justificatif (jamais de lien permanent). */
    public function documentUrl(VerificationDocument $document): string
    {
        return URL::temporarySignedRoute('verification-documents.download', now()->addMinutes(config('chezvous.agencies.document_link_minutes')), ['document' => $document->id]);
    }

    private function assertPending(VerificationRequest $request): void
    {
        if ($request->status !== VerificationRequestStatus::Pending) {
            throw new AgencyRuleException('invalid_transition', 'Cette demande a déjà été traitée.');
        }
    }
}
