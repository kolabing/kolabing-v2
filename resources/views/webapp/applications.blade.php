@extends('webapp.layout')
@section('title', __('webapp.applications.sent_title'))

@section('body')
<div x-data="applicationsPage()" x-init="init()">
    @include('webapp.partials.nav', ['active' => 'applications'])

    <main class="max-w-2xl mx-auto px-5 py-8">
        <h1 class="font-montserrat font-black text-2xl tracking-tight" x-text="isBusiness ? t('applications.received_title') : t('applications.sent_title')"></h1>

        <template x-if="error"><div class="mt-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div></template>

        <template x-if="loading"><p class="mt-8 text-off-black/50">{{ __('webapp.common.loading') }}</p></template>
        <template x-if="!loading && items.length === 0">
            <p class="mt-8 text-off-black/50" x-text="isBusiness ? t('applications.empty_received') : t('applications.empty_sent')"></p>
        </template>

        <div class="mt-5 space-y-3">
            <template x-for="a in items" :key="a.id">
                <div class="rounded-2xl border border-off-black/10 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a :href="(window.KB_BASE || '') + '/kolabs/' + a.kolab_id" class="font-semibold leading-snug" x-text="a.kolab?.title || t('intent.kolab')"></a>
                            <p class="text-sm text-off-black/60 mt-0.5"
                               x-text="isBusiness ? t('applications.from', { name: (a.applicant_profile?.name || a.applicant_profile?.handle || t('applications.a_community')) }) : t('applications.status_line', { status: statusLabel(a.status) })"></p>
                        </div>
                        <span class="text-[10px] uppercase tracking-wide font-bold px-2 py-0.5 rounded-full whitespace-nowrap" :class="statusClass(a.status)" x-text="statusLabel(a.status)"></span>
                    </div>

                    <template x-if="a.message">
                        <p class="text-sm text-off-black/80 mt-2" x-text="a.message"></p>
                    </template>
                    <template x-if="a.availability">
                        <p class="text-xs text-off-black/50 mt-1"><span class="font-semibold">{{ __('webapp.applications.availability') }}</span> <span x-text="a.availability"></span></p>
                    </template>

                    {{-- Business actions on pending received applications --}}
                    <template x-if="isBusiness && a.status === 'pending'">
                        <div class="mt-3 border-t border-off-black/10 pt-3">
                            <template x-if="acceptingId !== a.id">
                                <div class="flex gap-2">
                                    <button @click="startAccept(a)" class="rounded-lg bg-off-black text-off-white text-sm font-semibold px-3 py-1.5">{{ __('webapp.applications.accept') }}</button>
                                    <button @click="decline(a)" :disabled="busy" class="rounded-lg text-red-600 text-sm font-semibold px-3 py-1.5">{{ __('webapp.applications.decline') }}</button>
                                </div>
                            </template>
                            <template x-if="acceptingId === a.id">
                                <div class="flex flex-wrap items-end gap-2">
                                    <div>
                                        <label class="text-xs font-semibold block">{{ __('webapp.applications.scheduled_date') }}</label>
                                        <input x-model="scheduledDate" type="date" :min="minDate" class="mt-1 rounded-lg border-off-black/15 px-3 py-1.5 text-sm focus:border-off-black focus:ring-0">
                                    </div>
                                    <button @click="confirmAccept(a)" :disabled="busy || !scheduledDate" class="rounded-lg bg-off-black text-off-white text-sm font-semibold px-3 py-1.5 disabled:opacity-50">{{ __('webapp.applications.confirm') }}</button>
                                    <button @click="acceptingId = null" class="rounded-lg bg-off-black/5 text-sm font-semibold px-3 py-1.5">{{ __('webapp.common.cancel') }}</button>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Community can withdraw a pending application --}}
                    <template x-if="!isBusiness && a.status === 'pending'">
                        <div class="mt-3">
                            <button @click="withdraw(a)" :disabled="busy" class="rounded-lg text-red-600 text-sm font-semibold">{{ __('webapp.applications.withdraw') }}</button>
                        </div>
                    </template>

                    <template x-if="a.status === 'accepted'">
                        <p class="text-xs text-green-700 mt-2">{{ __('webapp.applications.accepted_note') }}</p>
                    </template>
                </div>
            </template>
        </div>
    </main>
</div>

@push('scripts')
<script>
    function applicationsPage() {
        return {
            loading: true, busy: false, error: '', isBusiness: false,
            items: [], acceptingId: null, scheduledDate: '',
            minDate: new Date(Date.now() + 86400000).toISOString().slice(0, 10),
            statusLabel(s) { return window.t('status.' + s); },
            statusClass(s) { return { pending: 'bg-amber-100 text-amber-700', accepted: 'bg-green-100 text-green-700', declined: 'bg-red-100 text-red-600', withdrawn: 'bg-off-black/10 text-off-black/50' }[s] || 'bg-off-black/10'; },
            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await window.kb.api('/auth/me');
                if (!me.ok) { window.kb.logout(); return; }
                this.isBusiness = me.json?.data?.user_type === 'business';
                await this.load();
            },
            async load() {
                this.loading = true; this.error = '';
                const path = this.isBusiness ? '/me/received-applications' : '/me/applications';
                const res = await window.kb.api(path + '?per_page=50');
                if (res.ok) this.items = res.json?.data || [];
                else this.error = res.json?.message || t('applications.load_error');
                this.loading = false;
            },
            startAccept(a) { this.acceptingId = a.id; this.scheduledDate = ''; },
            async confirmAccept(a) {
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/applications/' + a.id + '/accept', { method: 'POST', body: { scheduled_date: this.scheduledDate } });
                this.busy = false;
                if (res.ok) { a.status = 'accepted'; this.acceptingId = null; }
                else if (res.status === 402) { window.nav('/subscription'); }
                else if (res.status === 422 && res.json?.errors) this.error = Object.values(res.json.errors).flat().join('\n');
                else this.error = res.json?.message || t('applications.accept_error');
            },
            async decline(a) {
                const reason = prompt(t('applications.decline_reason')) || undefined;
                this.busy = true;
                const res = await window.kb.api('/applications/' + a.id + '/decline', { method: 'POST', body: reason ? { reason } : {} });
                this.busy = false;
                if (res.ok) a.status = 'declined'; else this.error = res.json?.message || t('applications.decline_error');
            },
            async withdraw(a) {
                if (!confirm(t('applications.withdraw_confirm'))) return;
                this.busy = true;
                const res = await window.kb.api('/applications/' + a.id + '/withdraw', { method: 'POST' });
                this.busy = false;
                if (res.ok) a.status = 'withdrawn'; else this.error = res.json?.message || t('applications.withdraw_error');
            },
        };
    }
</script>
@endpush
@endsection
