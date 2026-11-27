<?php

namespace App\Enums;

enum InventoryBatchStatus: string
{
    case Active = 'active';
    case Quarantined = 'quarantined';
    case Damaged = 'damaged';
    case Recalled = 'recalled';
    case Inactive = 'inactive';
}
