@extends('webapp.layout')
@section('title', __('webapp.chats.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), chatsPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'chats'])

    {{-- The two panes fill the viewport and scroll independently: a chat that
         scrolls the whole page loses the composer, which must always be reachable. --}}
    <main class="flex-1 min-w-0 flex h-[calc(100dvh-56px)] md:h-screen overflow-hidden">

        {{-- ── Thread list ──────────────────────────────────────────────── --}}
        <section class="w-full md:w-[340px] shrink-0 flex-col bg-white md:border-r border-ink/[.08]"
                 :class="active ? 'hidden md:flex' : 'flex'">
            <div class="shrink-0 px-5 pt-6 pb-3 border-b border-ink/[.06]">
                <div class="flex items-center justify-between gap-3">
                    <h1 class="font-anton text-[24px] tracking-[1px] text-ink">{{ __('webapp.chats.title') }}</h1>
                    <button type="button" @click="openCreate()" x-show="managed.length > 0" x-cloak
                            class="h-9 px-3.5 rounded-pill bg-white border border-line text-[12.5px] font-bold hover:border-ink transition">
                        + {{ __('webapp.chats.new_channel') }}
                    </button>
                </div>
                <div class="mt-3 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="search" x-model="q" placeholder="{{ __('webapp.chats.search') }}"
                           class="w-full h-10 pl-9 pr-3 rounded-pill bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13px] outline-none transition">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto kb-scroll">
                <template x-if="loading">
                    <p class="px-5 py-6 text-sm text-muted">{{ __('webapp.common.loading') }}</p>
                </template>
                <template x-if="error">
                    <div class="mx-5 my-4 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
                </template>
                <template x-if="!loading && !error && threads.length === 0">
                    <p class="px-5 py-8 text-sm text-muted leading-relaxed"
                       x-text="isBusiness ? t('chats.empty_business') : t('chats.empty')"></p>
                </template>
                <template x-if="!loading && threads.length > 0 && visibleThreads.length === 0">
                    <p class="px-5 py-8 text-sm text-muted">{{ __('webapp.chats.no_match') }}</p>
                </template>

                <template x-for="th in visibleThreads" :key="th.id">
                    <button type="button" @click="openThread(th)"
                            class="w-full text-left flex items-start gap-3 px-5 py-3.5 border-b border-ink/[.05] transition"
                            :class="active && active.id === th.id ? 'bg-primary-tint' : 'hover:bg-cream-low'">
                        <div class="w-10 h-10 rounded-full bg-primary/40 flex items-center justify-center overflow-hidden text-[13px] font-semibold text-ink shrink-0">
                            <template x-if="avatarFor(th)"><img :src="avatarFor(th)" alt="" class="w-full h-full object-cover"></template>
                            <template x-if="!avatarFor(th)"><span x-text="initialOf(titleFor(th))"></span></template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-2">
                                <p class="flex-1 min-w-0 truncate text-[13.5px] text-ink"
                                   :class="th.unread_count > 0 ? 'font-bold' : 'font-semibold'" x-text="titleFor(th)"></p>
                                <span class="shrink-0 text-[10.5px] text-muted" x-text="stamp(th.last_message_at)"></span>
                            </div>
                            <p class="mt-0.5 text-[12.5px] truncate"
                               :class="th.unread_count > 0 ? 'text-ink font-medium' : 'text-muted'"
                               x-text="previewFor(th)"></p>
                            <div class="mt-1 flex items-center gap-1.5">
                                <span class="text-[10px] font-semibold tracking-[.1em] uppercase text-muted" x-text="kindLabel(th)"></span>
                                <span x-show="th.unread_count > 0" x-cloak
                                      class="ml-auto min-w-[18px] h-[18px] px-1 rounded-pill bg-inverse text-on-inverse text-[10.5px] font-bold flex items-center justify-center"
                                      x-text="th.unread_count"></span>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </section>

        {{-- ── Conversation ─────────────────────────────────────────────── --}}
        <section class="flex-1 min-w-0 flex-col bg-cream" :class="active ? 'flex' : 'hidden md:flex'">

            {{-- Nothing selected (desktop only — mobile shows the list instead). --}}
            <template x-if="!active">
                <div class="flex-1 flex flex-col items-center justify-center text-center px-8">
                    <div class="w-14 h-14 rounded-full bg-primary/30 flex items-center justify-center text-ink">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <p class="mt-4 font-bold text-ink">{{ __('webapp.chats.pick') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('webapp.chats.pick_hint') }}</p>
                </div>
            </template>

            <template x-if="active">
                <div class="flex-1 min-h-0 flex flex-col">
                    {{-- Header --}}
                    <header class="shrink-0 flex items-center gap-3 px-4 md:px-6 h-16 bg-white border-b border-ink/[.08]">
                        <button type="button" @click="closeThread()" class="md:hidden -ml-1 w-9 h-9 flex items-center justify-center text-ink shrink-0" aria-label="{{ __('webapp.common.back') }}">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <div class="w-9 h-9 rounded-full bg-primary/40 flex items-center justify-center overflow-hidden text-[13px] font-semibold text-ink shrink-0">
                            <template x-if="avatarFor(active)"><img :src="avatarFor(active)" alt="" class="w-full h-full object-cover"></template>
                            <template x-if="!avatarFor(active)"><span x-text="initialOf(titleFor(active))"></span></template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <template x-if="counterpart(active)?.id">
                                <a :href="window.kbPath('/profiles/' + counterpart(active).id)"
                                   class="block text-[14px] font-bold text-ink truncate hover:underline" x-text="titleFor(active)"></a>
                            </template>
                            <template x-if="!counterpart(active)?.id">
                                <p class="text-[14px] font-bold text-ink truncate" x-text="titleFor(active)"></p>
                            </template>
                            <p class="text-[11.5px] text-muted flex items-center gap-1.5">
                                <span x-text="kindLabel(active)"></span>
                                {{-- Socket state is worth surfacing: it is the difference
                                     between instant delivery and a 8s poll. --}}
                                <template x-if="live">
                                    <span class="flex items-center gap-1 text-ok-ink"><span class="w-1.5 h-1.5 rounded-full bg-ok-ink"></span>{{ __('webapp.chats.live') }}</span>
                                </template>
                            </p>
                        </div>
                        <div class="shrink-0 flex items-center gap-1.5">
                            <button type="button" @click="openMembers()" x-show="canModerate(active)" x-cloak
                                    class="h-9 px-3 rounded-pill bg-white border border-line text-[12.5px] font-bold hover:border-ink transition">{{ __('webapp.chats.members') }}</button>
                            <button type="button" @click="openRename()" x-show="canManageChannel(active)" x-cloak
                                    class="h-9 px-3 rounded-pill bg-white border border-line text-[12.5px] font-bold hover:border-ink transition">{{ __('webapp.chats.rename') }}</button>
                            <button type="button" @click="openDelete()" x-show="canManageChannel(active)" x-cloak
                                    class="h-9 w-9 rounded-pill bg-white border border-line text-danger hover:border-danger transition flex items-center justify-center" :aria-label="t('chats.delete')">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                            </button>
                        </div>
                    </header>

                    {{-- Messages --}}
                    <div class="flex-1 min-h-0 overflow-y-auto kb-scroll px-4 md:px-6 py-5" x-ref="scroll">
                        <template x-if="msgError">
                            <div class="rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="msgError"></div>
                        </template>
                        <template x-if="msgLoading && messages.length === 0">
                            <p class="text-sm text-muted">{{ __('webapp.common.loading') }}</p>
                        </template>
                        <template x-if="!msgLoading && !msgError && messages.length === 0">
                            <p class="text-sm text-muted">{{ __('webapp.chats.no_messages') }}</p>
                        </template>

                        <div class="text-center mb-4" x-show="page > 1" x-cloak>
                            <button type="button" @click="loadOlder()" :disabled="msgLoading"
                                    class="h-9 px-4 rounded-pill bg-white border border-line text-[12.5px] font-bold hover:border-ink transition disabled:opacity-50">{{ __('webapp.chats.load_older') }}</button>
                        </div>

                        <div class="flex flex-col gap-2.5">
                            <template x-for="(m, i) in messages" :key="m.id">
                                <div>
                                    {{-- Day separator whenever the calendar day changes. --}}
                                    <template x-if="dayBreak(i)">
                                        <p class="my-4 text-center text-[10.5px] font-semibold tracking-[.12em] uppercase text-muted" x-text="dayLabel(m.created_at)"></p>
                                    </template>
                                    <div class="flex" :class="m.is_own ? 'justify-end' : 'justify-start'">
                                        <div class="max-w-[78%] md:max-w-[62%]">
                                            {{-- Group chats need a name on every incoming
                                                 bubble; a 1:1 chat does not. --}}
                                            <p x-show="!m.is_own && isGroup(active)" x-cloak
                                               class="mb-0.5 ml-1 text-[11px] font-semibold text-muted" x-text="senderName(m)"></p>
                                            <div class="px-3.5 py-2.5 text-[13.5px] leading-relaxed whitespace-pre-wrap break-words"
                                                 :class="m.is_own
                                                     ? 'bg-primary text-ink rounded-2xl rounded-br-md kb-on-yellow'
                                                     : 'bg-white text-ink border border-ink/[.07] rounded-2xl rounded-bl-md'"
                                                 x-text="m.content"></div>
                                            <p class="mt-1 text-[10.5px] text-muted" :class="m.is_own ? 'text-right mr-1' : 'ml-1'" x-text="clock(m.created_at)"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Composer --}}
                    <div class="shrink-0 bg-white border-t border-ink/[.08] px-4 md:px-6 py-3">
                        <template x-if="sendError">
                            <p class="mb-2 text-[12.5px] text-bad-ink whitespace-pre-line" x-text="sendError"></p>
                        </template>
                        <form @submit.prevent="send()" class="flex items-end gap-2">
                            <textarea x-model="draft" x-ref="draft" rows="1"
                                      @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); send(); }"
                                      @input="grow()"
                                      placeholder="{{ __('webapp.chats.placeholder') }}"
                                      class="flex-1 max-h-32 py-2.5 px-4 rounded-2xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13.5px] leading-relaxed outline-none resize-none transition"></textarea>
                            <button type="submit" :disabled="sending || draft.trim() === ''"
                                    class="kb-on-yellow shrink-0 w-11 h-11 rounded-full bg-primary text-ink flex items-center justify-center shadow-btn hover:bg-primary-dark transition disabled:opacity-40 disabled:hover:bg-primary"
                                    :aria-label="t('chats.send')">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </template>
        </section>
    </main>

    @include('webapp.partials.chat-modals')
