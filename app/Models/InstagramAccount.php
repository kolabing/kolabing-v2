<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Kolabing profile's connected Instagram professional account (BE-NF-75).
 *
 * The long-lived token is encrypted at rest and never serialised. A NULL token
 * means disconnected: the row is kept so a later Meta data-deletion request can
 * still find what this account imported (docs/instagram-connect.md).
 *
 * @property string $id
 * @property string $profile_id
 * @property string $ig_user_id
 * @property string|null $ig_app_scoped_id
 * @property string $username
 * @property string|null $name
 * @property string|null $account_type
 * @property string|null $profile_picture_url
 * @property string|null $access_token
 * @property \Illuminate\Support\Carbon|null $token_expires_at
 * @property \Illuminate\Support\Carbon|null $token_refreshed_at
 * @property \Illuminate\Support\Carbon|null $connected_at
 * @property \Illuminate\Support\Carbon|null $disconnected_at
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property-read Profile $profile
 */
class InstagramAccount extends Model
{
    /** @use HasFactory<\Database\Factories\InstagramAccountFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'ig_user_id',
        'ig_app_scoped_id',
        'username',
        'name',
        'account_type',
        'profile_picture_url',
        'access_token',
        'token_expires_at',
        'token_refreshed_at',
        'connected_at',
        'disconnected_at',
        'last_synced_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'token_refreshed_at' => 'datetime',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function isConnected(): bool
    {
        return $this->access_token !== null
            && ($this->token_expires_at === null || $this->token_expires_at->isFuture());
    }
}
