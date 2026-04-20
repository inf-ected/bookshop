<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OauthProvider as OauthProviderEnum;
use Database\Factories\OAuthProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property OauthProviderEnum $provider
 * @property string $provider_id
 * @property string|null $token
 * @property string|null $refresh_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class OAuthProvider extends Model
{
    /** @use HasFactory<OAuthProviderFactory> */
    use HasFactory;

    protected $table = 'oauth_providers';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'provider' => OauthProviderEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
