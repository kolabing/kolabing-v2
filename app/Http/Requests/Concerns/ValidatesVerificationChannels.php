<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\VerificationChannelType;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules + extraction for community verification proof channels.
 *
 * SHARED CONTRACT with the mobile app:
 *   verification_channels: array of { "type": "<slug>", "url": "<string>" }
 *   allowed types: instagram, strava, whatsapp, telegram, flaire, skool,
 *                  tiktok, website. Min 1 item when present.
 */
trait ValidatesVerificationChannels
{
    /**
     * Validation rules for the optional verification_channels array.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function verificationChannelRules(): array
    {
        return [
            'verification_channels' => ['sometimes', 'nullable', 'array', 'min:1'],
            'verification_channels.*' => ['array'],
            'verification_channels.*.type' => ['required', 'string', Rule::in(VerificationChannelType::values())],
            'verification_channels.*.url' => ['required', 'string', 'max:2048'],
        ];
    }

    /**
     * Custom messages for the channel rules.
     *
     * @return array<string, string>
     */
    protected function verificationChannelMessages(): array
    {
        return [
            'verification_channels.array' => __('The verification channels must be a list.'),
            'verification_channels.min' => __('Provide at least one verification channel.'),
            'verification_channels.*.type.required' => __('Each verification channel needs a type.'),
            'verification_channels.*.type.in' => __('The selected verification channel type is invalid.'),
            'verification_channels.*.url.required' => __('Each verification channel needs a URL.'),
        ];
    }

    /**
     * Extract the normalised channels from the validated payload, or null when the
     * key was not present in the request (so callers can distinguish "no change"
     * from "submitted").
     *
     * @return array<int, array{type: string, url: string}>|null
     */
    public function verificationChannels(): ?array
    {
        if (! $this->has('verification_channels')) {
            return null;
        }

        $channels = $this->input('verification_channels');

        if (! is_array($channels)) {
            return null;
        }

        $normalised = [];

        foreach ($channels as $channel) {
            if (! is_array($channel) || ! isset($channel['type'], $channel['url'])) {
                continue;
            }

            $normalised[] = [
                'type' => (string) $channel['type'],
                'url' => (string) $channel['url'],
            ];
        }

        return $normalised;
    }
}
