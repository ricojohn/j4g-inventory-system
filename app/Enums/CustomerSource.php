<?php

namespace App\Enums;

enum CustomerSource: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Viber = 'viber';
    case WhatsApp = 'whatsapp';
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Viber => 'Viber',
            self::WhatsApp => 'WhatsApp',
            self::WalkIn => 'Walk-in',
            self::Referral => 'Referral',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Facebook => '📘',
            self::Instagram => '📷',
            self::Viber => '💬',
            self::WhatsApp => '💬',
            self::WalkIn => '🚶',
            self::Referral => '🤝',
            self::Other => '📌',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Facebook => 'bg-blue-100 text-blue-700',
            self::Instagram => 'bg-pink-100 text-pink-700',
            self::Viber => 'bg-violet-100 text-violet-700',
            self::WhatsApp => 'bg-green-100 text-green-700',
            self::WalkIn => 'bg-gray-100 text-gray-700',
            self::Referral => 'bg-amber-100 text-amber-700',
            self::Other => 'bg-gray-100 text-gray-600',
        };
    }
}
