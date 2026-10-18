<?php

namespace App\Http\Controllers\Api\V1\Host;

use App\Enums\BookingState;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\HostPayout;
use App\Models\Unit;
use App\Services\Stays\BookingService;
use App\Services\Stays\SupportService;
use Illuminate\Http\JsonResponse;
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

    public function checkIn(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('asHost', $booking);

        return new BookingResource($this->bookings->checkIn($booking, $request->user())->load(self::RELATIONS));
    }

    public function complete(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('asHost', $booking);

        return new BookingResource($this->bookings->complete($booking, $request->user())->load(self::RELATIONS));
    }

    public function dispute(Request $request, Booking $booking, SupportService $support): JsonResponse
    {
        Gate::authorize('asHost', $booking);
        $data = $request->validate(['description' => ['required', 'string', 'min:10', 'max:2000'], 'severity' => ['nullable', 'in:low,normal,high']]);
        $case = $support->open($booking, $request->user(), 'host', $data['description'], $data['severity'] ?? 'normal');

        return response()->json(['data' => ['id' => $case->ulid, 'status' => $case->status]], 201);
    }

    /** Reversements de l'hôte connecté : montant dû, état, référence une fois payé. */
    public function payouts(Request $request): JsonResponse
    {
        Gate::authorize('publish-listing');

        return response()->json(['data' => HostPayout::where('host_user_id', $request->user()->id)->with('booking.unit')->latest('id')->limit(100)->get()->map(fn (HostPayout $p) => [
            'id' => $p->ulid, 'booking' => $p->booking->ulid, 'unit' => $p->booking->unit->name, 'amount_xof' => $p->amount_xof, 'status' => $p->status, 'reference' => $p->reference,
        ])]);
    }

    public function cancel(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('asHost', $booking);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return new BookingResource($this->bookings->cancelByHost($booking, $request->user(), $data['reason'])->load(self::RELATIONS));
    }
}
