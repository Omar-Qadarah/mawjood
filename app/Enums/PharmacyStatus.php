<?php

namespace App\Enums;

enum PharmacyStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}
