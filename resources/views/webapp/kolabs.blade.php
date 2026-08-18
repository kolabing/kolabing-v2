@extends('webapp.layout')
{{-- Reused by /applications, which lands straight on the Requests tab. --}}
@php $startTab = $initialTab ?? 'offers'; @endphp
@section('title', $startTab === 'requests' ? __('webapp.applications.title') : __('webapp.kolabs.title'))

@section('body')
{{-- @js (not @json) — it emits single quotes, so the value cannot terminate this
     double-quoted attribute and truncate the Alpine expression. --}}
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), myKolabsPage(@js($startTab)))" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'kolabs'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        <h1 class="font-anton text-[28px] tracking-[1px] text-ink">{{ __('webapp.kolabs.title') }}</h1>

        {{-- ── Primary tabs ────────────────────────────────────────────── --}}
        <div class="flex p-1 bg-white border border-ink/[.12] rounded-pill shadow-card mt-[18px] overflow-x-auto">
            <template x-for="tb in tabs" :key="tb.value">
                <button type="button" @click="setTab(tb.value)"
                        class="flex-1 min-w-[84px] h-[34px] rounded-pill text-[12.5px] font-bold tracking-[.4px] transition whitespace-nowrap"
                        :class="tab === tb.value ? 'bg-ink text-white' : 'text-muted'"
                        x-text="tb.label"></button>
            </template>
        </div>

        <template x-if="error">
            <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>
        <template x-if="loading">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        {{-- ══ OFFERS ══════════════════════════════════════════════════ --}}
        <template x-if="!loading && tab === 'offers'">
            <div class="flex flex-col gap-2.5 mt-5">
                <template x-for="of in offers" :key="of.id">
                    <div class="bg-white border border-ink/[.08] rounded-2xl p-[18px] shadow-card hover:border-ink/25 transition">
                        <div class="flex items-start sm:items-center gap-3.5 flex-col sm:flex-row">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <a :href="kbPath('/kolabs/' + of.id)" class="text-[15px] font-bold text-ink hover:underline" x-text="of.title"></a>
                                    <span class="px-2.5 py-[3px] rounded-xl text-[11px] font-bold tracking-[.4px]"
                                          :style="`background:${statusPill(of.status).bg};color:${statusPill(of.status).c}`"
                                          x-text="statusPill(of.status).label"></span>
                                </div>
                                <p class="text-[13px] text-muted mt-1" x-text="offerMeta(of)"></p>
                            </div>
                            <div class="flex gap-2 shrink-0 flex-wrap">
                                <template x-if="of.status === 'draft'">
                                    <button type="button" @click="publish(of)" :disabled="busy"
                                            class="kb-on-yellow h-[38px] px-[18px] rounded-pill bg-primary text-ink text-[13px] font-bold hover:bg-primary-dark transition disabled:opacity-50">{{ __('webapp.kolabs.publish') }}</button>
                                </template>
                                <template x-if="of.status === 'published'">
                                    <button type="button" @click="closeKolab(of)" :disabled="busy"
                                            class="h-[38px] px-[18px] rounded-pill bg-white border border-line text-ink text-[13px] font-bold hover:border-ink transition">{{ __('webapp.kolabs.close') }}</button>
                                </template>
                                <a :href="kbPath('/kolabs/' + of.id + '/edit')"
                                   class="h-[38px] px-[18px] rounded-pill bg-white border border-line text-ink text-[13px] font-bold hover:border-ink transition flex items-center">{{ __('webapp.common.edit') }}</a>
                                <button type="button" @click="destroy(of)" :disabled="busy"
                                        class="h-[38px] px-3 rounded-pill text-danger text-[13px] font-bold hover:bg-bad-surface transition">{{ __('webapp.common.delete') }}</button>
                            </div>
                        </div>
                    </div>
                </template>

                <a href="{{ $base }}/kolabs/create"
                   class="flex items-center justify-center gap-2 p-[18px] rounded-2xl border-[1.5px] border-dashed border-ink/20 text-body text-[13px] font-semibold hover:border-ink hover:text-ink transition">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    {{ __('webapp.kolabs.create_new') }}
                </a>
            </div>
        </template>

        {{-- ══ REQUESTS ════════════════════════════════════════════════ --}}
        <template x-if="!loading && tab === 'requests'">
            <div class="mt-5">
                <div class="flex justify-center">
                    <div class="flex p-1 bg-white border border-ink/[.12] rounded-pill">
                        <button type="button" @click="setReqSub('sent')"
                                class="min-w-[100px] h-8 rounded-pill text-[12.5px] font-bold tracking-[.4px] transition"
                                :class="kb-on-yellow reqSub === 'sent' ? 'kb-on-yellow bg-primary text-ink' : 'text-muted'">{{ __('webapp.applications.tab_sent') }}</button>
                        <button type="button" @click="setReqSub('received')"
                                class="min-w-[100px] h-8 rounded-pill text-[12.5px] font-bold tracking-[.4px] transition"
                                :class="kb-on-yellow reqSub === 'received' ? 'kb-on-yellow bg-primary text-ink' : 'text-muted'">{{ __('webapp.applications.tab_received') }}</button>
                    </div>
                </div>

                <template x-if="requests.length === 0">
                    <div class="mt-[18px] rounded-2xl border-[1.5px] border-dashed border-ink/20 py-12 text-center text-sm text-muted"
                         x-text="reqSub === 'sent' ? t('applications.empty_sent') : t('applications.empty_received')"></div>
                </template>

                <div class="flex flex-col gap-2.5 mt-[18px]">
                    <template x-for="rq in requests" :key="rq.id">
                        <div class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-primary/40 flex items-center justify-center text-[15px] font-semibold text-ink shrink-0"
                                     x-text="initialOf(partyName(rq))"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-ink truncate" x-text="partyName(rq)"></p>
                                    <p class="text-[12.5px] text-muted mt-px truncate" x-text="requestMeta(rq)"></p>
                                </div>
                                <span class="px-3 py-1 rounded-xl text-[11px] font-bold tracking-[.4px] shrink-0"
                                      :style="`background:${statusPill(rq.status).bg};color:${statusPill(rq.status).c}`"
                                      x-text="statusPill(rq.status).label"></span>
                            </div>

                            <template x-if="rq.message">
                                <p class="mt-2.5 px-3 py-2.5 rounded-xl bg-cream-low text-[13px] text-body leading-normal" x-text="'“' + rq.message + '”'"></p>
                            </template>
                            <template x-if="rq.availability">
                                <p class="mt-1.5 text-[12px] text-muted"><span class="font-semibold">{{ __('webapp.applications.availability') }}</span> <span x-text="rq.availability"></span></p>
                            </template>

                            {{-- Received + pending → accept (needs a date) / decline --}}
                            <template x-if="reqSub === 'received' && rq.status === 'pending'">
                                <div class="mt-2.5">
                                    <template x-if="acceptingId !== rq.id">
                                        <div class="flex gap-2">
                                            <button type="button" @click="startAccept(rq)"
                                                    class="kb-on-yellow flex-1 h-[38px] rounded-pill bg-primary text-ink text-[13px] font-bold hover:bg-primary-dark transition">{{ __('webapp.applications.accept') }}</button>
                                            <button type="button" @click="decline(rq)" :disabled="busy"
                                                    class="flex-1 h-[38px] rounded-pill bg-white border border-line text-ink text-[13px] font-bold hover:border-ink transition disabled:opacity-50">{{ __('webapp.applications.decline') }}</button>
                                        </div>
                                    </template>
                                    <template x-if="acceptingId === rq.id">
                                        <div class="rounded-xl bg-cream-low p-3">
                                            <div class="flex flex-wrap items-end gap-2">
                                                <div class="flex-1 min-w-[160px]">
                                                    <label class="text-[11px] font-semibold text-body block">{{ __('webapp.applications.scheduled_date') }}</label>
                                                    {{-- The API only accepts a date inside the Kolab window that falls on one
                                                         of its recurring days, so the picker is bounded to that window. --}}
                                                    <input x-model="scheduledDate" type="date" :min="dateBounds(rq).min" :max="dateBounds(rq).max"
                                                           class="mt-1 h-10 w-full rounded-xl border border-transparent bg-white px-3 text-sm text-ink">
                                                </div>
                                                <button type="button" @click="confirmAccept(rq)" :disabled="busy || !scheduledDate"
                                                        class="h-10 px-4 rounded-pill bg-inverse text-on-inverse text-[13px] font-bold disabled:opacity-50">{{ __('webapp.applications.confirm') }}</button>
                                                <button type="button" @click="acceptingId = null"
                                                        class="h-10 px-4 rounded-pill bg-white border border-line text-[13px] font-bold">{{ __('webapp.common.cancel') }}</button>
                                            </div>
                                            <p x-show="allowedDaysLabel(rq)" x-cloak class="text-[11px] text-muted mt-2"
                                               x-text="t('applications.allowed_days', { days: allowedDaysLabel(rq) })"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- Sent + pending → withdraw --}}
                            <template x-if="reqSub === 'sent' && rq.status === 'pending'">
                                <div class="mt-2.5">
                                    <button type="button" @click="withdraw(rq)" :disabled="busy"
                                            class="h-[38px] px-4 rounded-pill bg-white border border-line text-danger text-[13px] font-bold hover:border-danger transition disabled:opacity-50">{{ __('webapp.applications.withdraw') }}</button>
                                </div>
                            </template>

                            <template x-if="rq.status === 'accepted'">
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <p class="text-[12px] text-ok-ink">{{ __('webapp.applications.accepted_note') }}</p>
                                    <a :href="window.kbPath('/chats') + '?application=' + rq.id"
                                       class="shrink-0 h-[38px] px-4 rounded-pill bg-white border border-line text-[13px] font-bold hover:border-ink transition flex items-center gap-1.5">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                        {{ __('webapp.chats.title') }}
                                    </a>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- ══ ACTIVE / FINISHED ═══════════════════════════════════════ --}}
        <template x-if="!loading && (tab === 'active' || tab === 'finished')">
            <div class="mt-5">
                <template x-if="collabs.length === 0">
                    <div class="rounded-2xl border-[1.5px] border-dashed border-ink/20 py-12 text-center text-sm text-muted"
                         x-text="tab === 'active' ? t('kolabs.empty_active') : t('kolabs.empty_finished')"></div>
                </template>
                <div class="flex flex-col gap-2.5">
                    <template x-for="cl in collabs" :key="cl.id">
                        <div class="flex items-center gap-3 bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card hover:border-ink/25 transition">
                            <div class="w-10 h-10 rounded-full bg-primary/40 flex items-center justify-center text-[15px] font-semibold text-ink shrink-0"
                                 x-text="initialOf(collabPartner(cl).name)"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-ink truncate" x-text="collabPartner(cl).name"></p>
                                <p class="text-[13px] text-body mt-px truncate" x-text="cl.kolab?.title || ''"></p>
                                <span x-show="cl.scheduled_date" x-cloak
                                      class="inline-block mt-[7px] px-2 py-[3px] rounded-md bg-cream-input text-[11px] font-medium text-body"
                                      x-text="fmtDate(cl.scheduled_date)"></span>
                            </div>
                            <span class="px-3 py-1 rounded-xl text-[11px] font-bold tracking-[.4px] shrink-0"
                                  :style="`background:${statusPill(cl.status).bg};color:${statusPill(cl.status).c}`"
                                  x-text="statusPill(cl.status).label"></span>
                            <a :href="window.kbPath('/chats') + '?collaboration=' + cl.id"
                               class="shrink-0 w-9 h-9 rounded-full bg-cream-low hover:bg-cream-low-hover transition flex items-center justify-center text-ink"
                               :aria-label="t('chats.title')" :title="t('chats.title')">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </a>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
    </main>
