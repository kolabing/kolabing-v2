@php
    $title = $community->name;
    $description = $community->description
        ? \Illuminate\Support\Str::limit(strip_tags($community->description), 155)
        : __('Join :community on Kolabing.', ['community' => $community->name]);
    $canonical = url('/c/'.$community->slug);
    $logo = $community->avatar_url ?: $community->communityProfile?->profile_photo;
    $webapp = rtrim(config('webapp.url'), '/');
    // An invite-only community is not a public destination — do not index it.
    $robots = $isInviteOnly ? 'noindex,nofollow' : 'index,follow,max-image-preview:large';
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical" :robots="$robots" :image="$logo">
    <section class="mx-auto max-w-3xl px-6 py-16 md:py-24">
        <div class="rounded-[2rem] border border-off-black/10 bg-white p-8 shadow-sm md:p-12">

            {{-- Identity --}}
            <div class="flex flex-col items-center text-center">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="h-24 w-24 rounded-full object-cover ring-4 ring-off-black/5">
                @else
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-[#FFE28C] text-3xl font-black uppercase">
                        {{ mb_substr($community->name, 0, 1) }}
                    </div>
                @endif

                <h1 class="mt-6 font-montserrat text-3xl font-black uppercase md:text-4xl">{{ $community->name }}</h1>

                <p class="mt-2 text-sm font-semibold uppercase tracking-[.16em] text-off-black/50">
                    {{ str_replace('_', ' ', (string) $community->type) }}
                    <span aria-hidden="true">·</span>
                    {{ trans_choice('{0}No members yet|{1}:count member|[2,*]:count members', $memberCount, ['count' => $memberCount]) }}
                </p>

                @if ($community->description)
                    <p class="mt-5 max-w-xl text-lg text-off-black/70">{{ $community->description }}</p>
                @endif
            </div>

            {{-- Tier ladder: whatever the leader defined, highest rank first. --}}
            @if ($community->tiers->isNotEmpty())
                <div class="mt-10 border-t border-off-black/10 pt-8">
                    <h2 class="text-xs font-semibold uppercase tracking-[.16em] text-off-black/50">{{ __('Membership levels') }}</h2>
                    <ul class="mt-4 flex flex-wrap gap-2">
                        @foreach ($community->tiers as $tier)
                            <li class="inline-flex items-center gap-2 rounded-full border border-off-black/10 px-4 py-2 text-sm font-semibold">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $tier->color ?: '#FFE28C' }}"></span>
                                {{ $tier->name }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Upcoming public events --}}
            @if ($events->isNotEmpty())
                <div class="mt-8 border-t border-off-black/10 pt-8">
                    <h2 class="text-xs font-semibold uppercase tracking-[.16em] text-off-black/50">{{ __('Upcoming events') }}</h2>
                    <ul class="mt-4 space-y-2">
                        @foreach ($events as $event)
                            <li class="flex items-baseline justify-between gap-4 rounded-2xl bg-off-black/[.03] px-4 py-3">
                                <span class="font-semibold">{{ $event->name }}</span>
                                <span class="shrink-0 text-sm text-off-black/60">{{ $event->event_date->translatedFormat('j M Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- CTA --}}
            <div class="mt-10 border-t border-off-black/10 pt-8" x-data="communityJoinCta()" x-cloak>
                <template x-if="!signedIn">
                    <div class="text-center">
                        <a :href="loginUrl"
                           class="inline-flex h-12 items-center justify-center rounded-full bg-[#FFE28C] px-8 font-bold text-off-black transition hover:-translate-y-px">
                            {{ __('Sign in to join') }}
                        </a>
                        <p class="mt-4 text-sm text-off-black/60">
                            {{ __('New to Kolabing? Get the app to create your account, then open this link again.') }}
                        </p>
                    </div>
                </template>

                <template x-if="signedIn">
                    <div class="text-center">
                        <button type="button" @click="join()" :disabled="busy"
                                class="inline-flex h-12 items-center justify-center rounded-full bg-[#FFE28C] px-8 font-bold text-off-black transition hover:-translate-y-px disabled:opacity-60"
                                x-text="busy ? @js(__('Working…')) : ctaLabel"></button>
                    </div>
                </template>

                <p x-show="error" x-text="error" class="mt-4 text-center text-sm font-semibold text-red-600"></p>
                <p x-show="done" class="mt-4 text-center text-sm font-semibold text-green-700">{{ __('Done — taking you to Kolabing…') }}</p>
            </div>
        </div>
    </section>

    <script>
        function communityJoinCta() {
            return {
                busy: false,
                done: false,
                error: '',
                // The web app stores its bearer token under this key.
                signedIn: !!localStorage.getItem('kolabing_token'),
                communityId: @js($community->id),
                inviteOnly: @js($isInviteOnly),
                invitationToken: @js($invitationToken),
                inviteToken: @js($inviteToken),

                get loginUrl() {
                    return @js($webapp) + '/login?next=' + encodeURIComponent(location.pathname + location.search);
                },

                get ctaLabel() {
                    if (this.invitationToken) return @js(__('Accept invitation'));
                    return this.inviteOnly ? @js(__('Request to join')) : @js(__('Join'));
                },

                get path() {
                    if (this.invitationToken) return '/invitations/accept/' + encodeURIComponent(this.invitationToken);
                    if (this.inviteToken) return '/communities/join/' + encodeURIComponent(this.inviteToken);
                    return this.inviteOnly
                        ? '/communities/' + this.communityId + '/join-requests'
                        : '/communities/' + this.communityId + '/join';
                },

                async join() {
                    this.busy = true;
                    this.error = '';

                    let res;
                    try {
                        res = await fetch(@js(rtrim(config('app.url'), '/')) + '/api/v1' + this.path, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'Authorization': 'Bearer ' + localStorage.getItem('kolabing_token'),
                            },
                        });
                    } catch (e) {
                        this.busy = false;
                        this.error = @js(__('Something went wrong. Please try again.'));
                        return;
                    }

                    this.busy = false;

                    if (res.ok) {
                        this.done = true;
                        location.href = @js($webapp) + '/community';
                        return;
                    }

                    let body = null;
                    try { body = await res.json(); } catch (e) { /* empty */ }
                    this.error = body?.message || @js(__('Something went wrong. Please try again.'));
                },
            };
        }
    </script>
</x-layouts.marketing-page>
