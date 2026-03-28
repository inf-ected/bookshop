<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\OAuthProvider;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_to_provider_works(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://google.com/oauth'));

        $socialite = $this->mock(SocialiteFactory::class, function (MockInterface $mock) use ($provider) {
            $mock->shouldReceive('driver')->with('google')->andReturn($provider);
        });

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect();
    }

    public function test_unknown_provider_returns_404(): void
    {
        $response = $this->get('/auth/unknown-provider/redirect');

        $response->assertStatus(404);
    }

    public function test_callback_creates_new_user_when_email_provided(): void
    {
        $socialiteUser = $this->makeSocialiteUser('123456', 'New User', 'newuser@example.com');

        $this->mockSocialiteCallback('google', $socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/cabinet');

        $user = User::query()->where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('oauth_providers', [
            'provider' => 'google',
            'provider_id' => '123456',
            'user_id' => $user->id,
        ]);
    }

    public function test_callback_links_provider_to_existing_user_with_matching_email(): void
    {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $socialiteUser = $this->makeSocialiteUser('654321', 'Existing User', 'existing@example.com');

        $this->mockSocialiteCallback('google', $socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/cabinet');

        $this->assertDatabaseHas('oauth_providers', [
            'provider' => 'google',
            'provider_id' => '654321',
            'user_id' => $existingUser->id,
        ]);

        $this->assertAuthenticatedAs($existingUser);
    }

    public function test_callback_links_existing_oauth_record_and_logs_in(): void
    {
        $user = User::factory()->create();
        OAuthProvider::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '999999',
            'token' => 'token',
            'refresh_token' => null,
        ]);

        $socialiteUser = $this->makeSocialiteUser('999999', $user->name, $user->email);

        $this->mockSocialiteCallback('google', $socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/cabinet');
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_redirects_to_complete_registration_when_no_email(): void
    {
        $socialiteUser = $this->makeSocialiteUser('777777', 'No Email User', null);

        $this->mockSocialiteCallback('google', $socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('auth.complete-registration'));

        $this->assertEquals('google', session('oauth_pending.provider'));
        $this->assertEquals('777777', session('oauth_pending.provider_id'));
    }

    public function test_complete_registration_creates_user_and_sends_verification_email(): void
    {
        Notification::fake();

        session()->put('oauth_pending', [
            'provider' => 'google',
            'provider_id' => '888888',
            'token' => 'tok',
            'refresh_token' => null,
            'name' => 'OAuth User',
        ]);

        $response = $this->post('/auth/complete-registration', [
            'email' => 'oauthuser@example.com',
        ]);

        $response->assertRedirect('/email/verify');

        $user = User::query()->where('email', 'oauthuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        $this->assertDatabaseHas('oauth_providers', [
            'provider' => 'google',
            'provider_id' => '888888',
            'user_id' => $user->id,
        ]);

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );
    }

    public function test_complete_registration_redirects_to_login_without_session(): void
    {
        $response = $this->post('/auth/complete-registration', [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_unknown_provider_callback_returns_404(): void
    {
        $response = $this->get('/auth/unknown/callback');

        $response->assertStatus(404);
    }

    private function makeSocialiteUser(string $id, ?string $name, ?string $email): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'token' => 'test-token',
            'refreshToken' => null,
        ]);

        return $user;
    }

    private function mockSocialiteCallback(string $providerName, SocialiteUser $socialiteUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        $this->mock(SocialiteFactory::class, function (MockInterface $mock) use ($providerName, $provider) {
            $mock->shouldReceive('driver')->with($providerName)->andReturn($provider);
        });
    }
}
