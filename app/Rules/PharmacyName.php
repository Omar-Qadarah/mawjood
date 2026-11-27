<?php

namespace App\Rules;

final class PharmacyName
{
    public const ARABIC = "regex:/^(?=.*(?=\\p{Arabic})\\p{L})(?:(?=\\p{Arabic})\\p{L}|\\p{M}|[ .،&’'–-])+$/uD";

    public const ENGLISH = "regex:/^(?=.*[A-Za-z])[A-Za-z .&’'–-]+$/uD";
}
