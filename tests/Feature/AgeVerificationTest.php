<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgeVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_verify_age_via_session(): void
    {
        $this->post(route('age-verification.store'))
            ->assertRedirect();

        $this->assertEquals('accepted', session('adult_consent'));
    }

    public function test_authenticated_user_can_verify_age(): void
    {
        $user = User::factory()->create(['is_adult_verified' => false]);

        $this->actingAs($user)
            ->postJson(route('age-verification.store'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_adult_verified' => true,
        ]);
    }

    public function test_already_verified_user_stays_verified(): void
    {
        $user = User::factory()->create(['is_adult_verified' => true]);

        $this->actingAs($user)
            ->postJson(route('age-verification.store'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_adult_verified' => true,
        ]);
    }
}
