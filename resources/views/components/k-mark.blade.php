{{--
  The Kolabing K — the brand's one mark, everywhere.

  Replaces the cloud wordmark PNGs (`/webapp-assets/wordmark-*.png`,
  `/brand/kolabing-logo.webp`). It is the same asset the mobile app icon, the
  app's splash and its app bar use, so a user meets one shape across the site,
  app.kolabing and the phone.

  It fills itself once on load, the way the app's splash does. The waterline is
  straight rather than the app's wavy one — a 3px wave is invisible at nav size
  and would be CSS guesswork at any size.

  Props:
    size    rendered height in px (the mark is all but square). Pass null to
            leave sizing to CSS — the marketing header needs that, because its
            mark shrinks on a media query and an inline height would win.
    tone    'auto'  ink on a light surface, brand yellow under [data-theme=dark]
            'dark'  always ink — for the yellow marketing header
            'light' always brand yellow — for dark grounds (footer, login hero)
    animate false to paint the finished mark with no fill
    class   extra classes on the wrapper (rotation, drop-shadow, …)

  Usage: <x-k-mark :size="24" /> · <x-k-mark :size="38" tone="dark" class="logo-mark" />
--}}
@props([
    'size' => 28,
    'tone' => 'auto',
    'animate' => true,
    'class' => '',
])

@php
    // 657x636 in the source; keeping the ratio stops the letter stretching.
    $box = $size === null
        ? ''
        : 'width: ' . round($size * 657 / 636, 1) . 'px; height: ' . $size . 'px;';
    $src = '/brand/kolabing-k-mark.png?v=2';
@endphp

@once
    <style>
        .k-mark { position: relative; display: inline-block; line-height: 0; }
        .k-mark img { display: block; width: 100%; height: 100%; }
        /* The empty vessel: enough to read as the letter, dim enough that the
           fill line is the thing you watch. */
        .k-mark__empty { opacity: .22; }
        .k-mark__full { position: absolute; inset: 0; clip-path: inset(100% 0 0 0); }
        .k-mark--static .k-mark__full { clip-path: inset(0 0 0 0); }
        .k-mark--fill .k-mark__full { animation: k-mark-fill 900ms cubic-bezier(.65, 0, .35, 1) forwards; }
        @keyframes k-mark-fill {
            from { clip-path: inset(100% 0 0 0); }
            to   { clip-path: inset(0 0 0 0); }
        }
        /* brightness(0) turns the yellow asset into ink without shipping a
           second PNG, and it keeps the letter's own alpha. */
        .k-mark--dark img { filter: brightness(0); }
        .k-mark--auto img { filter: brightness(0); }
        [data-theme="dark"] .k-mark--auto img { filter: none; }
        @media (prefers-reduced-motion: reduce) {
            .k-mark--fill .k-mark__full { animation: none; clip-path: inset(0 0 0 0); }
        }
    </style>
@endonce

<span
    class="k-mark k-mark--{{ $tone }} {{ $animate ? 'k-mark--fill' : 'k-mark--static' }} {{ $class }}"
    @if ($box) style="{{ $box }}" @endif
    role="img"
    aria-label="Kolabing"
>
    <img class="k-mark__empty" src="{{ $src }}" alt="" width="657" height="636">
    <img class="k-mark__full" src="{{ $src }}" alt="" width="657" height="636">
</span>
