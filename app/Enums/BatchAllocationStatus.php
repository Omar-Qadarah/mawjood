<?php

namespace App\Enums;

enum BatchAllocationStatus: string
{
    case Held = 'held';
    case Fulfilled = 'fulfilled';
    case Released = 'released';
}
