<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Str;

/**
 * Shareable public URLs for events: `kolabing.com/events/sunday-beach-run-1dd66a`.
 *
 * Same convention as {@see PublicProfileLink} — a readable name plus the last six
 * characters of the UUID — so a link survives a rename and a full UUID still
 * resolves. The two are separate classes rather than one abstraction because they
 * resolve against different tables with different visibility rules; sharing the
 * six-character convention is the only thing they have in common.
 */
final class PublicEventLink
{
    private const SUFFIX_LENGTH = 6;

    public static function slugFor(Event $event): string
    {
        $readable = Str::slug((string) $event->name);

        if ($readable === '') {
            $readable = 'event';
        }

        return $readable.'-'.substr(str_replace('-', '', $event->id), -self::SUFFIX_LENGTH);
    }

    public static function urlFor(Event $event): string
    {
        return url('/events/'.self::slugFor($event));
    }

    /**
     * Resolve a slug back to an event. Only ever returns a **public** event: this is
     * the entry point for anonymous visitors, so a members-only or tier-gated event
     * must not become readable by guessing a URL.
     */
    public static function resolve(string $slug): ?Event
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $query = fn () => Event::query()
            ->where('visibility', 'public')
            ->with(['community', 'city', 'photos']);

        if (Str::isUuid($slug)) {
            return $query()->whereKey($slug)->first();
        }

        $suffix = Str::afterLast($slug, '-');

        if (strlen($suffix) === self::SUFFIX_LENGTH && preg_match('/^[0-9a-f]+$/i', $suffix) === 1) {
            // LIKE '%suffix' rather than right()/substr(): the two drivers disagree
            // on negative offsets. See PublicProfileLink for the same reasoning.
            return $query()->where('id', 'like', '%'.$suffix)->first();
        }

        return null;
    }
}
