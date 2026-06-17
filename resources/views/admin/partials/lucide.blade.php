{{--
    Server-side inline Lucide icon, rendered by slug. No CDN, no client-side JS
    timing — the SVG is baked into the HTML at render time, so it is identical
    locally and in production. The same Lucide slugs are what the mobile app
    renders, so admins see exactly what users see.

    Usage: @include('admin.partials.lucide', ['slug' => $o->icon, 'class' => 'lucide-22'])

    The stored `icon` value uses hyphenated Lucide names (e.g. door-open, share-2).
    We normalise underscores to hyphens defensively, prefix with `lucide-`, and
    fall back to a neutral placeholder if the slug is not a real Lucide icon so a
    typo never throws.
--}}
@php
    $lucideSlug = trim((string) ($slug ?? ''));
    $lucideClass = $class ?? 'lucide-icon';
    $lucideName = $lucideSlug !== '' ? 'lucide-'.str_replace('_', '-', $lucideSlug) : 'lucide-help-circle';
    try {
        $lucideSvg = svg($lucideName, $lucideClass)->toHtml();
    } catch (\BladeUI\Icons\Exceptions\SvgNotFound $e) {
        $lucideSvg = svg('lucide-help-circle', $lucideClass.' lucide-missing')->toHtml();
    }
@endphp
{!! $lucideSvg !!}
