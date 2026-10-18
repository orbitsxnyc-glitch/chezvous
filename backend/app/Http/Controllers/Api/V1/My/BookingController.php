<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Quote;
use App\Services\Stays\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Séjours du voyageur : demande à partir d'un devis, consultation, annulation. */
class BookingController extends Controller
{
    private const RELATIONS = ['unit.property.locality', 'guest', 'quote', 'events', 'paymentIntents'];

    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return BookingResource::collection(Booking::where('guest_user_id', $request->user()->id)->with(self::RELATIONS)->latest('id')->cursorPaginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quote' => ['required', 'string', 'max:40'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:80'],
        ]);
        $quote = Quote::where('ulid', $data['quote'])->where('guest_user_id', $request->user()->id)->firstOrFail();

        $existing = Booking::where('guest_user_id', $request->user()->id)->where('idempotency_key', $data['idempotency_key'])->exists();
        $booking = $this->bookings->request($quote, $request->user(), $data['idempotency_key']);

        // Rejeu d'une même demande : 200 et la même réservation, jamais de doublon.
        return (new BookingResource($booking->load(self::RELATIONS)))->response()->setStatusCode($existing ? 200 : 201);
    }

    public function show(Booking $booking): BookingResource
    {
        Gate::authorize('asGuest', $booking);

        return new BookingResource($booking->load(self::RELATIONS));
    }

    public function cancel(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('asGuest', $booking);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        return new BookingResource($this->bookings->cancelByGuest($booking, $request->user(), $data['reason'] ?? null)->load(self::RELATIONS));
    }
}
