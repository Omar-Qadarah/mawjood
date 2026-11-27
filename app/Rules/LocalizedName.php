<?php

namespace App\Rules;

final class LocalizedName
{
    public const ARABIC = "regex:/^(?=.*(?=\\p{Arabic})\\p{L})(?:(?=\\p{scx:Arabic})[\\p{L}\\p{M}]|[ .،&’'–-])+$/uD";

    public const ENGLISH = "regex:/^(?=.*[A-Za-z])[A-Za-z .&’'–-]+$/uD";

    public const CATALOG_ARABIC = "regex:~^(?=.*(?=\\p{Arabic})\\p{L})(?:(?=\\p{scx:Arabic})[\\p{L}\\p{M}]|[0-9٠-٩۰-۹ .،,()&’'+/–-])+$~uD";

    public const CATALOG_ENGLISH = "regex:~^(?=.*[A-Za-z])[A-Za-z0-9 .,()&’'+/–-]+$~uD";
}