</div>

@push('scripts')
<script src="/webapp-assets/kb-realtime.js" defer></script>
<script>
    function chatsPage() {
        return {
            threads: [], loading: true, error: '', q: '',
            active: null,
            messages: [], msgLoading: false, msgError: '', page: 1, lastPage: 1,
            draft: '', sending: false, sendError: '',
            /** Socket state, mirrored from kbRealtime so the template can react. */
            live: false,
            channelName: null, ticker: null, tick: 0,
            /** Communities the viewer owns — the only ones with channel management. */
            managed: [],
            modal: '', modalName: '', modalCommunity: '', modalBusy: false, modalError: '', modalNotice: '',
            members: [], bannedIds: [], membersLoading: false, membersTotal: 0,

            /* ── derived ─────────────────────────────────────────────────── */
            get visibleThreads() {
                const q = this.q.trim().toLowerCase();
                if (!q) return this.threads;
                return this.threads.filter(th =>
                    (this.titleFor(th) || '').toLowerCase().includes(q)
                    || (this.previewFor(th) || '').toLowerCase().includes(q));
            },

            /* ── labels ──────────────────────────────────────────────────── */
            initialOf(v) { return window.kbInitial(v); },

            /**
             * The other side of a 1:1 Kolab chat. `participant_summary` carries
             * [applicant, creator] as {name, avatar_url} with no ids, so "not me" is
             * resolved by display name — the only discriminator the payload offers.
             */
            counterpart(th) {
                const people = th.participant_summary || [];
                if (people.length === 0) return null;
                const mine = (this.displayName || '').trim().toLowerCase();
                const other = people.find(p => (p.name || '').trim().toLowerCase() !== mine);
                return other || people[0];
            },
            titleFor(th) {
                if (!th) return '';
                if (th.type === 'collaboration') {
                    const other = this.counterpart(th);
                    return other?.name || (this.isBusiness ? t('chats.a_community') : t('chats.a_business'));
                }
                return th.name || t('chats.kind_community');
            },
            avatarFor(th) {
                if (!th) return '';
                return th.type === 'collaboration' ? (this.counterpart(th)?.avatar_url || '') : '';
            },
            previewFor(th) {
                // `last_message` is omitted (not null) when the relation was not
                // loaded, so fall back rather than render "undefined".
                const text = th.last_message?.content;
                return text ? text.replace(/\s+/g, ' ') : '—';
            },
            kindLabel(th) {
                if (!th) return '';
                if (th.type === 'collaboration') return t('chats.kind_kolab');
                if (th.type === 'community_main') return t('chats.kind_community');
                if (th.type === 'community_custom') return t('chats.kind_channel');
                if (th.type === 'event') return t('chats.kind_event');
                return '';
            },
            /** Group threads show a sender name per bubble; 1:1 Kolab chats do not. */
            isGroup(th) { return !!th && th.type !== 'collaboration'; },
            senderName(m) {
                const p = m.sender_profile || {};
                return p.display_name || p.name || p.handle || '';
            },

            /* ── time ────────────────────────────────────────────────────── */
            clock(iso) {
                if (!iso) return '';
                return new Date(iso).toLocaleTimeString(window.KB_LOCALE || 'en', { hour: '2-digit', minute: '2-digit' });
            },
            /** Relative-ish stamp for the list: time today, else a short date. */
            stamp(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                return this.sameDay(d, new Date()) ? this.clock(iso) : window.kbDateShort(iso);
            },
            sameDay(a, b) {
                return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
            },
            dayLabel(iso) {
                const d = new Date(iso);
                const now = new Date();
                if (this.sameDay(d, now)) return t('chats.today');
                const yesterday = new Date(now);
                yesterday.setDate(now.getDate() - 1);
                if (this.sameDay(d, yesterday)) return t('chats.yesterday');
                return window.kbDateShort(iso);
            },
            dayBreak(i) {
                if (i === 0) return true;
                const prev = this.messages[i - 1]?.created_at;
                const cur = this.messages[i]?.created_at;
                if (!prev || !cur) return false;
                return !this.sameDay(new Date(prev), new Date(cur));
            },

            /* ── permissions (mirrors ChatController; the API still enforces) ─ */
            managesCommunity(id) { return !!id && this.managed.some(c => c.id === id); },
            /** Rename/delete: custom channels only. */
            canManageChannel(th) { return !!th && th.type === 'community_custom' && this.managesCommunity(th.community_id); },
            /** Bans: any community thread, main included. */
            canModerate(th) { return !!th && !!th.community_id && this.managesCommunity(th.community_id); },

            /* ── lifecycle ───────────────────────────────────────────────── */
            async init() {
                if (!window.kb.requireAuth()) return;
                if (!await this.loadShell()) return;
                await this.loadThreads();
                await this.loadManaged();
                this.resolveDeepLink();
                this.startRealtime();
                this.startTicker();
                document.addEventListener('visibilitychange', () => {
                    // Coming back to the tab should feel instant, not wait a tick.
                    if (!document.hidden) { this.loadThreads({ silent: true }); this.refreshActive(); }
                });
            },

            startRealtime() {
                const rt = window.kbRealtime;
                if (!rt) return;
                rt.onStateChange = () => { this.live = rt.isLive(); };
                rt.connect(window.KB_CONFIG.realtime || null);
                this.live = rt.isLive();
            },

            /**
             * One 4s heartbeat drives both fallbacks. When the socket is live the
             * open thread needs no polling at all and the inbox only needs an
             * occasional sweep (other threads are not subscribed); when it is down,
             * this is the only thing delivering messages.
             */
            startTicker() {
                if (this.ticker) return;
                this.ticker = setInterval(() => {
                    if (document.hidden) return;
                    this.tick += 1;
                    const isLive = window.kbRealtime ? window.kbRealtime.isLive() : false;
                    this.live = isLive;
                    if (!isLive && this.tick % 2 === 0) this.refreshActive();
                    if (this.tick % (isLive ? 15 : 5) === 0) this.loadThreads({ silent: true });
                }, 4000);
            },

            /* ── threads ─────────────────────────────────────────────────── */
            async loadThreads(opts = {}) {
                if (!opts.silent) { this.loading = true; this.error = ''; }
                const res = await window.kb.api('/chats');
                if (res.ok) {
                    const rows = window.kb.rows(res);
                    // The open thread keeps the local read state a silent refresh
                    // would otherwise resurrect as unread.
                    if (this.active) {
                        const fresh = rows.find(r => r.id === this.active.id);
                        if (fresh) { fresh.unread_count = 0; this.active = fresh; }
                    }
                    this.threads = rows;
                    this.chatUnread = rows.reduce((n, r) => n + (r.unread_count || 0), 0);
                    this.error = '';
                } else if (!opts.silent) {
                    this.error = window.kb.errorText(res, t('chats.load_error'));
                }
                this.loading = false;
            },

            async loadManaged() {
                // GET /me/communities returns only OWNED communities, which is exactly
                // the set whose channels this viewer may create/rename/delete.
                const res = await window.kb.api('/me/communities');
                this.managed = res.ok ? window.kb.rows(res) : [];
            },

            /** ?thread= / ?application= / ?collaboration= → open that conversation. */
            resolveDeepLink() {
                const params = new URLSearchParams(location.search);
                const byThread = params.get('thread');
                const byApplication = params.get('application');
                const byCollaboration = params.get('collaboration');
                let match = null;
                if (byThread) match = this.threads.find(th => th.id === byThread);
                else if (byApplication) match = this.threads.find(th => th.application_id === byApplication);
                else if (byCollaboration) match = this.threads.find(th => th.collaboration_id === byCollaboration);
                if (match) this.openThread(match);
            },

            async openThread(th) {
                if (this.active && this.active.id === th.id) return;
                this.leaveChannel();
                this.active = th;
                this.messages = [];
                this.draft = '';
                this.sendError = '';
                this.page = 1;
                this.lastPage = 1;
                // Deep-linkable without a page load, and refresh-safe.
                history.replaceState(null, '', window.kbPath('/chats') + '?thread=' + encodeURIComponent(th.id));
                // The shell badge drops by exactly what this thread was carrying;
                // GET messages marks it read server-side.
                this.chatUnread = Math.max(0, this.chatUnread - (th.unread_count || 0));
                th.unread_count = 0;
                await this.loadNewest();
                this.joinChannel(th);
            },

            closeThread() {
                this.leaveChannel();
                this.active = null;
                this.messages = [];
                history.replaceState(null, '', window.kbPath('/chats'));
            },

            /* ── channel subscription ────────────────────────────────────── */
            joinChannel(th) {
                if (!window.kbRealtime) return;
                this.channelName = window.kbRealtime.listen('chat.thread.' + th.id, 'message.sent', (payload) => {
                    const incoming = payload?.message;
                    if (!incoming || !this.active || incoming.thread_id !== this.active.id) return;
                    this.absorb(incoming);
                    // Reading it now means it must not linger as unread.
                    window.kb.api('/chats/' + this.active.id + '/read', { method: 'POST' });
                });
            },
            leaveChannel() {
                if (this.channelName && window.kbRealtime) window.kbRealtime.leave(this.channelName);
                this.channelName = null;
            },

            /* ── messages ────────────────────────────────────────────────── */
            /**
             * Open on the NEWEST messages. The endpoint paginates oldest-first, so
             * page 1 is the start of the conversation — for a long thread the newest
             * page is `last_page`, which costs one extra request to discover.
             */
            async loadNewest() {
                const first = await this.fetchPage(1);
                if (first && first.lastPage > 1) await this.fetchPage(first.lastPage);
            },

            async fetchPage(page) {
                this.msgLoading = true;
                this.msgError = '';
                const id = this.active?.id;
                const res = await window.kb.api('/chats/' + id + '/messages?per_page=50&page=' + page);
                this.msgLoading = false;
                if (!this.active || this.active.id !== id) return null; // switched away mid-flight
                if (!res.ok) {
                    this.msgError = window.kb.errorText(res, t('chats.messages_error'));
                    return null;
                }
                const rows = res.json?.data?.messages || [];
                const meta = res.json?.meta || {};
                if (page === (meta.last_page || page)) this.paint(rows, meta);
                return { rows, meta, lastPage: meta.last_page || 1 };
            },

            paint(rows, meta) {
                this.messages = rows;
                this.page = meta.current_page || 1;
                this.lastPage = meta.last_page || this.page;
                this.$nextTick(() => this.scrollToEnd());
            },

            async loadOlder() {
                if (this.page <= 1) return;
                const target = this.page - 1;
                const id = this.active?.id;
                this.msgLoading = true;
                const res = await window.kb.api('/chats/' + id + '/messages?per_page=50&page=' + target);
                this.msgLoading = false;
                if (!res.ok || !this.active || this.active.id !== id) return;
                const rows = res.json?.data?.messages || [];
                const box = this.$refs.scroll;
                const before = box ? box.scrollHeight : 0;
                this.messages = rows.concat(this.messages);
                this.page = target;
                // Keep the reader where they were instead of jumping to the top.
                this.$nextTick(() => { if (box) box.scrollTop = box.scrollHeight - before; });
            },

            /** Re-read the newest page and merge anything we have not seen. */
            async refreshActive() {
                if (!this.active || this.msgLoading) return;
                if (this.page !== this.lastPage) return; // reading history — do not yank
                const id = this.active.id;
                const res = await window.kb.api('/chats/' + id + '/messages?per_page=50&page=' + this.lastPage);
                if (!res.ok || !this.active || this.active.id !== id) return;
                const meta = res.json?.meta || {};
                this.lastPage = meta.last_page || this.lastPage;
                (res.json?.data?.messages || []).forEach(m => this.absorb(m, true));
            },

            /** Append one message unless it is already on screen (id-deduped). */
            absorb(message, quiet) {
                if (!message || this.messages.some(m => m.id === message.id)) return;
                const box = this.$refs.scroll;
                const wasAtEnd = !box || (box.scrollHeight - box.scrollTop - box.clientHeight) < 80;
                this.messages.push(message);
                this.touchThread(message);
                // Never yank someone reading back through the thread to the bottom.
                if (wasAtEnd || !quiet) this.$nextTick(() => this.scrollToEnd());
            },

            /** Keep the list row's preview and ordering honest without a refetch. */
            touchThread(message) {
                const th = this.threads.find(t2 => t2.id === message.thread_id);
                if (!th) return;
                th.last_message = { content: message.content, created_at: message.created_at };
                th.last_message_at = message.created_at;
                this.threads = this.threads
                    .slice()
                    .sort((a, b) => new Date(b.last_message_at || 0) - new Date(a.last_message_at || 0));
            },

            scrollToEnd() {
                const box = this.$refs.scroll;
                if (box) box.scrollTop = box.scrollHeight;
            },

            grow() {
                const el = this.$refs.draft;
                if (!el) return;
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 128) + 'px';
            },

            async send() {
                const content = this.draft.trim();
                if (content === '' || this.sending || !this.active) return;
                this.sending = true;
                this.sendError = '';

                // Kolab chats MUST post through the application endpoint: the generic
                // thread endpoint does not notify the other party for collaboration
                // threads (ChatService::threadRecipientIds returns [] for them), so
                // sending here would deliver a silent message.
                const isKolab = this.active.type === 'collaboration' && this.active.application_id;
                const path = isKolab
                    ? '/applications/' + this.active.application_id + '/messages'
                    : '/chats/' + this.active.id + '/messages';

                const res = await window.kb.api(path, { method: 'POST', body: { content } });
                this.sending = false;
                if (!res.ok) {
                    this.sendError = window.kb.errorText(res, t('chats.send_error'));
                    return;
                }
                this.draft = '';
                if (this.$refs.draft) this.$refs.draft.style.height = 'auto';
                const sent = res.json?.data;
                if (sent) { sent.is_own = true; this.absorb(sent); }
            },

            /* ── channel management ──────────────────────────────────────── */
            closeModal() { this.modal = ''; this.modalError = ''; this.modalBusy = false; },

            openCreate() {
                this.modal = 'create';
                this.modalName = '';
                this.modalCommunity = this.managed[0]?.id || '';
                this.modalError = '';
            },
            async createChannel() {
                const name = this.modalName.trim();
                if (name === '' || !this.modalCommunity) return;
                this.modalBusy = true;
                this.modalError = '';
                const res = await window.kb.api('/communities/' + this.modalCommunity + '/chats', {
                    method: 'POST', body: { name },
                });
                this.modalBusy = false;
                if (!res.ok) {
                    this.modalError = res.status === 422
                        ? t('chats.cap_reached')
                        : window.kb.errorText(res, t('chats.action_error'));
                    return;
                }
                this.closeModal();
                await this.loadThreads({ silent: true });
                const fresh = this.threads.find(th => th.id === res.json?.data?.id);
                if (fresh) this.openThread(fresh);
            },

            openRename() {
                this.modal = 'rename';
                this.modalName = this.active?.name || '';
                this.modalError = '';
            },
            async renameChannel() {
                const name = this.modalName.trim();
                if (name === '' || !this.active) return;
                this.modalBusy = true;
                this.modalError = '';
                const res = await window.kb.api('/chats/' + this.active.id, { method: 'PATCH', body: { name } });
                this.modalBusy = false;
                if (!res.ok) { this.modalError = window.kb.errorText(res, t('chats.action_error')); return; }
                this.active.name = res.json?.data?.name || name;
                const row = this.threads.find(th => th.id === this.active.id);
                if (row) row.name = this.active.name;
                this.closeModal();
            },

            openDelete() { this.modal = 'delete'; this.modalError = ''; },
            async deleteChannel() {
                if (!this.active) return;
                this.modalBusy = true;
                this.modalError = '';
                const id = this.active.id;
                const res = await window.kb.api('/chats/' + id, { method: 'DELETE' });
                this.modalBusy = false;
                if (!res.ok) { this.modalError = window.kb.errorText(res, t('chats.action_error')); return; }
                this.closeModal();
                this.closeThread();
                this.threads = this.threads.filter(th => th.id !== id);
            },

            async openMembers() {
                if (!this.active) return;
                this.modal = 'members';
                this.modalError = '';
                this.members = [];
                this.bannedIds = [];
                this.membersTotal = 0;
                this.membersLoading = true;
                const communityId = this.active.community_id;
                // The roster paginates on `limit` (NOT per_page) and caps at 100, and
                // it nests rows under data.members — kb.rows() cannot find those.
                const [members, bans] = await Promise.all([
                    window.kb.api('/communities/' + communityId + '/members?limit=100'),
                    window.kb.api('/chats/' + this.active.id + '/bans'),
                ]);
                this.membersLoading = false;
                if (!members.ok) { this.modalError = window.kb.errorText(members, t('chats.members_error')); return; }
                this.members = members.json?.data?.members || [];
                this.membersTotal = members.json?.data?.pagination?.total_count || this.members.length;
                this.bannedIds = bans.ok ? (bans.json?.data?.banned_profile_ids || []) : [];
            },
            isBanned(member) { return this.bannedIds.includes(member.profile_id); },
            async toggleBan(member) {
                if (!this.active) return;
                this.modalError = '';
                const banned = this.isBanned(member);
                const res = banned
                    ? await window.kb.api('/chats/' + this.active.id + '/bans/' + member.profile_id, { method: 'DELETE' })
                    : await window.kb.api('/chats/' + this.active.id + '/bans', { method: 'POST', body: { profile_id: member.profile_id } });
                if (!res.ok) { this.modalError = window.kb.errorText(res, t('chats.action_error')); return; }
                this.bannedIds = res.json?.data?.banned_profile_ids || [];
            },
        };
    }
</script>
@endpush
@endsection
