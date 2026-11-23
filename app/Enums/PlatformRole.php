<?php

namespace App\Enums;

enum PlatformRole: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}