{{--
    Community Hub modals + the member detail drawer.

    Included by community-members.blade.php, whose Alpine component owns every
    piece of state referenced here. No window.confirm/alert anywhere: a browser
    modal blocks the page (and any automation driving it).
--}}

{{-- ── Member detail drawer ───────────────────────────────────────────── --}}
<div x-show="drawer" x-cloak class="fixed inset-0 z-40 flex justify-end">
    <div class="absolute inset-0 bg-ink/40" @click="closeDrawer()"></div>

    <aside class="relative w-full max-w-[420px] h-full bg-white shadow-xl overflow-y-auto kb-scroll">
        <div class="sticky top-0 bg-white border-b border-ink/[.08] px-5 py-4 flex items-center justify-between">
            <p class="font-anton text-[19px] tracking-[.5px]">{{ __('webapp.community.members.detail_title') }}</p>
            <button type="button" @click="closeDrawer()" class="text-muted hover:text-ink" aria-label="{{ __('webapp.common.close') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <template x-if="drawer">
            <div class="px-5 py-6">
                <div class="flex items-center gap-3.5">
                    <template x-if="drawer.profile?.avatar_url">
                        <img :src="drawer.profile.avatar_url" alt="" class="w-14 h-14 rounded-full object-cover shrink-0">
                    </template>
                    <template x-if="!drawer.profile?.avatar_url">
                        <div class="w-14 h-14 rounded-full bg-primary/50 flex items-center justify-center text-lg font-bold shrink-0" x-text="window.kbInitial(drawer.profile?.name)"></div>
                    </template>
                    <div class="min-w-0">
                        <p class="font-bold text-ink truncate" x-text="drawer.profile?.name"></p>
                        <p class="text-[12px] text-muted truncate" x-text="drawer.profile?.email"></p>
                        <p class="text-[12px] text-muted truncate" x-show="drawer.profile?.handle" x-text="'@' + (drawer.profile?.handle || '')"></p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span x-show="drawer.tier" x-cloak class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-pill border border-ink/[.12] text-[12px] font-bold">
                        <span class="w-2 h-2 rounded-full" :style="'background:' + (drawer.tier?.color || '#FFE28C')"></span>
                        <span x-text="drawer.tier?.name"></span>
                    </span>
                    <span x-show="drawer.can_manage" x-cloak class="px-3 py-1.5 rounded-pill bg-ink text-primary text-[12px] font-bold">{{ __('webapp.community.members.manager') }}</span>
                    <span class="px-3 py-1.5 rounded-pill bg-cream-low text-[12px] font-bold" x-text="drawer.status"></span>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2.5">
                    <div class="rounded-2xl bg-cream-low px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-muted">{{ __('webapp.community.members.col_points') }}</p>
                        <p class="text-xl font-bold tabular-nums" x-text="drawer.points ?? 0"></p>
                    </div>
                    <div class="rounded-2xl bg-cream-low px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-muted">{{ __('webapp.community.members.col_events') }}</p>
                        <p class="text-xl font-bold tabular-nums" x-text="drawer.events_attended ?? 0"></p>
                    </div>
                    <div class="rounded-2xl bg-cream-low px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-muted">{{ __('webapp.community.members.tenure') }}</p>
                        <p class="text-xl font-bold tabular-nums" x-text="(drawer.tenure_days ?? 0) + 'd'"></p>
                    </div>
                    <div class="rounded-2xl bg-cream-low px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-muted">{{ __('webapp.community.members.col_last_active') }}</p>
                        <p class="text-sm font-bold" x-text="window.kbDateShort(drawer.last_active_at)"></p>
                    </div>
                </div>

                <p class="mt-7 text-[11px] font-semibold tracking-[.16em] uppercase text-muted">{{ __('webapp.community.members.activity') }}</p>

                <template x-if="drawerLoading">
                    <p class="mt-3 text-sm text-muted">{{ __('webapp.common.loading') }}</p>
                </template>

                <template x-if="!drawerLoading && drawerActivity.length === 0">
                    <p class="mt-3 text-sm text-muted">{{ __('webapp.community.members.activity_empty') }}</p>
                </template>

                <ul class="mt-3 flex flex-col gap-1.5">
                    <template x-for="row in drawerActivity" :key="row.id">
                        <li class="flex items-baseline justify-between gap-3 rounded-xl bg-cream-low px-3.5 py-2.5">
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold text-ink" x-text="row.description || window.kbHumanize(row.source)"></span>
                                <span class="block text-[11px] text-muted" x-text="window.kbDateShort(row.created_at)"></span>
                            </span>
                            <span class="shrink-0 text-[13px] font-bold tabular-nums" :class="row.points < 0 ? 'text-bad-ink' : 'text-ink'"
                                  x-text="(row.points > 0 ? '+' : '') + row.points"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
    </aside>
</div>

