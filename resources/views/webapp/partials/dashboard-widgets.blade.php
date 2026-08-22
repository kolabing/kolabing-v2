{{-- Dashboard widgets shared by both roles. The host component supplies
     `recommended`, `activity`, `suggestionTop`, `suggestionCountLabel`,
     `statusPill()`, `fmtDate()`, `initialOf()`. --}}

@if (config('suggestions.enabled'))
{{-- ── Suggested partners (BE-NF-39) ──────────────────────────────────────
     The entry point to /suggestions: the top card plus how many are waiting.

     Two ways this renders nothing at all, and both matter. The flag is checked
     here in Blade, so with suggestions off there is no markup and no request —
     the same gate the sidebar entry and the route itself use. And `suggestionTop`
     is null until there is a real suggestion to name, so an empty list shows
     nothing rather than a block advertising "0 suggestions", which would be
     worse than no block.

     Nothing here reads the counterpart's `id`: a blurred card (a free business)
     carries none, and the web app has no profile page to link to either way. The
     whole block goes to /suggestions. --}}
<div x-show="!loadingExtras && suggestionTop" x-cloak>
    <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-[13px] font-semibold tracking-[1px] uppercase text-ink">{{ __('webapp.nav.suggestions') }}</p>
        <a href="{{ $base }}/suggestions" class="text-[12.5px] font-semibold text-body hover:text-ink">{{ __('webapp.dashboard.see_all') }}</a>
    </div>
    <a href="{{ $base }}/suggestions"
       class="block bg-white border border-ink/[.08] rounded-2xl shadow-card p-4 hover:-translate-y-0.5 hover:shadow-cardhover hover:border-ink/20 transition">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-full bg-primary/35 flex items-center justify-center overflow-hidden shrink-0 text-[14px] font-semibold text-ink">
                {{-- Held back, never substituted: a blurred card has no name and
                     no avatar, so the placeholder is visibly withheld. --}}
                <template x-if="suggestionTop?.blurred">
                    <span class="w-full h-full bg-primary/60 blur-sm select-none" aria-hidden="true"></span>
                </template>
                <template x-if="!suggestionTop?.blurred && suggestionTop?.avatar">
                    <img :src="suggestionTop?.avatar" :alt="suggestionTop?.name" class="w-full h-full object-cover">
                </template>
                <template x-if="!suggestionTop?.blurred && !suggestionTop?.avatar">
                    <span x-text="suggestionTop?.initial">&nbsp;</span>
                </template>
            </span>
            <span class="flex-1 min-w-0">
                <template x-if="suggestionTop?.blurred">
                    <span class="block text-[13px] font-bold text-ink blur-sm select-none" aria-hidden="true">●●●●●●●●●</span>
                </template>
                <template x-if="!suggestionTop?.blurred">
                    <span class="block text-[13px] font-bold text-ink truncate" x-text="suggestionTop?.name">&nbsp;</span>
                </template>
                <span class="block text-[12px] text-muted truncate" x-text="suggestionCountLabel"></span>
            </span>
            <span class="px-2.5 py-1 rounded-pill bg-ink text-primary text-[11px] font-bold shrink-0" x-text="suggestionTop?.scoreLabel"></span>
        </div>
        {{-- One reason, already a sentence in the reader's locale. Absent when the
             API dropped every signal, rather than an empty line. --}}
        <p class="text-[12px] text-body mt-2.5 line-clamp-2" x-show="suggestionTop?.reason" x-cloak x-text="suggestionTop?.reason"></p>
    </a>
</div>
@endif

{{-- ── Recommended for you ────────────────────────────────────────────────
     Straight from /discovery/opportunities, so the match score is the real
     server-computed one — the same number Explore shows. --}}
<div x-show="!loadingExtras && recommended.length" x-cloak>
    <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-[13px] font-semibold tracking-[1px] uppercase text-ink" x-text="recommendedTitle"></p>
        <a href="{{ $base }}/feed" class="text-[12.5px] font-semibold text-body hover:text-ink">{{ __('webapp.dashboard.see_all') }}</a>
    </div>
    <div class="grid sm:grid-cols-3 gap-3">
        <template x-for="r in recommended" :key="r.id">
            <a :href="kbPath('/kolabs/' + r.id)"
               class="group bg-white border border-ink/[.08] rounded-2xl overflow-hidden shadow-card hover:-translate-y-0.5 hover:shadow-cardhover hover:border-ink/20 transition flex flex-col">
                <div class="relative bg-cream-input" style="aspect-ratio: 16/9;">
                    <template x-if="r.img">
                        <img :src="r.img" alt="" class="w-full h-full object-cover block">
                    </template>
                    <template x-if="!r.img">
                        <div class="w-full h-full flex items-center justify-center text-muted text-xs font-medium" x-text="r.meta"></div>
                    </template>
                    <span x-show="r.match > 0" x-cloak
                          class="absolute top-2 right-2 px-2 py-1 rounded-pill bg-ink text-white text-[10px] font-bold"
                          x-text="t('feed.match', { pct: r.match })"></span>
                </div>
                <div class="p-3 flex-1 flex flex-col">
                    <p class="text-[13px] font-bold text-ink truncate" x-text="r.name"></p>
                    <p class="text-[12px] text-body mt-1 line-clamp-2 flex-1" x-text="r.offer"></p>
                    <span x-show="r.city" x-cloak class="text-[11px] text-muted mt-2" x-text="r.city"></span>
                </div>
            </a>
        </template>
    </div>
</div>

{{-- ── Recent activity ──────────────────────────────────────────────────── --}}
<div x-show="!loadingExtras && activity.length" x-cloak>
    <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-[13px] font-semibold tracking-[1px] uppercase text-ink">{{ __('webapp.dashboard.activity') }}</p>
        <a href="{{ $base }}/notifications" class="text-[12.5px] font-semibold text-body hover:text-ink">{{ __('webapp.dashboard.see_all') }}</a>
    </div>
    <div class="bg-white border border-ink/[.08] rounded-2xl overflow-hidden shadow-card">
        <template x-for="(n, i) in activity" :key="n.id">
            <a :href="kbPath('/notifications')"
               class="flex items-start gap-3 px-4 py-3 hover:bg-cream-low transition"
               :class="i > 0 ? 'border-t border-ink/[.06]' : ''">
                <span class="w-8 h-8 rounded-full bg-primary/40 flex items-center justify-center text-[13px] font-semibold text-ink shrink-0"
                      x-text="initialOf(n.actor_name || n.title)"></span>
                <span class="flex-1 min-w-0">
                    <span class="block text-[13px] font-semibold text-ink truncate" x-text="n.title"></span>
                    <span class="block text-[12px] text-muted truncate" x-text="n.body"></span>
                </span>
                <span class="flex items-center gap-2 shrink-0">
                    <span class="text-[11px] text-muted" x-text="ago(n.created_at)"></span>
                    <span x-show="!n.is_read" x-cloak class="w-2 h-2 rounded-full bg-accent"></span>
                </span>
            </a>
        </template>
    </div>
</div>
