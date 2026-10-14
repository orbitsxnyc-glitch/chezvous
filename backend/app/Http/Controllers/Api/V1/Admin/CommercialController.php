<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PlanAudience;
use App\Enums\PlanPeriod;
use App\Enums\PremiumStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CommercialRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\DeclarePaymentRequest;
use App\Http\Requests\Commercial\ReasonRequest;
use App\Http\Requests\Commercial\UpdatePlanRequest;
use App\Http\Resources\PremiumListingResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\OfflinePayment;
use App\Models\Plan;
use App\Models\PremiumListing;
use App\Models\PremiumProduct;
use App\Models\Subscription;
use App\Services\Commercial\PaymentService;
use App\Services\Commercial\PremiumService;
use App\Services\Commercial\SettingsService;
use App\Services\Commercial\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestion commerciale par les administrateurs (COM-04, ADM-04) : encaissements, activations, suspensions.
 * Le catalogue et les coordonnées de règlement sont réservés au super administrateur.
 */
class CommercialController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PremiumService $premium,
        private readonly PaymentService $payments,
        private readonly SettingsService $settings,
    ) {}

    // ---------------------------------------------------------------- souscriptions

    public function subscriptions(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $request->validate(['status' => ['sometimes', Rule::enum(SubscriptionStatus::class)]]);
        $page = Subscription::with('plan')->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->orderByDesc('id')->cursorPaginate(30)->withQueryString();

        return response()->json(['data' => collect($page->items())->map(fn ($s) => (new SubscriptionResource($s, true))->toArray($request)), 'meta' => ['next_cursor' => $page->nextCursor()?->encode()]]);
    }

    public function showSubscription(Request $request, Subscription $subscription): SubscriptionResource
    {
        Gate::authorize('moderate');

        return new SubscriptionResource($subscription, true);
    }

    public function recordSubscriptionPayment(DeclarePaymentRequest $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('moderate');
        $payment = $this->payments->record($subscription, $request->user(), $request->validated(), $request->file('proof'));

        return response()->json(['data' => ['id' => $payment->ulid, 'status' => $payment->status->value, 'settlement' => $this->payments->settlementStatus($subscription)]], 201);
    }

    public function activateSubscription(Request $request, Subscription $subscription): SubscriptionResource
    {
        Gate::authorize('moderate');

        return new SubscriptionResource($this->subscriptions->activate($subscription, $request->user()), true);
    }

    public function suspendSubscription(ReasonRequest $request, Subscription $subscription): SubscriptionResource
    {
        Gate::authorize('moderate');

        return new SubscriptionResource($this->subscriptions->suspend($subscription, $request->user(), $request->validated('reason')), true);
    }

    public function reinstateSubscription(Request $request, Subscription $subscription): SubscriptionResource
    {
        Gate::authorize('moderate');

        return new SubscriptionResource($this->subscriptions->reinstate($subscription, $request->user()), true);
    }

    public function cancelSubscription(ReasonRequest $request, Subscription $subscription): SubscriptionResource
    {
        Gate::authorize('moderate');

        return new SubscriptionResource($this->subscriptions->cancelPending($subscription, $request->user(), $request->validated('reason')), true);
    }

    // ---------------------------------------------------------------- mises en avant

    public function premiumIndex(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $request->validate(['status' => ['sometimes', Rule::enum(PremiumStatus::class)]]);
        $page = PremiumListing::with('listing')->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->orderByDesc('id')->cursorPaginate(30)->withQueryString();

        return response()->json(['data' => collect($page->items())->map(fn ($p) => (new PremiumListingResource($p, true))->toArray($request)), 'meta' => ['next_cursor' => $page->nextCursor()?->encode()]]);
    }

    public function recordPremiumPayment(DeclarePaymentRequest $request, PremiumListing $premium): JsonResponse
    {
        Gate::authorize('moderate');
        $payment = $this->payments->record($premium, $request->user(), $request->validated(), $request->file('proof'));

        return response()->json(['data' => ['id' => $payment->ulid, 'status' => $payment->status->value, 'settlement' => $this->payments->settlementStatus($premium)]], 201);
    }

    public function activatePremium(Request $request, PremiumListing $premium): PremiumListingResource
    {
        Gate::authorize('moderate');

        return new PremiumListingResource($this->premium->activate($premium, $request->user())->load('listing'), true);
    }

    public function suspendPremium(ReasonRequest $request, PremiumListing $premium): PremiumListingResource
    {
        Gate::authorize('moderate');

        return new PremiumListingResource($this->premium->suspend($premium, $request->user(), $request->validated('reason'))->load('listing'), true);
    }

    public function cancelPremium(ReasonRequest $request, PremiumListing $premium): PremiumListingResource
    {
        Gate::authorize('moderate');

        return new PremiumListingResource($this->premium->cancelPending($premium, $request->user(), $request->validated('reason'))->load('listing'), true);
    }

    // ---------------------------------------------------------------- règlements

    public function confirmPayment(Request $request, OfflinePayment $payment): JsonResponse
    {
        Gate::authorize('moderate');
        $result = $this->payments->confirm($payment, $request->user());

        return response()->json(['data' => ['id' => $result->ulid, 'status' => $result->status->value]]);
    }

    public function rejectPayment(Request $request, OfflinePayment $payment): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:255']]);
        $result = $this->payments->reject($payment, $request->user(), $data['note']);

        return response()->json(['data' => ['id' => $result->ulid, 'status' => $result->status->value]]);
    }

    /** Justificatif de règlement : lien signé temporaire ET administrateur connecté ; jamais mis en cache. */
    public function downloadProof(OfflinePayment $payment): StreamedResponse
    {
        Gate::authorize('moderate');
        abort_if($payment->proof_path === null, 404);

        return Storage::disk('local')->download($payment->proof_path, 'justificatif.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ---------------------------------------------------------------- catalogue (super administrateur)

    public function plans(): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => Plan::orderBy('sort')->get()->map(fn (Plan $p) => $this->planArray($p))]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        Gate::authorize('manage-catalog');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'unique:plans,code'],
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'audience' => ['required', Rule::enum(PlanAudience::class)],
            'period' => ['required', Rule::enum(PlanPeriod::class)],
        ]);
        $plan = Plan::create($data + ['price_xof' => 0, 'is_active' => false, 'is_free' => false, 'limits' => ['active_listings' => null, 'agents' => null, 'photos_per_listing' => null, 'featured_credits' => null], 'features' => ['advanced_stats' => false, 'badge' => false, 'enriched_agency_page' => false]]);

        return response()->json(['data' => $this->planArray($plan)], 201);
    }

    /**
     * Modifier une formule ne change JAMAIS une souscription existante (prix et quotas figés dans son plan_snapshot).
     */
    public function updatePlan(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $data = $request->validated();
        $plan->fill(collect($data)->except(['limits', 'features'])->all());
        if (isset($data['limits'])) {
            $plan->limits = array_merge($plan->limits, $data['limits']);
        }
        if (isset($data['features'])) {
            $plan->features = array_merge($plan->features, $data['features']);
        }
        // Une formule n'est proposée que lorsqu'elle est entièrement renseignée (et payante : prix > 0).
        if ($plan->is_active && ! $plan->is_free && (! $plan->isConfigured() || $plan->price_xof <= 0)) {
            throw new CommercialRuleException('plan_incomplete', 'Renseignez le prix et tous les quotas avant d\'activer cette formule.');
        }
        $plan->save();

        return response()->json(['data' => $this->planArray($plan)]);
    }

    public function products(): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => PremiumProduct::orderBy('kind')->orderBy('duration_days')->get()->map(fn ($p) => ['code' => $p->code, 'kind' => $p->kind->value, 'duration_days' => $p->duration_days, 'price_xof' => $p->price_xof, 'is_active' => $p->is_active])]);
    }

    public function updateProduct(Request $request, PremiumProduct $product): JsonResponse
    {
        Gate::authorize('manage-catalog');
        $data = $request->validate(['price_xof' => ['sometimes', 'integer', 'min:0', 'max:100000000000'], 'is_active' => ['sometimes', 'boolean']]);
        $product->fill($data);
        if ($product->is_active && $product->price_xof <= 0) {
            throw new CommercialRuleException('product_incomplete', 'Renseignez le prix avant d\'activer cette option.');
        }
        $product->save();

        return response()->json(['data' => ['code' => $product->code, 'price_xof' => $product->price_xof, 'is_active' => $product->is_active]]);
    }

    // ---------------------------------------------------------------- coordonnées de règlement

    public function paymentInstructions(): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => $this->settings->get('payment_instructions')]);
    }

    public function updatePaymentInstructions(Request $request): JsonResponse
    {
        Gate::authorize('manage-catalog');
        $data = $request->validate([
            'methods' => ['required', 'array', 'min:1', 'max:5'],
            'methods.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'methods.*.label' => ['required', 'string', 'max:60'],
            'methods.*.details' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->settings->put('payment_instructions', $data, $request->user());

        return response()->json(['data' => $data]);
    }

    /** @return array<string, mixed> */
    private function planArray(Plan $p): array
    {
        return ['code' => $p->code, 'name' => $p->name, 'audience' => $p->audience->value, 'price_xof' => $p->price_xof, 'period' => $p->period->value, 'limits' => $p->limits, 'features' => $p->features, 'free' => $p->is_free, 'is_active' => $p->is_active, 'configured' => $p->isConfigured()];
    }
}