{{-- ── Add member ─────────────────────────────────────────────────────── --}}
<div x-show="addOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-5">
    <div class="absolute inset-0 bg-ink/40" @click="addOpen = false"></div>

    <div class="relative w-full max-w-[440px] bg-white rounded-3xl shadow-xl p-6">
        <p class="font-anton text-[21px] tracking-[.5px]">{{ __('webapp.community.members.add_title') }}</p>
        <p class="mt-1.5 text-sm text-muted">{{ __('webapp.community.members.add_help') }}</p>

        <label class="block mt-5 text-[12px] font-bold text-body">{{ __('webapp.community.members.add_label') }}</label>
        <input type="text" x-model="addIdentifier" @keydown.enter="submitAdd()"
               placeholder="{{ __('webapp.community.members.add_placeholder') }}"
               class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">

        <label class="block mt-4 text-[12px] font-bold text-body">{{ __('webapp.community.members.tier_label') }}</label>
        <select x-model="addTierId" class="mt-1.5 w-full h-11 px-3 rounded-2xl bg-white border border-ink/[.12] text-sm font-semibold">
            <option value="">{{ __('webapp.community.members.tier_default') }}</option>
            <template x-for="t in tiers" :key="t.id">
                <option :value="t.id" x-text="t.name"></option>
            </template>
        </select>

        <template x-if="addError">
            <div class="mt-4 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3">
                <p x-text="addError"></p>
                <button type="button" x-show="addOfferInvite" @click="escalateToInvite()"
                        class="mt-2 font-bold underline">{{ __('webapp.community.members.send_invite_instead') }}</button>
            </div>
        </template>

        <div class="mt-6 flex gap-2.5">
            <button type="button" @click="addOpen = false" class="flex-1 h-11 rounded-pill bg-white border border-ink/[.12] text-sm font-bold">{{ __('webapp.common.cancel') }}</button>
            <button type="button" @click="submitAdd()" :disabled="addBusy || !addIdentifier.trim()"
                    class="flex-1 h-11 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn disabled:opacity-50"
                    x-text="addBusy ? '{{ __('webapp.common.saving') }}' : '{{ __('webapp.community.members.add') }}'"></button>
        </div>
    </div>
</div>

{{-- ── Invite by email ────────────────────────────────────────────────── --}}
<div x-show="inviteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-5">
    <div class="absolute inset-0 bg-ink/40" @click="inviteOpen = false"></div>

    <div class="relative w-full max-w-[480px] bg-white rounded-3xl shadow-xl p-6">
        <p class="font-anton text-[21px] tracking-[.5px]">{{ __('webapp.community.members.invite_title') }}</p>
        <p class="mt-1.5 text-sm text-muted">{{ __('webapp.community.members.invite_help') }}</p>

        <label class="block mt-5 text-[12px] font-bold text-body">{{ __('webapp.community.members.invite_label') }}</label>
        <textarea x-model="inviteEmails" rows="5"
                  placeholder="{{ __('webapp.community.members.invite_placeholder') }}"
                  class="mt-1.5 w-full px-4 py-3 rounded-2xl bg-white border border-ink/[.12] text-sm"></textarea>
        <p class="mt-1.5 text-[11px] text-muted" x-text="t('community.members.invite_count', { count: parsedInviteEmails.length })"></p>

        <label class="block mt-4 text-[12px] font-bold text-body">{{ __('webapp.community.members.tier_label') }}</label>
        <select x-model="inviteTierId" class="mt-1.5 w-full h-11 px-3 rounded-2xl bg-white border border-ink/[.12] text-sm font-semibold">
            <option value="">{{ __('webapp.community.members.tier_default') }}</option>
            <template x-for="t in tiers" :key="t.id">
                <option :value="t.id" x-text="t.name"></option>
            </template>
        </select>

        <template x-if="inviteError">
            <div class="mt-4 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3" x-text="inviteError"></div>
        </template>

        <template x-if="inviteResult">
            <div class="mt-4 rounded-2xl bg-cream-low text-sm px-4 py-3">
                <p class="font-bold" x-text="t('community.members.invite_result', { invited: inviteResult.invited, existing: inviteResult.already_members })"></p>
                <p class="mt-1 text-muted">{{ __('webapp.community.members.invite_result_help') }}</p>
            </div>
        </template>

        <div class="mt-6 flex gap-2.5">
            <button type="button" @click="inviteOpen = false" class="flex-1 h-11 rounded-pill bg-white border border-ink/[.12] text-sm font-bold">{{ __('webapp.common.close') }}</button>
            <button type="button" @click="submitInvite()" :disabled="inviteBusy || !parsedInviteEmails.length"
                    class="flex-1 h-11 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn disabled:opacity-50"
                    x-text="inviteBusy ? '{{ __('webapp.common.saving') }}' : '{{ __('webapp.community.members.send_invites') }}'"></button>
        </div>
    </div>
</div>
