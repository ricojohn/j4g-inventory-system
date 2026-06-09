<?php

namespace App\Enums;

enum SupplierOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::PartiallyReceived => 'Partially Received',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }
}
