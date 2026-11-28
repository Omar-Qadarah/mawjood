<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/admin/profile';

    public function test_admin_can_update_name_and_replace_private_profile_photo(): void
    {
        $disk = Storage::fake('local');
        $admin = $this->admin();
        $disk->put('admin-avatars/old.png', 'old');
        $admin->forceFill(['avatar_path' => 'admin-avatars/old.png'])->save();
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH1sAAAAASUVORK5CYII=');
        $response = $this->actingAs($admin, 'web')->post(self::URL, [
            'name' => 'Mawjood Administrator',
            'avatar' => UploadedFile::fake()->createWithContent('profile.png', $bytes),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.name', 'Mawjood Administrator')->assertJsonMissingPath('data.avatar_path');
        $path = $admin->fresh()->avatar_path;
        $disk->assertExists($path);
        $disk->assertMissing('admin-avatars/old.png');
        $this->assertSame($bytes, $this->get($response->json('data.avatar_url'))->assertOk()->streamedContent());
        $this->postJson(self::URL, ['name' => 'Updated Name'])->assertOk()->assertJsonPath('data.name', 'Updated Name');
        $this->assertSame($path, $admin->fresh()->avatar_path);
    }

    public function test_password_change_requires_current_password_and_matching_confirmation(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'web');
        $payload = ['password' => 'New-test-password1', 'password_confirmation' => 'New-test-password1'];
        $this->patchJson(self::URL.'/password', $payload + ['current_password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson(self::URL.'/password', ['password_confirmation' => 'mismatch'] + $payload + ['current_password' => 'Original-test-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        foreach (['Aa1!bcd', 'abcdef1!', 'ABCDEF1!', 'Abcdefg!', 'Abcd ef1'] as $weak) {
            $this->patchJson(self::URL.'/password', [
                'current_password' => 'Original-test-password', 'password' => $weak, 'password_confirmation' => $weak,
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->patchJson(self::URL.'/password', $payload + ['current_password' => 'Original-test-password'])->assertNoContent();
        $this->assertTrue(Hash::check('New-test-password1', $admin->fresh()->password));
        $this->assertFalse(Hash::check('Original-test-password', $admin->fresh()->password));
        $this->assertNull($admin->fresh()->remember_token);
    }

    public function test_customers_cannot_access_admin_profile_endpoints(): void
    {
        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]), 'web');
        $this->postJson(self::URL, ['name' => 'Unauthorized'])->assertForbidden();
        $this->getJson(self::URL.'/avatar')->assertForbidden();
        $this->patchJson(self::URL.'/password')->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'platform_role' => PlatformRole::Admin, 'is_active' => true,
            'password' => 'Original-test-password', 'remember_token' => 'test-token',
        ]);
    }
}
