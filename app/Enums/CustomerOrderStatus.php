<?php

namespace App\Enums;

enum CustomerOrderStatus: string
{
    case Pending = 'pending';
    case Reserved = 'reserved';
    case PartiallyReserved = 'partially_reserved';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reserved => 'Reserved',
            self::PartiallyReserved => 'Partially Reserved',
            self::Fulfilled => 'Fulfilled',
            self::Cancelled => 'Cancelled',
        };
    }
}
