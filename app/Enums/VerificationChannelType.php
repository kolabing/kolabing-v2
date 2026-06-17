<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Allowed proof-channel types a community can submit for verification.
 *
 * SHARED CONTRACT with the mobile app: a channel is
 * { "type": "<slug>", "url": "<string>", "is_public": <bool> } and the slug MUST
 * be one of these. For `email` and `phone` the `url` field holds the email/phone
 * value (these are contact channels merged into the same structure).
 */
enum VerificationChannelType: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Instagram = 'instagram';
    case Strava = 'strava';
    case WhatsApp = 'whatsapp';
    case Telegram = 'telegram';
    case Flaire = 'flaire';
    case Skool = 'skool';
    case TikTok = 'tiktok';
    case Website = 'website';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
