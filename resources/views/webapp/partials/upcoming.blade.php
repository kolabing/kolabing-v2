{{-- "Upcoming Kolabs" list — shared by both dashboard variants.
     Expects the host component to expose `upcoming`, `statusPill()`, `fmtDate()`, `initialOf()`. --}}
<div>
    <p class="text-[13px] font-semibold tracking-[1px] uppercase text-ink mb-3">{{ __('webapp.dashboard.upcoming') }}</p>

    <template x-if="upcoming.length === 0">
        <div class="rounded-2xl border-[1.5px] border-dashed border-ink/20 py-10 px-5 text-center text-sm text-muted">
            {{ __('webapp.dashboard.upcoming_empty') }}
            <a href="{{ $base }}/feed" class="font-semibold text-ink underline ml-1">{{ __('webapp.dashboard.find_one') }}</a>
        </div>
    </template>

    <div class="flex flex-col gap-2.5">
        <template x-for="c in upcoming" :key="c.id">
            <div class="flex items-center gap-3 bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card hover:border-ink/25 transition">
                <div class="w-10 h-10 rounded-full bg-primary/40 flex items-center justify-center text-[15px] font-semibold text-ink shrink-0"
                     x-text="initialOf(c.partner?.name)"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-ink truncate" x-text="c.partner?.name || t('dashboard.partner')"></p>
                    <p class="text-[13px] text-body mt-px truncate" x-text="c.kolab?.title || c.opportunity?.title || ''"></p>
                    <span x-show="c.scheduled_date" x-cloak
                          class="inline-block mt-[7px] px-2 py-[3px] rounded-md bg-cream-input text-[11px] font-medium text-body"
                          x-text="fmtDate(c.scheduled_date)"></span>
                </div>
                <span class="px-3 py-1 rounded-xl text-[11px] font-bold tracking-[.4px] shrink-0"
                      :style="`background:${statusPill(c.status).bg};color:${statusPill(c.status).c}`"
                      x-text="statusPill(c.status).label"></span>
            </div>
        </template>
    </div>
</div>
