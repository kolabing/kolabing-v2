@extends('webapp.layout')
@section('title', __('webapp.account.gallery.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), kbPhonePreview(), accountGalleryPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'account'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    {{-- The phone preview sits beside the tab from xl up; below that the
         tab keeps its full-width layout. --}}
    <div class="xl:flex xl:items-start xl:gap-8 xl:pr-10">
    <div class="flex-1 min-w-0">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        @include('webapp.partials.account-nav', ['accountActive' => 'gallery'])

        <p class="mt-5 text-sm text-muted">{{ __('webapp.account.gallery.help') }}</p>

        {{-- ── Toolbar ─────────────────────────────────────────────────── --}}
        <div class="mt-5 flex flex-wrap items-center gap-3">
            <span class="text-[12px] font-bold text-body tabular-nums" x-text="photos.length + ' / ' + max"></span>
            <div class="flex-1"></div>
            <label class="h-11 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition flex items-center cursor-pointer"
                   :class="(busy || remaining === 0) ? 'opacity-50 pointer-events-none' : ''">
                <input type="file" accept="image/*" multiple class="hidden" @change="upload($event)" :disabled="busy || remaining === 0">
                <span x-text="busy ? '{{ __('webapp.common.uploading') }}' : '{{ __('webapp.account.gallery.upload') }}'"></span>
            </label>
        </div>

        <template x-if="error">
            <div class="mt-4 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>

        <template x-if="loading">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        {{-- ── Empty state ─────────────────────────────────────────────── --}}
        <template x-if="!loading && photos.length === 0">
            <div class="mt-6 bg-white border border-ink/[.08] rounded-2xl p-10 text-center shadow-card">
                <p class="font-bold text-ink">{{ __('webapp.account.gallery.empty_title') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('webapp.account.gallery.empty_body') }}</p>
            </div>
        </template>

        {{-- ── Grid ────────────────────────────────────────────────────── --}}
        <div x-show="!loading && photos.length" x-cloak
             class="mt-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <template x-for="(photo, index) in photos" :key="photo.id">
                <div class="bg-white border border-ink/[.08] rounded-2xl overflow-hidden shadow-card"
                     draggable="true"
                     @dragstart="dragFrom = index"
                     @dragover.prevent
                     @drop.prevent="onDrop(index)"
                     :class="dragFrom === index ? 'opacity-50' : ''">

                    <img :src="photo.url" alt="" class="w-full aspect-square object-cover bg-cream-low">

                    <div class="p-3">
                        {{-- Caption: click to edit, blur or Enter to save. --}}
                        <template x-if="editingId !== photo.id">
                            <button type="button" @click="startEditing(photo)"
                                    class="w-full text-left text-[12px] leading-snug"
                                    :class="photo.caption ? 'text-body' : 'text-muted italic'"
                                    x-text="photo.caption || '{{ __('webapp.account.gallery.add_caption') }}'"></button>
                        </template>
                        <template x-if="editingId === photo.id">
                            <input type="text" x-model="editingCaption" maxlength="500"
                                   @keydown.enter="saveCaption(photo)" @keydown.escape="editingId = null"
                                   @blur="saveCaption(photo)"
                                   x-init="$nextTick(() => $el.focus())"
                                   class="w-full h-8 px-2 rounded-lg bg-white border border-ink/[.12] text-[12px]">
                        </template>

                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-[10px] uppercase tracking-wide text-muted">{{ __('webapp.account.gallery.drag_hint') }}</span>
                            <template x-if="confirmDelete !== photo.id">
                                <button type="button" @click="confirmDelete = photo.id"
                                        class="text-[11px] font-bold text-bad-ink">{{ __('webapp.common.delete') }}</button>
                            </template>
                            <template x-if="confirmDelete === photo.id">
                                <span class="inline-flex items-center gap-2">
                                    <button type="button" @click="remove(photo)" class="text-[11px] font-bold text-bad-ink">{{ __('webapp.common.confirm') }}</button>
                                    <button type="button" @click="confirmDelete = null" class="text-[11px] font-semibold text-muted">{{ __('webapp.common.cancel') }}</button>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
    </div>

    @include('webapp.partials.phone-preview')
    </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    function accountGalleryPage() {
        return {
            t: (key, params) => window.t(key, params),
            loading: true,
            busy: false,
            error: '',
            photos: [],
            max: 20,
            confirmDelete: null,
            dragFrom: null,
            editingId: null,
            editingCaption: '',

            get remaining() { return Math.max(0, this.max - this.photos.length); },

            async init() {
                await this.loadShell();
                await this.load();
                this.initPreview();
            },

            async load() {
                this.loading = true;
                const res = await window.kb.api('/me/gallery');
                this.loading = false;
                this.photos = res.ok ? window.kb.rows(res) : [];
                this.confirmDelete = null;
            },

            /**
             * The endpoint caps a request at 5 files but the gallery allows 20,
             * so a larger drop is sent in sequential chunks rather than rejected
             * or silently truncated.
             */
            async upload(event) {
                const files = Array.from(event.target.files || []).slice(0, this.remaining);
                event.target.value = '';
                if (!files.length) return;

                this.busy = true;
                this.error = '';
                const failures = [];

                for (let i = 0; i < files.length; i += 5) {
                    const fd = new FormData();
                    files.slice(i, i + 5).forEach(f => fd.append('photos[]', f));
                    const res = await window.kb.upload('/me/gallery', fd);
                    if (!res.ok) failures.push(window.kb.errorText(res, window.t('account.gallery.upload_error')));
                }

                this.busy = false;
                if (failures.length) this.error = failures.join('\n');
                await this.load();
                this.refreshPreview();
            },

            startEditing(photo) {
                this.editingId = photo.id;
                this.editingCaption = photo.caption || '';
            },

            async saveCaption(photo) {
                if (this.editingId !== photo.id) return;
                const caption = this.editingCaption.trim() || null;
                this.editingId = null;

                if (caption === (photo.caption || null)) return;

                const res = await window.kb.api('/me/gallery/' + photo.id, {
                    method: 'PATCH', body: { caption },
                });
                if (res.ok) { photo.caption = res.json?.data?.caption ?? null; this.refreshPreview(); }
                else this.error = window.kb.errorText(res, window.t('account.gallery.save_error'));
            },

            async remove(photo) {
                this.confirmDelete = null;
                const res = await window.kb.api('/me/gallery/' + photo.id, { method: 'DELETE' });
                if (res.ok) { await this.load(); this.refreshPreview(); }
                else this.error = window.kb.errorText(res, window.t('account.gallery.delete_error'));
            },

            /* Drag to reorder — optimistic, then persisted. */
            onDrop(index) {
                if (this.dragFrom === null || this.dragFrom === index) { this.dragFrom = null; return; }
                const moved = this.photos.splice(this.dragFrom, 1)[0];
                this.photos.splice(index, 0, moved);
                this.dragFrom = null;
                this.persistOrder();
            },

            async persistOrder() {
                const res = await window.kb.api('/me/gallery/order', {
                    method: 'PUT', body: { ids: this.photos.map(p => p.id) },
                });
                if (res.ok) {
                    this.photos = window.kb.rows(res);
                    this.refreshPreview();
                } else {
                    this.error = window.kb.errorText(res, window.t('account.gallery.order_error'));
                    await this.load();
                }
            },
        };
    }
</script>
@endpush
