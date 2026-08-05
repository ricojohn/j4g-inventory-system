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

    /**
     * @return list<self>
     */
    public static function boardColumns(): array
    {
        return [
            self::Pending,
            self::PartiallyReserved,
            self::Reserved,
            self::Fulfilled,
            self::Cancelled,
        ];
    }

    /**
     * @return list<self>
     */
    public function kanbanTargets(): array
    {
        return match ($this) {
            self::Pending => [self::Cancelled],
            self::Reserved, self::PartiallyReserved => [self::Fulfilled, self::Cancelled],
            self::Fulfilled, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $target): bool
    {
        return in_array($target, $this->kanbanTargets(), true);
    }

    public function allowsFulfill(): bool
    {
        return $this->canMoveTo(self::Fulfilled);
    }

    public function allowsCancel(): bool
    {
        return $this->canMoveTo(self::Cancelled);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Fulfilled, self::Cancelled], true);
    }

    public function isOpen(): bool
    {
        return ! $this->isTerminal();
    }
}
