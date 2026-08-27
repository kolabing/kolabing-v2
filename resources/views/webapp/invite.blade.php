@extends('webapp.layout')
@section('title', __('webapp.ghost_invite.title'))

@section('body')
{{-- Where a ghost invite lands for someone who does NOT have the app (#246).

     On a phone that has it, this page is never seen: the Universal Link hands
     the URL straight to the app. So everything here is written for the other
     case — and that case has exactly one job, because a Universal Link carries
     no state through the App Store: show the code big enough to remember. --}}
<div class="min-h-screen flex items-center justify-center px-5 py-10 bg-cream">
    <div class="w-full max-w-[420px]">

        <div class="block mb-7 text-center">
            <img src="/webapp-assets/wordmark-light.png" alt="Kolabing" class="h-7 w-auto inline-block">
        </div>

        <div class="bg-white border border-ink/[.08] rounded-[22px] p-7 text-center kb-fade-up">

            @if ($status === 'open')
                <p class="text-[19px] font-bold text-ink leading-snug">
                    @if ($inviterName)
                        {{ __('webapp.ghost_invite.heading', ['name' => $inviterName]) }}
                    @else
                        {{ __('webapp.ghost_invite.heading_generic') }}
                    @endif
                </p>

                @if ($eventName)
                    <p class="mt-1 text-[14px] text-ink/60">
                        {{ __('webapp.ghost_invite.at_event', ['event' => $eventName]) }}
                    </p>
                @endif

                {{-- The reason the page exists. Big, spaced, and selectable:
                     someone is going to read this back to themselves after an
                     install, possibly in a noisy room. --}}
                <p class="mt-6 text-[12px] uppercase tracking-[.14em] text-ink/50 font-bold">
                    {{ __('webapp.ghost_invite.code_label') }}
                </p>
                <p class="mt-2 select-all font-mono font-extrabold text-ink text-[38px] leading-none tracking-[.18em]">
                    {{ $code }}
                </p>
                <p class="mt-3 text-[13px] text-ink/60 leading-relaxed">
                    {{ __('webapp.ghost_invite.code_hint') }}
                </p>

                @if ($points > 0)
                    <p class="mt-5 inline-block rounded-full bg-primary/30 px-4 py-1.5 text-[13px] font-bold text-ink">
                        {{ __('webapp.ghost_invite.reward', ['points' => $points]) }}
                    </p>
                @endif

                <div class="mt-7 space-y-2.5">
                    @if ($appStoreUrl)
                        <a href="{{ $appStoreUrl }}" rel="noopener"
                           class="block w-full rounded-[14px] bg-ink py-3.5 text-[15px] font-bold text-cream">
                            {{ __('webapp.ghost_invite.app_store') }}
                        </a>
                    @endif
                    @if ($playStoreUrl)
                        <a href="{{ $playStoreUrl }}" rel="noopener"
                           class="block w-full rounded-[14px] border border-ink/15 py-3.5 text-[15px] font-bold text-ink">
                            {{ __('webapp.ghost_invite.play_store') }}
                        </a>
                    @endif
                </div>

                <p class="mt-5 text-[12px] text-ink/50">
                    {{ __('webapp.ghost_invite.have_app') }}
                </p>
            @else
                {{-- Claimed, expired or unknown. Rendered as its own state rather
                     than a 404: someone tapping a link a fortnight late deserves
                     to be told what happened, not shown a dead end. --}}
                <p class="text-[19px] font-bold text-ink leading-snug">
                    {{ __('webapp.ghost_invite.'.$status.'_title') }}
                </p>
                <p class="mt-2 text-[14px] text-ink/60 leading-relaxed">
                    {{ __('webapp.ghost_invite.'.$status.'_body') }}
                </p>
            @endif

        </div>
    </div>
</div>
@endsection
