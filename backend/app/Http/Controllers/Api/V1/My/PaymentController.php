<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentIntentResource;
use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Règlement d'un séjour par le voyageur : préparer le paiement, indiquer qu'on a payé. Aucune route ne confirme un paiement. */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function store(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('asGuest', $booking);
        $data = $request->validate(['method' => ['required', Rule::enum(PaymentMethod::class)]]);

        $intent = $this->payments->createIntent($booking, $request->user(), PaymentMethod::from($data['method']));

        return (new PaymentIntentResource($intent->load('booking')))->response()->setStatusCode($intent->wasRecentlyCreated ? 201 : 200);
    }

    public function declare(Request $request, PaymentIntent $intent): PaymentIntentResource
    {
        Gate::authorize('asGuest', $intent->booking);
        $data = $request->validate(['reference' => ['required', 'string', 'min:3', 'max:120']]);

        return new PaymentIntentResource($this->payments->declare($intent, $data['reference'])->load('booking'));
    }
}
