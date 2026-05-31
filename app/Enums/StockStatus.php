<?php

namespace App\Enums;

enum StockStatus: string
{
    case Ok = 'OK';
    case LowStock = 'LOW_STOCK';
    case OutOfStock = 'OUT_OF_STOCK';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::LowStock => 'LOW STOCK',
            self::OutOfStock => 'OUT OF STOCK',
        };
    }
}
