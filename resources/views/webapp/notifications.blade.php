@extends('webapp.layout')
@section('title', __('webapp.nav.notifications'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), notificationsPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'notifications'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[640px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        <div class="flex items-center justify-between gap-3">
            <h1 class="font-anton text-[28px] tracking-[1px] text-ink">{{ __('webapp.nav.notifications') }}</h1>
            <button type="button" @click="markAll()" x-show="unread > 0" x-cloak :disabled="busy"
                    class="h-9 px-4 rounded-pill bg-white border border-line text-[13px] font-bold hover:border-ink transition disabled:opacity-50">{{ __('webapp.notifications.mark_all') }}</button>
        </div>

        <template x-if="error">
            <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>
        <template x-if="loading">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>
        <template x-if="!loading && items.length === 0 && !error">
            <div class="mt-8 rounded-2xl border-[1.5px] border-dashed border-ink/20 py-12 text-center text-sm text-muted">{{ __('webapp.notifications.empty') }}</div>
        </template>

        <div class="flex flex-col gap-2 mt-5">
            <template x-for="nt in items" :key="nt.id">
                <div @click="open(nt)"
                     class="flex items-start gap-3 border border-ink/[.08] rounded-2xl px-4 py-3.5 hover:border-ink/25 transition cursor-pointer"
                     :class="nt.is_read ? 'bg-white/60' : 'bg-white'">
                    <div class="w-9 h-9 rounded-full bg-primary/40 flex items-center justify-center overflow-hidden text-sm font-semibold text-ink shrink-0">
                        <template x-if="nt.actor_avatar_url"><img :src="nt.actor_avatar_url" alt="" class="w-full h-full object-cover"></template>
                        <template x-if="!nt.actor_avatar_url"><span x-text="initialOf(nt.actor_name || nt.title)"></span></template>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13.5px] font-semibold text-ink" x-text="nt.title"></p>
                        <p class="text-[13px] text-body mt-0.5 leading-snug" x-text="nt.body"></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[11px] text-muted" x-text="ago(nt.created_at)"></span>
                        <span x-show="!nt.is_read" x-cloak class="w-2 h-2 rounded-full bg-accent"></span>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-8 text-center" x-show="!loading && page < lastPage" x-cloak>
            <button type="button" @click="load(page + 1)"
                    class="h-11 px-6 rounded-pill bg-white border border-line text-sm font-bold hover:border-ink transition">{{ __('webapp.feed.load_more') }}</button>
        </div>
    </div>
    </main>
</div>

@push('scripts')
<script>
    function notificationsPage() {
        return {
            items: [], loading: true, busy: false, error: '', page: 1, lastPage: 1,
            initialOf(v) { return window.kbInitial(v); },
            ago(iso) {
                if (!iso) return '';
                const secs = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
                if (secs < 3600) return t('notifications.minutes', { n: Math.max(1, Math.floor(secs / 60)) });
                if (secs < 86400) return t('notifications.hours', { n: Math.floor(secs / 3600) });
                if (secs < 604800) return t('notifications.days', { n: Math.floor(secs / 86400) });
                return window.kbDateShort(iso);
            },
            async init() {
                if (!window.kb.requireAuth()) return;
                if (!await this.loadShell()) return;
                await this.load(1);
            },
            async load(page) {
                this.loading = true; this.error = '';
                const res = await window.kb.api('/me/notifications?per_page=20&page=' + page);
                if (res.ok) {
                    const rows = window.kb.rows(res);
                    this.items = page === 1 ? rows : this.items.concat(rows);
                    this.page = window.kb.meta(res).current_page || page;
                    this.lastPage = window.kb.meta(res).last_page || this.page;
                } else {
                    this.error = window.kb.errorText(res, t('notifications.load_error'));
                }
                this.loading = false;
            },
            /** Mark read, then follow the notification to whatever it points at. */
            async open(nt) {
                if (!nt.is_read) {
                    const res = await window.kb.api('/me/notifications/' + nt.id + '/read', { method: 'POST' });
                    if (res.ok) { nt.is_read = true; this.unread = Math.max(0, this.unread - 1); }
                }
                const target = this.targetPath(nt);
                if (target) window.nav(target);
            },
            targetPath(nt) {
                const type = String(nt.target_type || '').toLowerCase();
                if (!nt.target_id) return null;
                if (type.includes('kolab') || type.includes('opportunity')) return '/kolabs/' + nt.target_id;
                if (type.includes('application')) return '/kolabs?tab=requests';
                if (type.includes('collaboration')) return '/kolabs?tab=active';
                return null;
            },
            async markAll() {
                this.busy = true;
                const res = await window.kb.api('/me/notifications/read-all', { method: 'POST' });
                this.busy = false;
                if (!res.ok) { this.error = window.kb.errorText(res, t('notifications.load_error')); return; }
                this.items.forEach(n => { n.is_read = true; });
                this.unread = 0;
            },
        };
    }
</script>
@endpush
@endsection
