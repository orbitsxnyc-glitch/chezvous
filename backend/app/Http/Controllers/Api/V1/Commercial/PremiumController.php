<?php

namespace App\Http\Controllers\Api\V1\Commercial;

use App\Exceptions\CommercialRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\DeclarePaymentRequest;
use App\Http\Resources\PremiumListingResource;
use App\Models\Listing;
use App\Models\PremiumListing;
use App\Models\PremiumProduct;
use App\Services\Commercial\PaymentService;
use App\Services\Commercial\PremiumService;
use App\Services\Commercial\SettingsService;
use App\Services\Commercial\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PremiumController extends Controller
{
    public function __construct(
        private readonly PremiumService $premium,
        private readonly PaymentService $payments,
        private readonly SubscriptionService $subscriptions,
        private readonly SettingsService $settings,
    ) {}

    /** Demande d'une mise en avant (le gérant d'agence ou le propriétaire particulier, pas un simple agent). */
    public function store(Request $request, Listing $listing): JsonResponse
    {
        Gate::authorize('manage', $listing);
        $this->subscriptions->subscriberFor($request->user()); // refuse un agent ou un simple client
        $data = $request->validate(['product' => ['required', 'string', 'max:40']]);
        $product = PremiumProduct::where('code', $data['product'])->first()
            ?? throw new CommercialRuleException('product_unavailable', 'Cette option n\'est pas proposée pour le moment.');

        $premium = $this->premium->request($request->user(), $listing, $product)->load('listing');

        return response()->json(['data' => (new PremiumListingResource($premium))->toArray($request), 'payment_instructions' => $premium->source->value === 'payment' ? $this->settings->get('payment_instructions') : null], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->subscriptions->subscriberFor($request->user());
        $rows = PremiumListing::with('listing')->whereIn('listing_id', Listing::visibleTo($request->user())->select('id'))->orderByDesc('id')->get();

        return response()->json(['data' => $rows->map(fn ($p) => (new PremiumListingResource($p))->toArray($request))]);
    }

    public function cancel(Request $request, string $premium): JsonResponse
    {
        $target = $this->owned($request, $premium);
        $this->premium->cancelPending($target, $request->user(), 'Annulée par le demandeur');

        return response()->json(null, 204);
    }

    public function declare(DeclarePaymentRequest $request, string $premium): JsonResponse
    {
        $target = $this->owned($request, $premium);
        $payment = $this->payments->declare($target, $request->user(), $request->validated(), $request->file('proof'));

        return response()->json(['data' => ['id' => $payment->ulid, 'status' => $payment->status->value, 'settlement' => $this->payments->settlementStatus($target)]], 201);
    }

    /** Une option n'est accessible que par celui qui peut gérer l'annonce concernée (sinon 404). */
    private function owned(Request $request, string $ulid): PremiumListing
    {
        $this->subscriptions->subscriberFor($request->user());
        $premium = PremiumListing::with('listing')->where('ulid', $ulid)->firstOrFail();
        Gate::authorize('manage', $premium->listing);

        return $premium;
    }
}
