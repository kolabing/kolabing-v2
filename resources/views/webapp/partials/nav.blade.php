{{-- Shared top bar for authenticated web-app pages. Expects Alpine scope to expose `logout()` (falls back to window.kb.logout). --}}
<header class="border-b border-off-black/10 bg-off-white/90 backdrop-blur sticky top-0 z-10">
    <div class="max-w-4xl mx-auto px-5 h-14 flex items-center justify-between">
        <a href="/dashboard" class="font-montserrat font-black text-lg tracking-tight">Kolabing</a>
        <nav class="flex items-center gap-4 text-sm">
            <a href="/dashboard" class="{{ ($active ?? '') === 'dashboard' ? 'font-semibold' : 'text-off-black/60' }}">Home</a>
            <a href="/feed" class="{{ ($active ?? '') === 'feed' ? 'font-semibold' : 'text-off-black/60' }}">Feed</a>
            <a href="/kolabs" class="{{ ($active ?? '') === 'kolabs' ? 'font-semibold' : 'text-off-black/60' }}">Kolabs</a>
            <a href="/subscription" class="{{ ($active ?? '') === 'subscription' ? 'font-semibold' : 'text-off-black/60' }}">Plan</a>
            <button type="button" @click="window.kb.logout()" class="text-off-black/60 hover:text-off-black">Log out</button>
        </nav>
    </div>
</header>
