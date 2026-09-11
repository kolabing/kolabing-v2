@extends('webapp.layout')
@section('title', __('webapp.mke.title'))

@section('body')
{{--
    Multi-Kolab events the viewer created.

    A multi-Kolab event is one organizer recruiting SEVERAL partners into one date,
    which is why it gets its own surface instead of sitting in My Kolabs: the thing
    you come back to is not one agreement, it is a board of roles at different
    stages. So a row leads with the status and the roles filled — the two numbers
    that decide whether there is anything to do today — and the date, which decides
    whether it is still possible.

    Only the roles are counted here. Applications per role are on the detail page,
    because a list that showed them would print four numbers per row and answer
    nothing.
--}}
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), multiKolabEventsPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'multi-kolab-events'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[820px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h1 class="font-anton text-[30px] md:text-[34px] leading-none tracking-[1px] text-ink">{{ __('webapp.mke.title') }}</h1>
                <p class="text-sm text-muted mt-2">{{ __('webapp.mke.lede') }}</p>
            </div>
        </div>

        <template x-if="error">
            <div class="mt-6 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>

        <p x-show="loading" x-cloak class="mt-8 text-sm text-muted">{{ __('webapp.common.loading') }}</p>

        {{-- Someone without the entitlement has no events and cannot make one, so
             the page says why rather than showing an empty list with no way out. --}}
        <template x-if="!loading && !canCreateEvents && events.length === 0">
            <div class="mt-8 rounded-2xl border-[1.5px] border-dashed border-ink/20 py-12 px-6 text-center">
                <p class="text-[15px] font-bold text-ink">{{ __('webapp.mke.no_entitlement_title') }}</p>
                <p class="text-sm text-muted mt-2 max-w-[440px] mx-auto leading-relaxed">{{ __('webapp.mke.no_entitlement_body') }}</p>
                <a href="{{ $base }}/feed" class="mt-5 inline-flex h-10 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition items-center">{{ __('webapp.mke.no_entitlement_cta') }}</a>
            </div>
        </template>

        <template x-if="!loading && canCreateEvents && events.length === 0">
            <div class="mt-8 rounded-2xl border-[1.5px] border-dashed border-ink/20 py-12 px-6 text-center">
                <p class="text-[15px] font-bold text-ink">{{ __('webapp.mke.empty_title') }}</p>
                <p class="text-sm text-muted mt-2 max-w-[440px] mx-auto leading-relaxed">{{ __('webapp.mke.empty_body') }}</p>
            </div>
        </template>

        <div class="mt-7 flex flex-col gap-3">
            <template x-for="e in events" :key="e.id">
                <a :href="window.kbPath('/multi-kolab-events/' + e.id)"
                   class="block bg-white border border-ink/[.08] rounded-2xl p-5 shadow-card hover:border-ink/25 hover:-translate-y-px transition">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[16px] font-bold text-ink truncate" x-text="e.title"></p>
                            <p class="text-[13px] text-body mt-1 line-clamp-2" x-show="e.value_summary" x-cloak x-text="e.value_summary"></p>
                        </div>
                        <span class="px-3 py-1 rounded-xl text-[11px] font-bold tracking-[.4px] shrink-0"
                              :style="`background:${statusPill(e.status).bg};color:${statusPill(e.status).c}`"
                              x-text="statusPill(e.status).label"></span>
                    </div>

                    <div class="mt-4 flex items-center gap-2 flex-wrap">
                        {{-- Roles filled is the one number that says how far along
                             recruiting is. Printed as a fraction because "3" alone
                             means nothing without the target. --}}
                        <span class="px-2.5 py-[5px] rounded-lg bg-cream-input text-[12px] font-semibold text-body"
                              x-text="rolesLabel(e.role_counts)"></span>
                        <span class="px-2.5 py-[5px] rounded-lg bg-cream-input text-[12px] font-medium text-body"
                              x-show="e.event_date" x-cloak x-text="window.kbDate(e.event_date)"></span>
                        <span class="px-2.5 py-[5px] rounded-lg bg-cream-input text-[12px] font-medium text-body"
                              x-show="e.city" x-cloak x-text="e.city"></span>
                    </div>
                </a>
            </template>
        </div>

        <div class="mt-6 flex justify-center" x-show="hasMore" x-cloak>
            <button type="button" @click="loadMore()" :disabled="loadingMore"
                    class="h-10 px-5 rounded-pill bg-white border border-line text-sm font-bold hover:border-ink transition disabled:opacity-50"
                    x-text="loadingMore ? t('common.loading') : t('common.load_more')"></button>
        </div>

    </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    function multiKolabEventsPage() {
        return {
            t: (key, params) => window.t(key, params),

            events: [],
            page: 1,
            hasMore: false,
            loading: true,
            loadingMore: false,
            error: '',

            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await this.loadShell();
                if (!me) return;
                await this.load();
            },

            async load() {
                this.loading = true;
                const res = await window.kb.api('/multi-kolab-events/me?per_page=15');
                this.loading = false;

                if (!res.ok) {
                    this.error = window.kb.errorText(res, window.t('mke.load_error'));
                    return;
                }

                this.events = window.kb.rows(res);
                this.hasMore = this.pageHasMore(res);
            },

            async loadMore() {
                this.loadingMore = true;
                const res = await window.kb.api('/multi-kolab-events/me?per_page=15&page=' + (this.page + 1));
                this.loadingMore = false;

                if (!res.ok) {
                    this.error = window.kb.errorText(res, window.t('mke.load_error'));
                    return;
                }

                this.page += 1;
                this.events = [...this.events, ...window.kb.rows(res)];
                this.hasMore = this.pageHasMore(res);
            },

            pageHasMore(res) {
                const meta = window.kb.meta(res);
                if (!meta) return false;
                const current = meta.current_page ?? this.page;
                const last = meta.last_page ?? meta.total_pages ?? current;
                return current < last;
            },

            /** "3 / 5 roles filled" — a fraction, because the numerator alone is mute. */
            rolesLabel(counts) {
                const filled = counts?.filled ?? 0;
                const total = counts?.total ?? 0;
                return window.t('mke.roles_filled', { filled, total });
            },

            /* The panel's one status-pill helper — a multi-Kolab status should not
               look different from any other status the panel prints. */
            statusPill(status) {
                return window.kbStatus(status);
            },
        };
    }
</script>
@endpush
