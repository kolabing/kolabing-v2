{{-- Community channel management: create / rename / delete a custom channel, and
     the per-thread member block list. Behaviour lives in chatsPage(); these
     surfaces only appear for a community the viewer OWNS (the API re-checks). --}}

{{-- ── Create a channel ──────────────────────────────────────────────── --}}
<div x-show="modal === 'create'" x-cloak @click="closeModal()"
     class="kb-overlay fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-8">
    <div @click.stop class="bg-white rounded-[22px] w-full max-w-[440px] p-7 kb-fade-up-fast">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-lg font-bold text-ink">{{ __('webapp.chats.new_channel_title') }}</p>
                <p class="mt-1 text-[12.5px] text-body leading-relaxed">{{ __('webapp.chats.new_channel_hint') }}</p>
            </div>
            <button type="button" @click="closeModal()" class="w-9 h-9 rounded-full bg-cream-low hover:bg-cream-low-hover transition flex items-center justify-center shrink-0" aria-label="{{ __('webapp.common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <form @submit.prevent="createChannel()" class="mt-5 flex flex-col gap-4">
            {{-- Only shown when there is a real choice to make. --}}
            <div x-show="managed.length > 1" x-cloak>
                <label class="text-[11px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.chats.channel_community') }}</label>
                <select x-model="modalCommunity"
                        class="mt-1.5 w-full h-11 px-3 rounded-xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13.5px] outline-none transition">
                    <template x-for="c in managed" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="text-[11px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.chats.channel_name') }}</label>
                <input type="text" x-model="modalName" maxlength="60" placeholder="{{ __('webapp.chats.channel_name_ph') }}"
                       class="mt-1.5 w-full h-11 px-4 rounded-xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13.5px] outline-none transition">
            </div>

            <template x-if="modalError">
                <p class="text-[12.5px] text-bad-ink whitespace-pre-line" x-text="modalError"></p>
            </template>

            <div class="flex gap-2.5">
                <button type="button" @click="closeModal()"
                        class="flex-1 h-11 rounded-pill bg-white border border-line text-[13px] font-bold hover:border-ink transition">{{ __('webapp.common.cancel') }}</button>
                <button type="submit" :disabled="modalBusy || modalName.trim() === '' || !modalCommunity"
                        class="kb-on-yellow flex-1 h-11 rounded-pill bg-primary text-ink text-[13px] font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-50"
                        x-text="modalBusy ? t('common.saving') : t('chats.create')"></button>
            </div>
        </form>
    </div>
</div>

{{-- ── Rename a channel ──────────────────────────────────────────────── --}}
<div x-show="modal === 'rename'" x-cloak @click="closeModal()"
     class="kb-overlay fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-8">
    <div @click.stop class="bg-white rounded-[22px] w-full max-w-[420px] p-7 kb-fade-up-fast">
        <p class="text-lg font-bold text-ink">{{ __('webapp.chats.rename_title') }}</p>
        <form @submit.prevent="renameChannel()" class="mt-4 flex flex-col gap-4">
            <input type="text" x-model="modalName" maxlength="60"
                   class="w-full h-11 px-4 rounded-xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13.5px] outline-none transition">
            <template x-if="modalError">
                <p class="text-[12.5px] text-bad-ink whitespace-pre-line" x-text="modalError"></p>
            </template>
            <div class="flex gap-2.5">
                <button type="button" @click="closeModal()"
                        class="flex-1 h-11 rounded-pill bg-white border border-line text-[13px] font-bold hover:border-ink transition">{{ __('webapp.common.cancel') }}</button>
                <button type="submit" :disabled="modalBusy || modalName.trim() === ''"
                        class="kb-on-yellow flex-1 h-11 rounded-pill bg-primary text-ink text-[13px] font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-50"
                        x-text="modalBusy ? t('common.saving') : t('common.save')"></button>
            </div>
        </form>
    </div>
</div>

