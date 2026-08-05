<?php

namespace App\Enums;

enum ProductionStage: string
{
    case Ready = 'ready';
    case Printing = 'printing';
    case Cutting = 'cutting';
    case Sewing = 'sewing';
    case Finishing = 'finishing';
    case QualityCheck = 'quality_check';
    case Packing = 'packing';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready',
            self::Printing => 'Printing',
            self::Cutting => 'Cutting',
            self::Sewing => 'Sewing',
            self::Finishing => 'Finishing',
            self::QualityCheck => 'Quality Check',
            self::Packing => 'Packing',
            self::Completed => 'Completed',
        };
    }

    /**
     * @return list<self>
     */
    public static function boardColumns(): array
    {
        return [
            self::Ready,
            self::Printing,
            self::Cutting,
            self::Sewing,
            self::Finishing,
            self::QualityCheck,
            self::Packing,
            self::Completed,
        ];
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Ready => self::Printing,
            self::Printing => self::Cutting,
            self::Cutting => self::Sewing,
            self::Sewing => self::Finishing,
            self::Finishing => self::QualityCheck,
            self::QualityCheck => self::Packing,
            self::Packing => self::Completed,
            self::Completed => null,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed;
    }
}
