<?php

namespace App\Http\Controllers\Api\V1\Commercial;

use App\Exceptions\CommercialRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\DeclarePaymentRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Commercial\EntitlementService;
use App\Services\Commercial\PaymentService;
use App\Services\Commercial\QuotaEnforcer;
use App\Services\Commercial\SettingsService;
use App\Services\Commercial\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentService $payments,
        private readonly EntitlementService $entitlements,
        private readonly QuotaEnforcer $quota,
        private readonly SettingsService $settings,
    ) {}

    /** Demande d'une formule : en attente, avec référence de paiement et instructions de règlement hors ligne. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['plan' => ['required', 'string', 'max:40']]);
        $plan = Plan::where('code', $data['plan'])->first();
        if ($plan === null) {
            throw new CommercialRuleException('plan_unavailable', 'Cette formule n\'est pas proposée pour le moment.');
        }
        $subscription = $this->subscriptions->request($request->user(), $plan);

        return response()->json(['data' => (new SubscriptionResource($subscription))->toArray($request), 'payment_instructions' => $this->instructions()], 201);
    }

    /** Ma souscription (celle de mon agence si je suis gérant) : active, en attente, historique, droits et consommation. */
    public function show(Request $request): JsonResponse
    {
        $subscriber = $this->subscriptions->subscriberFor($request->user());
        [$type, $id] = $this->entitlements->ref($subscriber);
        $all = Subscription::where('subscriber_type', $type)->where('subscriber_id', $id)->orderByDesc('id')->get();
        $pending = $all->firstWhere('status.value', 'pending');
        $rights = $this->entitlements->for($subscriber);
        $deadline = $all->whereNotNull('downgrade_deadline_at')->first()?->downgrade_deadline_at;

        return response()->json(['data' => [
            'entitlements' => $rights,
            'usage' => ['active_listings' => $this->quota->activeCount($subscriber), 'limit' => (int) $rights['limits']['active_listings']],
            'current' => $all->first(fn ($s) => $s->isCurrentlyActive()) ? (new SubscriptionResource($all->first(fn ($s) => $s->isCurrentlyActive())))->toArray($request) : null,
            'pending' => $pending ? (new SubscriptionResource($pending))->toArray($request) : null,
            'history' => $all->map(fn ($s) => (new SubscriptionResource($s))->toArray($request))->values(),
            'downgrade_deadline_at' => $deadline,
        ], 'payment_instructions' => $pending ? $this->instructions() : null]);
    }

    public function entitlements(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->entitlements->for($this->subscriptions->subscriberFor($request->user()))]);
    }

    public function cancel(Request $request, string $subscription): JsonResponse
    {
        $target = $this->owned($request->user(), $subscription);
        $this->subscriptions->cancelPending($target, $request->user(), 'Annulée par le demandeur');

        return response()->json(null, 204);
    }

    /** L'annonceur DÉCLARE un règlement : n'ouvre aucun droit tant qu'un administrateur ne l'a pas confirmé. */
    public function declare(DeclarePaymentRequest $request, string $subscription): JsonResponse
    {
        $target = $this->owned($request->user(), $subscription);
        $payment = $this->payments->declare($target, $request->user(), $request->validated(), $request->file('proof'));

        return response()->json(['data' => ['id' => $payment->ulid, 'status' => $payment->status->value, 'settlement' => $this->payments->settlementStatus($target)]], 201);
    }

    /**
     * Après une expiration ou une baisse de formule : l'annonceur choisit les annonces à garder, les autres passent en pause.
     */
    public function downgrade(Request $request): JsonResponse
    {
        $data = $request->validate(['keep' => ['present', 'array', 'max:1000'], 'keep.*' => ['string', 'max:40']]);
        $subscriber = $this->subscriptions->subscriberFor($request->user());
        $limit = $this->entitlements->limit($subscriber, 'active_listings');
        $mine = $this->quota->published($subscriber)->pluck('ulid')->all();
        $keep = array_values(array_intersect($data['keep'], $mine));

        if (count($keep) > $limit) {
            throw new CommercialRuleException('too_many_kept', "Votre formule permet de garder {$limit} annonce(s) active(s).");
        }
        $paused = $this->quota->keepOnly($subscriber, $keep, $request->user());

        return response()->json(['data' => ['kept' => count($keep), 'paused' => $paused]]);
    }

    /** Historique complet (COM-07) : demandes, activations, changements, suspensions, avec auteur et date. */
    public function history(Request $request): JsonResponse
    {
        $subscriber = $this->subscriptions->subscriberFor($request->user());
        [$type, $id] = $this->entitlements->ref($subscriber);
        $ulids = Subscription::where('subscriber_type', $type)->where('subscriber_id', $id)->pluck('ulid');

        $rows = AuditLog::where('subject_type', 'Subscription')->whereIn('subject_id', $ulids)->orderBy('id')->get()
            ->map(fn ($l) => ['action' => $l->action, 'subscription' => $l->subject_id, 'by' => $l->actor_id ? User::find($l->actor_id)?->name : 'Système', 'at' => $l->created_at, 'detail' => $l->after]);

        return response()->json(['data' => $rows]);
    }

    private function owned(User $user, string $ulid): Subscription
    {
        $subscriber = $this->subscriptions->subscriberFor($user);
        [$type, $id] = $this->entitlements->ref($subscriber);

        return Subscription::where('ulid', $ulid)->where('subscriber_type', $type)->where('subscriber_id', $id)->firstOrFail();
    }

    /** @return array<string, mixed>|null */
    private function instructions(): ?array
    {
        return $this->settings->get('payment_instructions');
    }
}
