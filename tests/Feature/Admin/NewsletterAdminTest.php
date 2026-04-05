<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Features\Newsletter\Services\NewsletterService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class NewsletterAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent Resend\Client from being resolved (no API key in test env).
        // Individual tests override this mock with specific expectations.
        $this->mock(NewsletterService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getSubscriberCount')->andReturn(null)->byDefault();
            $mock->shouldReceive('sendBroadcast')->byDefault();
        });
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function test_non_admin_cannot_access_newsletter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/newsletter')->assertStatus(404);
    }

    public function test_guest_is_redirected_from_newsletter_index(): void
    {
        $this->get('/admin/newsletter')->assertRedirect('/login');
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_admin_can_view_newsletter_index(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/newsletter')->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Send broadcast
    // -------------------------------------------------------------------------

    public function test_admin_can_send_newsletter(): void
    {
        $this->mock(NewsletterService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getSubscriberCount')->andReturn(null)->byDefault();
            $mock->shouldReceive('sendBroadcast')
                ->once()
                ->with('Тестовая тема', '<p>Контент рассылки</p>');
        });

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/newsletter/send', [
            'subject' => 'Тестовая тема',
            'body' => '<p>Контент рассылки</p>',
            'confirm_send' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_send_newsletter_requires_subject(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/newsletter/send', [
            'body' => '<p>Контент</p>',
            'confirm_send' => '1',
        ]);

        $response->assertSessionHasErrors('subject');
    }

    public function test_send_newsletter_requires_confirmation(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/newsletter/send', [
            'subject' => 'Тема',
            'body' => '<p>Контент</p>',
        ]);

        $response->assertSessionHasErrors('confirm_send');
    }
}
