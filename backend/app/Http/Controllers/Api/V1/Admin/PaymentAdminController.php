<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Services\Payments\PaymentService;
use App\Services\Payments\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Encaissement manuel : l'administrateur confirme la réception d'un paiement ou l'exécution d'un remboursement. */
class PaymentAdminController extends Controller
{
    public function __construct(private readonly PaymentService $payments, private readonly RefundService $refunds) {}

    public function intents(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $status = $request->validate(['status' => ['nullable', 'in:created,pending,succeeded,failed,expired,cancelled,refunded,partially_refunded']])['status'] ?? 'pending';

        $rows = PaymentIntent::where('status', $status)->with('booking.guest')->orderBy('id')->limit(100)->get()->map(fn (PaymentIntent $i) => [
            'id' => $i->ulid,
            'booking' => $i->booking->ulid,
            'guest_first_name' => explode(' ', trim($i->booking->guest->name))[0],
            'method' => $i->method->value,
            'amount_xof' => $i->amount_xof,
            'reference' => $i->provider_ref,
            'declared_reference' => $i->declared_reference,
            'declared_at' => $i->declared_at?->toIso8601String(),
            'status' => $i->status,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function confirm(Request $request, PaymentIntent $intent): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['amount_xof' => ['required', 'integer', 'min:1'], 'currency' => ['nullable', 'in:XOF']]);

        $intent = $this->payments->confirmByAdmin($intent, $request->user(), (int) $data['amount_xof'], $data['currency'] ?? 'XOF');

        return response()->json(['data' => ['id' => $intent->ulid, 'status' => $intent->status, 'booking_state' => $intent->booking->fresh()->state->value]]);
    }

    public function reject(Request $request, PaymentIntent $intent): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return response()->json(['data' => ['id' => $intent->ulid, 'status' => $this->payments->rejectByAdmin($intent, $request->user(), $data['reason'])->status]]);
    }

    public function refunds(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $status = $request->validate(['status' => ['nullable', 'in:requested,succeeded,failed']])['status'] ?? 'requested';

        return response()->json(['data' => Refund::where('status', $status)->with('intent', 'booking')->orderBy('id')->limit(100)->get()->map(fn (Refund $r) => [
            'id' => $r->ulid, 'booking' => $r->booking->ulid, 'reason' => $r->reason, 'amount_xof' => $r->amount_xof, 'status' => $r->status,
            'method' => $r->intent->method->value, 'payment_reference' => $r->intent->provider_ref,
        ])]);
    }

    public function confirmRefund(Request $request, Refund $refund): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['reference' => ['required', 'string', 'min:3', 'max:120']]);

        return response()->json(['data' => ['id' => $refund->ulid, 'status' => $this->refunds->confirm($refund, $request->user(), $data['reference'])->status]]);
    }

    public function failRefund(Request $request, Refund $refund): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return response()->json(['data' => ['id' => $refund->ulid, 'status' => $this->refunds->fail($refund, $request->user(), $data['reason'])->status]]);
    }
}
