{{-- Past-due alert. Included from the sidebar partial so every authed page gets it
     from a single place, and it inherits the page root's kbShell() scope.
     Fixed to the bottom rather than the top: it must never cover a page heading,
     and it only ever renders when Stripe has failed to charge the card. --}}
<div x-data="{ dismissed: sessionStorage.getItem('kb_pastdue_dismissed') === '1' }"
     x-show="shellReady && pastDue && !dismissed" x-cloak
     class="fixed bottom-4 inset-x-4 md:inset-x-auto md:right-6 md:w-[420px] z-50 kb-fade-up-fast">
    <div class="bg-white border border-[#BA1A1A]/25 rounded-3xl shadow-cardhover p-5">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-full bg-[#F8D7DA] flex items-center justify-center shrink-0">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#721C24" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="10"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-ink">{{ __('webapp.subscription.past_due_title') }}</p>
                <p class="text-[13px] text-body mt-1 leading-relaxed">{{ __('webapp.subscription.past_due_desc') }}</p>
            </div>
            <button type="button" @click="dismissed = true; sessionStorage.setItem('kb_pastdue_dismissed', '1')"
                    aria-label="{{ __('webapp.common.close') }}" class="text-muted hover:text-ink shrink-0">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <button type="button" @click="openBillingPortal()"
                class="w-full h-11 mt-4 rounded-pill bg-ink text-primary text-[13px] font-bold hover:-translate-y-px transition">{{ __('webapp.subscription.past_due_cta') }}</button>
    </div>
</div>
