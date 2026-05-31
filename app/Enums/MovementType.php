<?php

namespace App\Enums;

enum MovementType: string
{
    case In = 'IN';
    case Out = 'OUT';
    case Reserve = 'RESERVE';
    case Release = 'RELEASE';
    case Damaged = 'DAMAGED';
    case Adjustment = 'ADJUSTMENT';
}
