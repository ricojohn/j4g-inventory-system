<?php

namespace App\Enums;

enum AiOrderDraftStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Converted => 'Converted',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700',
            self::Approved => 'bg-blue-100 text-blue-800',
            self::Rejected => 'bg-red-100 text-red-800',
            self::Converted => 'bg-green-100 text-green-800',
        };
    }
}
