<?php

namespace Database\Seeders;

use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) env('ADMIN_NAME'));
        $email = strtolower(trim((string) env('ADMIN_EMAIL')));
        $password = (string) env('ADMIN_PASSWORD');

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === '') {
            throw new RuntimeException('Set ADMIN_NAME, ADMIN_EMAIL, and ADMIN_PASSWORD before running AdminUserSeeder.');
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->platform_role = PlatformRole::Admin;
        $user->is_active = true;
        $user->save();
    }
}
