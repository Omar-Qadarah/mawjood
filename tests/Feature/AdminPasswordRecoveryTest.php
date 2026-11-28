<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Notifications\AdminPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const REQUEST_LINK = '/api/v1/auth/admin/forgot-password';

    private const RESET = '/api/v1/auth/admin/reset-password';

    public function test_only_active_admins_receive_a_reset_link(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $inactive = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => false]);
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);

        foreach ([$admin->email, $inactive->email, $customer->email, 'unknown@example.test'] as $email) {
            $this->postJson(self::REQUEST_LINK, ['email' => $email])->assertNoContent();
        }

        Notification::assertSentTo($admin, AdminPasswordReset::class,
            fn (AdminPasswordReset $notification) => collect($notification->toMail($admin)->outroLines)
                ->contains(fn (string $line) => str_contains($line, 'expire in 10 minutes')));
        Notification::assertNotSentTo($inactive, AdminPasswordReset::class);
        Notification::assertNotSentTo($customer, AdminPasswordReset::class);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_admin_can_reset_once_with_a_valid_token_and_strong_password(): void
    {
        $admin = User::factory()->create([
            'platform_role' => PlatformRole::Admin, 'is_active' => true,
            'password' => 'Old-password1!', 'remember_token' => 'old-token',
        ]);
        $data = ['email' => $admin->email, 'token' => Password::createToken($admin)];

        $this->postJson(self::RESET, $data + [
            'password' => 'NoSymbol1', 'password_confirmation' => 'NoSymbol1',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $valid = $data + ['password' => 'New!Pwd9', 'password_confirmation' => 'New!Pwd9'];
        $this->postJson(self::RESET, $valid)->assertNoContent();
        $this->postJson(self::RESET, $valid)->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertTrue(Hash::check('New!Pwd9', $admin->fresh()->password));
        $this->assertNull($admin->fresh()->remember_token);
    }

    public function test_expired_links_and_customer_tokens_are_rejected(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $adminToken = Password::createToken($admin);
        $this->travel(11)->minutes();
        $customerToken = Password::createToken($customer);

        foreach ([[$admin, $adminToken], [$customer, $customerToken]] as [$user, $token]) {
            $this->postJson(self::RESET, [
                'email' => $user->email, 'token' => $token,
                'password' => 'New!Pwd9', 'password_confirmation' => 'New!Pwd9',
            ])->assertUnprocessable()->assertJsonValidationErrors('token');
        }
    }
}
