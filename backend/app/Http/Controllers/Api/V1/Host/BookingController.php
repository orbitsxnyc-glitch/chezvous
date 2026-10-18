<?php

namespace App\Http\Controllers\Api\V1\Host;

use App\Enums\BookingState;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Unit;
use App\Services\Stays\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Demandes et séjours côté hôte : liste limitée à ses unités, décisions via les droits de l'unité. */
class BookingController extends Controller
{
    private const RELATIONS = ['unit.property.locality', 'guest', 'quote', 'events', 'paymentIntents'];

    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('publish-listing');
        $request->validate(['state' => ['sometimes', Rule::enum(BookingState::class)]]);

        $query = Booking::whereIn('unit_id', Unit::visibleTo($request->user())->select('units.id'))
            ->when($request->query('state'), fn ($q, $s) => $q->where('state', $s))
            ->with(self::RELATIONS)->latest('id');

        return BookingResource::collection($query->cursorPaginate(20)->withQueryString());
    }

    public function show(Booking $booking): BookingResource
    {
        Gate::authorize('asHost', $booking);

        return new BookingResource($booking->load(self::RELATIONS));
    }

    public function accept(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('asHost', $booking);

        return new BookingResource($this->bookings->accept($booking, $request->user())->load(self::RELATIONS));
    }

    public function decline(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('asHost', $booking);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return new BookingResource($this->bookings->decline($booking, $request->user(), $data['reason'])->load(self::RELATIONS));
    }

    public function cancel(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('asHost', $booking);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return new BookingResource($this->bookings->cancelByHost($booking, $request->user(), $data['reason'])->load(self::RELATIONS));
    }
}
