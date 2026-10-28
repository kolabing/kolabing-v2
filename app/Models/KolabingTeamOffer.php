<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KolabingTeamOfferKind;
use App\Enums\KolabingTeamOfferStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offer card Kolabing sends to one profile from the "Kolabing team" thread
 * ({@see \App\Services\KolabingTeamService}). One row = one send.
 *
 * @property string $id
 * @property KolabingTeamOfferKind $kind
 * @property KolabingTeamOfferStatus $status
 * @property string $recipient_profile_id
 * @property string|null $kolab_id
 * @property string|null $community_profile_id
 * @property string $title
 * @property string|null $message
 * @property string|null $what_business_gets
 * @property string|null $what_community_gets
 * @property \Illuminate\Support\Carbon|null $proposed_date
 * @property bool $is_test_send
 * @property string $channel
 * @property string $dedupe_key
 * @property int|null $created_by_admin_id
 * @property int|null $sent_by_admin_id
 * @property string|null $chat_message_id
 * @property string|null $notification_id
 * @property string|null $result_kolab_id
 * @property string|null $result_application_id
 * @property string|null $failure_reason
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property-read Profile $recipient
 * @property-read Kolab|null $kolab
 * @property-read Profile|null $communityProfile
 */
class KolabingTeamOffer extends Model
{
    use HasUuids;

    protected $table = 'kolabing_team_offers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'status',
        'recipient_profile_id',
        'kolab_id',
        'community_profile_id',
        'title',
        'message',
        'what_business_gets',
        'what_community_gets',
        'proposed_date',
        'is_test_send',
        'channel',
        'dedupe_key',
        'created_by_admin_id',
        'sent_by_admin_id',
        'chat_message_id',
        'notification_id',
        'result_kolab_id',
        'result_application_id',
        'failure_reason',
        'sent_at',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => KolabingTeamOfferKind::class,
            'status' => KolabingTeamOfferStatus::class,
            'proposed_date' => 'date',
            'is_test_send' => 'boolean',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function recipient(): BelongsTo
    {
        // withTrashed: a deleted recipient must show up as a refusal reason, not a null.
        return $this->belongsTo(Profile::class, 'recipient_profile_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Kolab, $this>
     */
    public function kolab(): BelongsTo
    {
        return $this->belongsTo(Kolab::class);
    }

    /**
     * The named community of a business_propose offer.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function communityProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'community_profile_id')->withTrashed();
    }

    public function isOpenForResponse(): bool
    {
        return $this->status === KolabingTeamOfferStatus::Sent;
    }
}