{{-- ── Delete a channel ──────────────────────────────────────────────── --}}
<div x-show="modal === 'delete'" x-cloak @click="closeModal()"
     class="kb-overlay fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-8">
    <div @click.stop class="bg-white rounded-[22px] w-full max-w-[420px] p-7 kb-fade-up-fast">
        <p class="text-lg font-bold text-ink">{{ __('webapp.chats.delete_title') }}</p>
        <p class="mt-2 text-[13px] text-body leading-relaxed"
           x-text="t('chats.delete_warning', { name: active ? titleFor(active) : '' })"></p>
        <template x-if="modalError">
            <p class="mt-3 text-[12.5px] text-bad-ink whitespace-pre-line" x-text="modalError"></p>
        </template>
        <div class="mt-5 flex gap-2.5">
            <button type="button" @click="closeModal()"
                    class="flex-1 h-11 rounded-pill bg-white border border-line text-[13px] font-bold hover:border-ink transition">{{ __('webapp.common.cancel') }}</button>
            <button type="button" @click="deleteChannel()" :disabled="modalBusy"
                    class="flex-1 h-11 rounded-pill bg-inverse text-on-inverse text-[13px] font-bold hover:opacity-90 transition disabled:opacity-50"
                    x-text="modalBusy ? t('common.saving') : t('chats.delete_confirm')"></button>
        </div>
    </div>
</div>

{{-- ── Members / block list ──────────────────────────────────────────── --}}
<div x-show="modal === 'members'" x-cloak @click="closeModal()"
     class="kb-overlay fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-8">
    <div @click.stop class="bg-white rounded-[22px] w-full max-w-[480px] max-h-[86vh] flex flex-col overflow-hidden kb-fade-up-fast">
        <div class="shrink-0 px-7 pt-6 pb-4 border-b border-ink/[.07] flex items-start justify-between gap-3">
            <div>
                <p class="text-lg font-bold text-ink">{{ __('webapp.chats.members_title') }}</p>
                <p class="mt-1 text-[12.5px] text-body leading-relaxed">{{ __('webapp.chats.members_hint') }}</p>
            </div>
            <button type="button" @click="closeModal()" class="w-9 h-9 rounded-full bg-cream-low hover:bg-cream-low-hover transition flex items-center justify-center shrink-0" aria-label="{{ __('webapp.common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto kb-scroll px-7 py-4">
            <template x-if="membersLoading">
                <p class="text-sm text-muted">{{ __('webapp.common.loading') }}</p>
            </template>
            <template x-if="modalError">
                <p class="text-[12.5px] text-bad-ink whitespace-pre-line" x-text="modalError"></p>
            </template>
            <template x-if="!membersLoading && !modalError && members.length === 0">
                <p class="text-sm text-muted">{{ __('webapp.chats.no_members') }}</p>
            </template>

            <template x-if="membersTotal > members.length">
                <p class="mb-3 text-[12px] text-muted" x-text="t('chats.members_truncated', { n: members.length, total: membersTotal })"></p>
            </template>

            <div class="flex flex-col gap-1.5">
                <template x-for="m in members" :key="m.id">
                    <div class="flex items-center gap-3 py-2">
                        <div class="w-9 h-9 rounded-full bg-primary/40 flex items-center justify-center overflow-hidden text-[13px] font-semibold text-ink shrink-0">
                            <template x-if="m.profile?.avatar_url"><img :src="m.profile.avatar_url" alt="" class="w-full h-full object-cover"></template>
                            <template x-if="!m.profile?.avatar_url"><span x-text="initialOf(m.profile?.name)"></span></template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[13.5px] font-semibold text-ink truncate" x-text="m.profile?.name || '—'"></p>
                            <p class="text-[11.5px] text-muted">
                                <span x-show="m.can_manage" x-cloak>{{ __('webapp.chats.manager') }}</span>
                                <span x-show="isBanned(m)" x-cloak class="text-bad-ink font-semibold">{{ __('webapp.chats.blocked') }}</span>
                                <span x-show="!m.can_manage && !isBanned(m)" x-cloak x-text="m.tier?.name || ''"></span>
                            </p>
                        </div>
                        <button type="button" @click="toggleBan(m)"
                                class="shrink-0 h-9 px-3.5 rounded-pill border text-[12.5px] font-bold transition"
                                :class="isBanned(m) ? 'bg-white border-line text-ink hover:border-ink' : 'bg-white border-line text-danger hover:border-danger'"
                                x-text="isBanned(m) ? t('chats.unblock') : t('chats.block')"></button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
