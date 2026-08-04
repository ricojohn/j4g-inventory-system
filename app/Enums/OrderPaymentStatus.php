<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartialDp = 'partial_dp';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartialDp => 'Partial / DP',
            self::Paid => 'Paid',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-red-100 text-red-800',
            self::PartialDp => 'bg-amber-100 text-amber-800',
            self::Paid => 'bg-green-100 text-green-800',
        };
    }
}
