<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\VerifyEmailNotification;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property UserRole $role
 * @property bool $newsletter_consent
 * @property bool $is_adult_verified
 * @property Carbon|null $banned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, OAuthProvider> $oauthProviders
 * @property-read Collection<int, UserBook> $userBooks
 * @property-read Collection<int, Order> $orders
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'newsletter_consent',
        'is_adult_verified',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'newsletter_consent' => 'boolean',
            'is_adult_verified' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /** @return HasMany<OAuthProvider, $this> */
    public function oauthProviders(): HasMany
    {
        return $this->hasMany(OAuthProvider::class);
    }

    /** @return HasMany<UserBook, $this> */
    public function userBooks(): HasMany
    {
        return $this->hasMany(UserBook::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
