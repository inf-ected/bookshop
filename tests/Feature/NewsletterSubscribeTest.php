<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Newsletter\Services\NewsletterService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class NewsletterSubscribeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent Resend\Client from being resolved (no API key in test env).
        // Individual tests override this mock with specific expectations.
        $this->mock(NewsletterService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('addContact')->byDefault();
        });
    }

    // -------------------------------------------------------------------------
    // Public subscribe endpoint
    // -------------------------------------------------------------------------

    public function test_user_can_subscribe_with_valid_email(): void
    {
        $this->mock(NewsletterService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('addContact')
                ->once()
                ->with('subscriber@example.com', '');
        });

        $response = $this->post('/newsletter/subscribe', [
            'email' => 'subscriber@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_subscribe_requires_valid_email(): void
    {
        $response = $this->post('/newsletter/subscribe', [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_subscribe_requires_email_field(): void
    {
        $response = $this->post('/newsletter/subscribe', []);

        $response->assertSessionHasErrors('email');
    }

    // -------------------------------------------------------------------------
    // Registration consent integration
    // -------------------------------------------------------------------------

    public function test_registration_with_newsletter_consent_calls_add_contact(): void
    {
        $this->mock(NewsletterService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('addContact')
                ->once()
                ->withArgs(function (string $email, string $name): bool {
                    return $email === 'consenting@example.com';
                });
        });

        $this->post('/register', [
            'name' => 'Consenting User',
            'email' => 'consenting@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
            'newsletter_consent' => '1',
        ]);

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'consenting@example.com')->first();
        $this->assertTrue($user?->newsletter_consent);
    }

    public function test_registration_without_newsletter_consent_does_not_call_add_contact(): void
    {
        $this->mock(NewsletterService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('addContact');
        });

        $this->post('/register', [
            'name' => 'No Consent User',
            'email' => 'noconsent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'noconsent@example.com')->first();
        $this->assertFalse($user?->newsletter_consent);
    }
}
