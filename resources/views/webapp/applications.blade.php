@extends('webapp.layout')
@section('title', 'Applications')

@section('body')
<div x-data="applicationsPage()" x-init="init()">
    @include('webapp.partials.nav', ['active' => 'applications'])

    <main class="max-w-2xl mx-auto px-5 py-8">
        <h1 class="font-montserrat font-black text-2xl tracking-tight" x-text="isBusiness ? 'Applications received' : 'Your applications'"></h1>

        <template x-if="error"><div class="mt-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div></template>

        <template x-if="loading"><p class="mt-8 text-off-black/50">Loading…</p></template>
        <template x-if="!loading && items.length === 0">
            <p class="mt-8 text-off-black/50" x-text="isBusiness ? 'No applications yet. Publish a Kolab to start receiving them.' : 'You haven\'t applied to any Kolabs yet.'"></p>
        </template>

        <div class="mt-5 space-y-3">
            <template x-for="a in items" :key="a.id">
                <div class="rounded-2xl border border-off-black/10 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a :href="'/kolabs/' + a.kolab_id" class="font-semibold leading-snug" x-text="a.kolab?.title || 'Kolab'"></a>
                            <p class="text-sm text-off-black/60 mt-0.5"
                               x-text="isBusiness ? ('From ' + (a.applicant_profile?.name || a.applicant_profile?.handle || 'a community')) : ('Status: ' + a.status)"></p>
                        </div>
                        <span class="text-[10px] uppercase tracking-wide font-bold px-2 py-0.5 rounded-full whitespace-nowrap" :class="statusClass(a.status)" x-text="a.status"></span>
                    </div>

                    <template x-if="a.message">
                        <p class="text-sm text-off-black/80 mt-2" x-text="a.message"></p>
                    </template>
                    <template x-if="a.availability">
                        <p class="text-xs text-off-black/50 mt-1"><span class="font-semibold">Availability:</span> <span x-text="a.availability"></span></p>
                    </template>

                    {{-- Business actions on pending received applications --}}
                    <template x-if="isBusiness && a.status === 'pending'">
                        <div class="mt-3 border-t border-off-black/10 pt-3">
                            <template x-if="acceptingId !== a.id">
                                <div class="flex gap-2">
                                    <button @click="startAccept(a)" class="rounded-lg bg-off-black text-off-white text-sm font-semibold px-3 py-1.5">Accept</button>
                                    <button @click="decline(a)" :disabled="busy" class="rounded-lg text-red-600 text-sm font-semibold px-3 py-1.5">Decline</button>
                                </div>
                            </template>
                            <template x-if="acceptingId === a.id">
                                <div class="flex flex-wrap items-end gap-2">
                                    <div>
                                        <label class="text-xs font-semibold block">Scheduled date</label>
                                        <input x-model="scheduledDate" type="date" :min="minDate" class="mt-1 rounded-lg border-off-black/15 px-3 py-1.5 text-sm focus:border-off-black focus:ring-0">
                                    </div>
                                    <button @click="confirmAccept(a)" :disabled="busy || !scheduledDate" class="rounded-lg bg-off-black text-off-white text-sm font-semibold px-3 py-1.5 disabled:opacity-50">Confirm</button>
                                    <button @click="acceptingId = null" class="rounded-lg bg-off-black/5 text-sm font-semibold px-3 py-1.5">Cancel</button>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Community can withdraw a pending application --}}
                    <template x-if="!isBusiness && a.status === 'pending'">
                        <div class="mt-3">
                            <button @click="withdraw(a)" :disabled="busy" class="rounded-lg text-red-600 text-sm font-semibold">Withdraw</button>
                        </div>
                    </template>

                    <template x-if="a.status === 'accepted'">
                        <p class="text-xs text-green-700 mt-2">Accepted — the collaboration is scheduled. Continue in the app.</p>
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
                else this.error = res.json?.message || 'Could not load applications.';
                this.loading = false;
            },
            startAccept(a) { this.acceptingId = a.id; this.scheduledDate = ''; },
            async confirmAccept(a) {
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/applications/' + a.id + '/accept', { method: 'POST', body: { scheduled_date: this.scheduledDate } });
                this.busy = false;
                if (res.ok) { a.status = 'accepted'; this.acceptingId = null; }
                else if (res.status === 402) { location.href = '/subscription'; }
                else if (res.status === 422 && res.json?.errors) this.error = Object.values(res.json.errors).flat().join('\n');
                else this.error = res.json?.message || 'Could not accept. Pick a date within the Kolab window.';
            },
            async decline(a) {
                const reason = prompt('Optional: reason for declining?') || undefined;
                this.busy = true;
                const res = await window.kb.api('/applications/' + a.id + '/decline', { method: 'POST', body: reason ? { reason } : {} });
                this.busy = false;
                if (res.ok) a.status = 'declined'; else this.error = res.json?.message || 'Could not decline.';
            },
            async withdraw(a) {
                if (!confirm('Withdraw this application?')) return;
                this.busy = true;
                const res = await window.kb.api('/applications/' + a.id + '/withdraw', { method: 'POST' });
                this.busy = false;
                if (res.ok) a.status = 'withdrawn'; else this.error = res.json?.message || 'Could not withdraw.';
            },
        };
    }
</script>
@endpush
@endsection
