<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OauthProvider;
use App\Models\OAuthProvider as OAuthProviderModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OAuthProviderModel>
 */
class OAuthProviderFactory extends Factory
{
    protected $model = OAuthProviderModel::class;

    /**
     * @return array<model-property<OAuthProviderModel>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(OauthProvider::cases())->value,
            'provider_id' => (string) fake()->unique()->randomNumber(8, true),
            'token' => fake()->sha256(),
            'refresh_token' => null,
        ];
    }
}
