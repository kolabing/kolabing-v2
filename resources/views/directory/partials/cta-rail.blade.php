{{-- The two-sided secondary CTA rail: one-field community claim + venue call.
     Vars: $city (string), $source (string, for claim attribution). --}}
<div id="claim" class="mt-12 grid gap-6 md:grid-cols-2">
    <div class="rounded-[2rem] border border-off-black/10 bg-primary/25 p-8">
        <h2 class="font-montserrat text-2xl font-black uppercase leading-tight">Run a community in {{ $city }}?</h2>
        <p class="mt-3 text-off-black/75">Claim your free listing. We will feature you and introduce you to venues near you that want to host your events. No call needed.</p>
        <form method="POST" action="{{ route('directory.claim') }}" class="mt-6 flex flex-col gap-3">
            @csrf
            <input type="hidden" name="city" value="{{ $city }}">
            <input type="hidden" name="source" value="{{ $source }}">
            {{-- Honeypot: real users never fill this. --}}
            <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
            <input type="text" name="community_name" id="claim-name" required placeholder="Your community name"
                   class="w-full rounded-full border border-off-black/15 bg-white px-5 py-3 text-off-black placeholder:text-off-black/40 focus:border-off-black focus:outline-none">
            <div class="flex flex-col gap-3 sm:flex-row">
                <input type="email" name="email" required placeholder="you@community.com"
                       class="w-full rounded-full border border-off-black/15 bg-white px-5 py-3 text-off-black placeholder:text-off-black/40 focus:border-off-black focus:outline-none">
                <button type="submit" class="shrink-0 rounded-full bg-off-black px-7 py-3 font-bold text-primary transition hover:bg-off-black/90">Claim listing</button>
            </div>
        </form>
        @if (session('claimed'))
            <p class="mt-3 text-sm font-semibold text-off-black">Thanks — we will be in touch about your listing.</p>
        @endif
    </div>
    <div class="rounded-[2rem] border border-off-black/10 bg-white p-8">
        <h2 class="font-montserrat text-2xl font-black uppercase leading-tight">Run a venue in {{ $city }}?</h2>
        <p class="mt-3 text-off-black/70">See how many communities near you are looking for a space, then book a 15-minute call to start hosting the ones that fit.</p>
        <a href="{{ config('rankings.business_cta_url') }}" class="mt-6 inline-block rounded-full border border-off-black px-7 py-3 font-bold text-off-black transition hover:bg-off-black hover:text-primary">Book a venue call</a>
    </div>
</div>
