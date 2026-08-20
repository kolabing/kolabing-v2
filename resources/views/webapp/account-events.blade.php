@extends('webapp.layout')
@section('title', __('webapp.account.events.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), accountEventsPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'account'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        @include('webapp.partials.account-nav', ['accountActive' => 'events'])

        {{-- True as of the past-events merge: these now render on the public profile. --}}
        <p class="mt-5 text-sm text-muted">{{ __('webapp.account.events.help') }}</p>

        <template x-if="error">
            <div class="mt-4 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>

        <div class="mt-5 flex justify-end">
            <button type="button" @click="openForm()"
                    class="h-11 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition">
                {{ __('webapp.account.events.log') }}
            </button>
        </div>

        {{-- ── Create form ─────────────────────────────────────────────── --}}
        <template x-if="form">
            <div class="mt-3 bg-white border border-ink/[.12] rounded-2xl p-5 shadow-card">
                <p class="font-anton text-[19px] tracking-[.5px]">{{ __('webapp.account.events.log') }}</p>

                <div class="mt-4 grid sm:grid-cols-2 gap-3.5">
                    <div class="sm:col-span-2">
                        <label class="block text-[12px] font-bold text-body">{{ __('webapp.account.events.name') }}</label>
                        <input type="text" x-model="form.name" minlength="3" maxlength="100" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold text-body">{{ __('webapp.account.events.partner_name') }}</label>
                        <input type="text" x-model="form.partner_name" minlength="2" maxlength="100" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold text-body">{{ __('webapp.account.events.partner_type') }}</label>
                        <select x-model="form.partner_type" class="mt-1.5 w-full h-11 px-3 rounded-2xl bg-white border border-ink/[.12] text-sm font-semibold">
                            <option value="business">{{ __('webapp.account.events.partner_business') }}</option>
                            <option value="community">{{ __('webapp.account.events.partner_community') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold text-body">{{ __('webapp.account.events.date') }}</label>
                        <input type="date" x-model="form.date" :max="today" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold text-body">{{ __('webapp.account.events.attendees') }}</label>
                        <input type="number" min="1" x-model.number="form.attendee_count" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[12px] font-bold text-body">{{ __('webapp.account.events.photos_required') }}</label>
                        <input type="file" accept="image/*" multiple class="mt-1.5 text-sm" @change="form.files = Array.from($event.target.files || []).slice(0, 5)">
                        <p class="mt-1 text-[11px] text-muted" x-text="form.files.length + ' / 5'"></p>
                    </div>
                </div>

                <div class="mt-6 flex gap-2.5">
                    <button type="button" @click="form = null" class="h-11 px-5 rounded-pill bg-white border border-ink/[.12] text-sm font-bold">{{ __('webapp.common.cancel') }}</button>
                    {{-- The API requires 1-5 photo files, so the button stays
                         disabled until one is chosen rather than submitting into
                         a guaranteed 422. --}}
                    <button type="button" @click="create()" :disabled="busy || !canSubmit"
                            class="h-11 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn disabled:opacity-50"
                            x-text="busy ? '{{ __('webapp.common.saving') }}' : '{{ __('webapp.common.save') }}'"></button>
                </div>
            </div>
        </template>

        <template x-if="loading">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        <template x-if="!loading && events.length === 0 && !form">
            <div class="mt-6 bg-white border border-ink/[.08] rounded-2xl p-10 text-center shadow-card">
                <p class="font-bold text-ink">{{ __('webapp.account.events.empty_title') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('webapp.account.events.empty_body') }}</p>
            </div>
        </template>

        {{-- ── List ────────────────────────────────────────────────────── --}}
        <div x-show="!loading && events.length" x-cloak class="mt-5 flex flex-col gap-2.5">
            <template x-for="event in events" :key="event.id">
                <div class="bg-white border border-ink/[.08] rounded-2xl shadow-card overflow-hidden">
                    <button type="button" @click="toggle(event)" class="w-full flex items-center gap-3.5 p-4 text-left hover:bg-cream-low/60 transition">
                        <template x-if="coverOf(event)">
                            <img :src="coverOf(event)" alt="" class="w-14 h-14 rounded-xl object-cover shrink-0 bg-cream-low">
                        </template>
                        <template x-if="!coverOf(event)">
                            <div class="w-14 h-14 rounded-xl bg-cream-low shrink-0"></div>
                        </template>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-ink truncate" x-text="event.name"></p>
                            <p class="text-[11px] text-muted">
                                <span x-text="window.kbDateShort(event.event_date || event.date)"></span>
                                <template x-if="event.partner_name">
                                    <span><span aria-hidden="true">·</span> <span x-text="event.partner_name"></span></span>
                                </template>
                                <span aria-hidden="true">·</span>
                                <span x-text="t('account.events.attendee_count', { count: event.attendee_count || 0 })"></span>
                            </p>
                        </div>
                        <span class="text-[11px] text-muted tabular-nums" x-text="(event.photos?.length || 0) + ' / 20'"></span>
                    </button>

                    {{-- ── Editor ──────────────────────────────────────── --}}
                    <div x-show="openId === event.id" x-cloak class="px-4 pb-4 border-t border-ink/[.06] pt-4">
                        <div class="grid sm:grid-cols-2 gap-3.5">
                            <div class="sm:col-span-2">
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.account.events.name') }}</label>
                                <input type="text" x-model="edit.name" maxlength="100" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.account.events.partner_name') }}</label>
                                <input type="text" x-model="edit.partner_name" maxlength="100" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.account.events.date') }}</label>
                                <input type="date" x-model="edit.date" :max="today" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.account.events.attendees') }}</label>
                                <input type="number" min="1" x-model.number="edit.attendee_count" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2.5">
                            <button type="button" @click="save(event)" :disabled="busy"
                                    class="h-10 px-4 rounded-pill bg-primary text-ink text-[13px] font-bold shadow-btn disabled:opacity-50">{{ __('webapp.common.save') }}</button>
                            <label class="h-10 px-4 rounded-pill bg-white border border-ink/[.12] text-[13px] font-bold flex items-center cursor-pointer"
                                   :class="busy ? 'opacity-50 pointer-events-none' : ''">
                                <input type="file" accept="image/*" multiple class="hidden" @change="addPhotos(event, $event)">
                                {{ __('webapp.account.events.add_photos') }}
                            </label>
                            <div class="flex-1"></div>
                            <template x-if="confirmDelete !== event.id">
                                <button type="button" @click="confirmDelete = event.id" class="h-10 px-3 text-[13px] font-bold text-bad-ink">{{ __('webapp.common.delete') }}</button>
                            </template>
                            <template x-if="confirmDelete === event.id">
                                <span class="inline-flex items-center gap-2">
                                    <button type="button" @click="destroy(event)" class="h-10 px-3 text-[13px] font-bold text-bad-ink">{{ __('webapp.common.confirm') }}</button>
                                    <button type="button" @click="confirmDelete = null" class="h-10 px-2 text-[13px] font-semibold text-muted">{{ __('webapp.common.cancel') }}</button>
                                </span>
                            </template>
                        </div>

                        <p class="mt-5 text-[11px] font-semibold tracking-[.16em] uppercase text-muted">{{ __('webapp.account.events.photos') }}</p>
                        <p class="mt-1 text-[11px] text-muted">{{ __('webapp.account.gallery.drag_hint') }}</p>

                        <div class="mt-3 grid grid-cols-3 sm:grid-cols-5 gap-2.5">
                            <template x-for="(photo, index) in (event.photos || [])" :key="photo.id">
                                <div class="relative rounded-xl overflow-hidden border border-ink/[.08]"
                                     draggable="true"
                                     @dragstart="dragFrom = index"
                                     @dragover.prevent
                                     @drop.prevent="onPhotoDrop(event, index)"
                                     :class="dragFrom === index ? 'opacity-50' : ''">
                                    <img :src="photo.url" alt="" class="w-full aspect-square object-cover bg-cream-low">
                                    <button type="button" @click="removePhoto(event, photo)"
                                            class="absolute top-1 right-1 w-6 h-6 rounded-full bg-ink/70 text-white text-[13px] leading-none flex items-center justify-center"
                                            aria-label="{{ __('webapp.common.delete') }}">&times;</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    function accountEventsPage() {
        return {
            t: (key, params) => window.t(key, params),
            loading: true,
            busy: false,
            error: '',
            events: [],
            form: null,
            edit: {},
            openId: null,
            confirmDelete: null,
            dragFrom: null,

            get today() { return new Date().toISOString().slice(0, 10); },

            get canSubmit() {
                return this.form
                    && this.form.name.trim().length >= 3
                    && this.form.partner_name.trim().length >= 2
                    && !!this.form.date
                    && this.form.attendee_count >= 1
                    && this.form.files.length > 0;
            },

            async init() {
                await this.loadShell();
                await this.load();
            },

            async load() {
                if (!this.me?.id) { this.loading = false; return; }
                this.loading = true;
                const res = await window.kb.api('/events?time=past&profile_id=' + this.me.id + '&limit=50');
                this.loading = false;
                this.events = res.ok ? window.kb.rows(res) : [];
                this.confirmDelete = null;
            },

            coverOf(event) { return (event.photos && event.photos[0]) ? event.photos[0].url : null; },

            openForm() {
                this.form = {
                    name: '', partner_name: '', partner_type: 'business',
                    date: '', attendee_count: 1, files: [],
                };
                this.error = '';
            },

            async create() {
                this.busy = true;
                this.error = '';

                const fd = new FormData();
                fd.append('name', this.form.name.trim());
                fd.append('partner_name', this.form.partner_name.trim());
                fd.append('partner_type', this.form.partner_type);
                fd.append('date', this.form.date);
                fd.append('attendee_count', String(this.form.attendee_count));
                this.form.files.forEach(f => fd.append('photos[]', f));

                const res = await window.kb.upload('/events', fd);
                this.busy = false;

                if (res.ok) { this.form = null; await this.load(); return; }
                this.error = window.kb.errorText(res, window.t('account.events.save_error'));
            },

            toggle(event) {
                if (this.openId === event.id) { this.openId = null; return; }
                this.openId = event.id;
                this.edit = {
                    name: event.name || '',
                    partner_name: event.partner_name || '',
                    date: (event.event_date || event.date || '').slice(0, 10),
                    attendee_count: event.attendee_count || 1,
                };
                this.confirmDelete = null;
            },

            async save(event) {
                this.busy = true;
                this.error = '';
                const res = await window.kb.api('/events/' + event.id, {
                    method: 'PUT',
                    body: {
                        name: this.edit.name.trim(),
                        partner_name: this.edit.partner_name.trim(),
                        date: this.edit.date,
                        attendee_count: this.edit.attendee_count,
                    },
                });
                this.busy = false;
                if (res.ok) await this.load();
                else this.error = window.kb.errorText(res, window.t('account.events.save_error'));
            },

            async destroy(event) {
                this.confirmDelete = null;
                const res = await window.kb.api('/events/' + event.id, { method: 'DELETE' });
                if (res.ok) { this.openId = null; await this.load(); }
                else this.error = window.kb.errorText(res, window.t('account.events.delete_error'));
            },

            /** Same chunking rule as the gallery: 5 per request, 20 per event. */
            async addPhotos(event, domEvent) {
                const room = Math.max(0, 20 - (event.photos?.length || 0));
                const files = Array.from(domEvent.target.files || []).slice(0, room);
                domEvent.target.value = '';
                if (!files.length) return;

                this.busy = true;
                this.error = '';
                const failures = [];

                for (let i = 0; i < files.length; i += 5) {
                    const fd = new FormData();
                    files.slice(i, i + 5).forEach(f => fd.append('photos[]', f));
                    const res = await window.kb.upload('/events/' + event.id + '/photos', fd);
                    if (!res.ok) failures.push(window.kb.errorText(res, window.t('account.events.photo_error')));
                }

                this.busy = false;
                if (failures.length) this.error = failures.join('\n');
                await this.load();
            },

            async removePhoto(event, photo) {
                const res = await window.kb.api('/events/' + event.id + '/photos/' + photo.id, { method: 'DELETE' });
                if (res.ok) await this.load();
                else this.error = window.kb.errorText(res, window.t('account.events.photo_error'));
            },

            onPhotoDrop(event, index) {
                if (this.dragFrom === null || this.dragFrom === index) { this.dragFrom = null; return; }
                const photos = event.photos || [];
                const moved = photos.splice(this.dragFrom, 1)[0];
                photos.splice(index, 0, moved);
                this.dragFrom = null;
                this.persistPhotoOrder(event);
            },

            async persistPhotoOrder(event) {
                const res = await window.kb.api('/events/' + event.id + '/photos/order', {
                    method: 'PUT', body: { ids: (event.photos || []).map(p => p.id) },
                });
                if (res.ok) event.photos = window.kb.rows(res);
                else { this.error = window.kb.errorText(res, window.t('account.events.photo_error')); await this.load(); }
            },
        };
    }
</script>
@endpush
