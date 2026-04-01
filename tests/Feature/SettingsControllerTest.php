<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OAuthProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Profile update ────────────────────────────────────────────────────────

    public function test_user_can_update_profile_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->put(route('cabinet.settings.update'), [
            'name' => 'New Name',
        ]);

        $response->assertRedirectToRoute('cabinet.settings');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_profile_update_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('cabinet.settings.update'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_email_is_not_updated_via_profile_update(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        // Attempt to change email by injecting into the name update payload
        $this->actingAs($user)->put(route('cabinet.settings.update'), [
            'name' => 'New Name',
            'email' => 'hacker@example.com',
        ]);

        // Email must remain unchanged
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'original@example.com',
        ]);
    }

    // ─── Password update ───────────────────────────────────────────────────────

    public function test_user_with_password_can_change_it(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)->put(route('cabinet.settings.password'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirectToRoute('cabinet.settings');
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_update_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('real-password')]);

        $response = $this->actingAs($user)->put(route('cabinet.settings.password'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    /**
     * Rule 47: Cannot change password if user has no password set.
     * authorize() in UpdatePasswordRequest returns false → 403.
     */
    public function test_oauth_only_user_cannot_change_password_rule_47(): void
    {
        $user = User::factory()->create(['password' => null]);

        $response = $this->actingAs($user)->put(route('cabinet.settings.password'), [
            'current_password' => 'anything',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertForbidden();
        $this->assertNull($user->fresh()->password);
    }

    public function test_password_update_requires_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)->put(route('cabinet.settings.password'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    // ─── OAuth provider unlinking ───────────────────────────────────────────────

    /**
     * Rule 45: User with password can always unlink a provider.
     */
    public function test_user_with_password_can_unlink_oauth_provider(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret')]);
        OAuthProvider::factory()->create(['user_id' => $user->id, 'provider' => 'google']);

        $response = $this->actingAs($user)->delete(route('cabinet.settings.oauth.unlink', ['provider' => 'google']));

        $response->assertRedirectToRoute('cabinet.settings');
        $this->assertDatabaseMissing('oauth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    /**
     * Rule 45: Cannot unlink last OAuth provider when user has no password.
     */
    public function test_oauth_only_user_cannot_unlink_last_provider_rule_45(): void
    {
        $user = User::factory()->create(['password' => null]);
        OAuthProvider::factory()->create(['user_id' => $user->id, 'provider' => 'google']);

        $response = $this->actingAs($user)->delete(route('cabinet.settings.oauth.unlink', ['provider' => 'google']));

        $response->assertSessionHasErrors('provider');
        $this->assertDatabaseHas('oauth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    /**
     * Rule 45: Can unlink one of many providers even without a password.
     */
    public function test_oauth_user_can_unlink_when_another_provider_exists(): void
    {
        $user = User::factory()->create(['password' => null]);
        OAuthProvider::factory()->create(['user_id' => $user->id, 'provider' => 'google']);
        OAuthProvider::factory()->create(['user_id' => $user->id, 'provider' => 'facebook', 'provider_id' => 'fb-id-999']);

        $response = $this->actingAs($user)->delete(route('cabinet.settings.oauth.unlink', ['provider' => 'google']));

        $response->assertRedirectToRoute('cabinet.settings');
        $this->assertDatabaseMissing('oauth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
        // Facebook provider still exists
        $this->assertDatabaseHas('oauth_providers', [
            'user_id' => $user->id,
            'provider' => 'facebook',
        ]);
    }

    public function test_unlinking_unknown_provider_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('cabinet.settings.oauth.unlink', ['provider' => 'nonexistent']));

        $response->assertNotFound();
    }

    public function test_settings_page_accessible_to_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cabinet.settings'));

        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_settings(): void
    {
        $response = $this->get(route('cabinet.settings'));

        $response->assertRedirectToRoute('login');
    }
}