</div>

@push('scripts')
<script>
    function myKolabsPage(startTab) {
        return {
            tab: startTab || 'offers', reqSub: 'sent',
            offers: [], requests: [], collabs: [], allCollabs: [],
            loading: true, busy: false, error: '',
            acceptingId: null, scheduledDate: '',
            // The API requires `after:today`, so tomorrow is the earliest legal date.
            minDate: window.kbDayOffset(1),

            get tabs() {
                return [
                    { value: 'offers', label: t('kolabs.tab_offers') },
                    { value: 'requests', label: t('kolabs.tab_requests') },
                    { value: 'active', label: t('kolabs.tab_active') },
                    { value: 'finished', label: t('kolabs.tab_finished') },
                ];
            },
            statusPill(s) { return window.kbStatus(s); },
            fmtDate(v) { return window.kbDate(v); },
            initialOf(v) { return window.kbInitial(v); },
            kbPath(p) { return window.kbPath(p); },

            offerMeta(of) {
                const n = of.applications_count || 0;
                const count = t(n === 1 ? 'kolabs.application_count' : 'kolabs.applications_count', { count: n });
                const from = window.kbDateShort(of.availability_start);
                const to = window.kbDateShort(of.availability_end);
                const win = from && to ? `${from} – ${to}` : (from || t('kolabs.no_window'));
                return `${count} · ${win}`;
            },
            /** The other party on an application row. */
            partyName(rq) {
                if (this.reqSub === 'received') return rq.applicant_profile?.display_name || t('applications.a_community');
                return rq.kolab?.creator_profile?.display_name || rq.opportunity?.creator_profile?.display_name || t('feed.a_partner');
            },
            requestMeta(rq) {
                const title = rq.kolab?.title || rq.opportunity?.title || t('intent.kolab');
                return `${title} · ${window.kbDate(rq.created_at)}`;
            },
            collabPartner(cl) {
                const mine = this.me?.id;
                const other = cl.creator_profile?.id === mine ? cl.applicant_profile : cl.creator_profile;
                return { name: other?.display_name || t('dashboard.partner') };
            },

            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await this.loadShell();
                if (!me) return;
                const params = new URLSearchParams(location.search);
                const wanted = params.get('tab');
                if (['offers', 'requests', 'active', 'finished'].includes(wanted)) this.tab = wanted;
                // Businesses land on the applications they received; communities on the ones they sent.
                this.reqSub = this.isBusiness ? 'received' : 'sent';
                await this.load();
            },
            setTab(tab) { if (this.tab === tab) return; this.tab = tab; this.error = ''; this.load(); },
            setReqSub(sub) { if (this.reqSub === sub) return; this.reqSub = sub; this.loadRequests(); },

            async load() {
                this.loading = true;
                if (this.tab === 'offers') await this.loadOffers();
                else if (this.tab === 'requests') await this.loadRequests();
                else await this.loadCollabs();
                this.loading = false;
            },
            async loadOffers() {
                const res = await window.kb.api('/kolabs/me?per_page=50');
                if (res.ok) this.offers = window.kb.rows(res);
                else this.error = window.kb.errorText(res, t('kolabs.load_error'));
            },
            async loadRequests() {
                this.acceptingId = null;
                const path = this.reqSub === 'received' ? '/me/received-applications' : '/me/applications';
                const res = await window.kb.api(path + '?per_page=50');
                if (res.ok) this.requests = window.kb.rows(res);
                else this.error = window.kb.errorText(res, t('applications.load_error'));
            },
            async loadCollabs() {
                if (this.allCollabs.length === 0) {
                    // One fetch covers both tabs — the API filters on a single status only.
                    const res = await window.kb.api('/collaborations?per_page=100');
                    if (res.ok) this.allCollabs = window.kb.rows(res);
                    else { this.error = window.kb.errorText(res, t('kolabs.load_error')); return; }
                }
                const live = ['scheduled', 'active', 'pending_confirmation'];
                this.collabs = this.tab === 'active'
                    ? this.allCollabs.filter(c => live.includes(c.status))
                    : this.allCollabs.filter(c => !live.includes(c.status));
            },

            async publish(of) {
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/kolabs/' + of.id + '/publish', { method: 'POST' });
                this.busy = false;
                if (res.status === 402) { window.nav('/subscription?reason=publish'); return; }
                if (res.ok) of.status = 'published';
                else this.error = window.kb.errorText(res, t('kolabs.publish_error'));
            },
            async closeKolab(of) {
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/kolabs/' + of.id + '/close', { method: 'POST' });
                this.busy = false;
                if (res.ok) of.status = 'closed';
                else this.error = window.kb.errorText(res, t('kolabs.close_error'));
            },
            async destroy(of) {
                if (!confirm(t('kolabs.delete_confirm'))) return;
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/kolabs/' + of.id, { method: 'DELETE' });
                this.busy = false;
                if (res.ok) this.offers = this.offers.filter(i => i.id !== of.id);
                else this.error = window.kb.errorText(res, t('kolabs.delete_error'));
            },

            startAccept(rq) { this.acceptingId = rq.id; this.scheduledDate = ''; this.error = ''; },
            /** The Kolab attached to an application (either resource key). */
            reqKolab(rq) { return rq.kolab || rq.opportunity || {}; },
            dateBounds(rq) {
                const k = this.reqKolab(rq);
                // Never before tomorrow — the API requires `after:today`.
                const min = [k.availability_start, this.minDate].filter(Boolean).sort().pop();
                return { min, max: k.availability_end || null };
            },
            allowedDaysLabel(rq) {
                const days = this.reqKolab(rq).recurring_days;
                if (!Array.isArray(days) || !days.length) return '';
                const labels = t('detail.day_initials').split(',');
                return days.slice().sort((a, b) => a - b).map(d => labels[d - 1]).join(', ');
            },
            async confirmAccept(rq) {
                this.error = '';
                // Mirror the API rule up front so the picker explains itself.
                const days = this.reqKolab(rq).recurring_days;
                if (Array.isArray(days) && days.length) {
                    const picked = new Date(this.scheduledDate + 'T00:00:00');
                    const dow = picked.getDay() === 0 ? 7 : picked.getDay();
                    if (!days.includes(dow)) {
                        this.error = t('applications.day_mismatch', { days: this.allowedDaysLabel(rq) });
                        return;
                    }
                }
                this.busy = true;
                const res = await window.kb.api('/applications/' + rq.id + '/accept', {
                    method: 'POST', body: { scheduled_date: this.scheduledDate },
                });
                this.busy = false;
                if (res.ok) { rq.status = 'accepted'; this.acceptingId = null; this.allCollabs = []; return; }
                // Accepting is subscription-gated for businesses; the policy denial
                // surfaces as 403 (not 402), so route unsubscribed businesses to the plan.
                if (res.status === 402 || (res.status === 403 && this.needsPlan)) { window.nav('/subscription?reason=accept'); return; }
                this.error = window.kb.errorText(res, t('applications.accept_error'));
            },
            async decline(rq) {
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/applications/' + rq.id + '/decline', { method: 'POST', body: {} });
                this.busy = false;
                if (res.ok) rq.status = 'declined';
                else this.error = window.kb.errorText(res, t('applications.decline_error'));
            },
            async withdraw(rq) {
                if (!confirm(t('applications.withdraw_confirm'))) return;
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/applications/' + rq.id + '/withdraw', { method: 'POST' });
                this.busy = false;
                if (res.ok) rq.status = 'withdrawn';
                else this.error = window.kb.errorText(res, t('applications.withdraw_error'));
            },
        };
    }
</script>
@endpush
@endsection
