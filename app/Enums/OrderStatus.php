<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Refunded = 'refunded';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowed(), true);
    }

    /**
     * @return list<self>
     */
    public function allowed(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Shipped, self::Refunded],
            self::Shipped => [self::Completed, self::Refunded],
            self::Completed, self::Cancelled, self::Refunded => [],
        };
    }
}
