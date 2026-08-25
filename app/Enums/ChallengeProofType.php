<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a challenge is played (kolabing-v2#216).
 *
 * This is an **engine selector, not a gate**. It tells the app which surface to
 * open once a pair agrees on a challenge; it does not make the server refuse a
 * verification that arrives with no photo attached.
 *
 * Deliberate. A hard requirement means a pair who cannot get a photo up — no
 * signal in a basement gym, a denied camera permission, a failed upload — cannot
 * earn what they just did together. The person confirming is already the check
 * on honesty, and that is the check the whole loop is built on. The photo is a
 * memento and a nudge; the moment it is mandatory it becomes a way to lose
 * points to a bad connection.
 */
enum ChallengeProofType: string
{
    /**
     * The instruction is the game. A photo may be attached at the end, and
     * usually is not. The default, and what every challenge that predates this
     * column reports.
     */
    case Text = 'text';

    /**
     * Open the camera as soon as the pair agrees. "Take a selfie together"
     * should not present itself as a reading exercise.
     */
    case Photo = 'photo';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
