<?php

namespace App\Enums;

enum BookingState: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case AwaitingPayment = 'awaiting_payment';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Declined = 'declined';
    case Expired = 'expired';
    case PaymentFailed = 'payment_failed';
    case CancelledByGuest = 'cancelled_by_guest';
    case CancelledByHost = 'cancelled_by_host';
    case Disputed = 'disputed';
    case RefundedPartially = 'refunded_partially';
    case RefundedFully = 'refunded_fully';

    /** Le séjour bloque-t-il les dates (verrou ou séjour confirmé) ? */
    public function holdsDates(): bool
    {
        return in_array($this, [self::Accepted, self::AwaitingPayment, self::Confirmed, self::CheckedIn], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Declined, self::Expired, self::PaymentFailed, self::CancelledByGuest, self::CancelledByHost, self::RefundedPartially, self::RefundedFully], true);
    }
}
