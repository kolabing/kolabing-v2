{{-- Business-side density proof (E1/E2): the local demand a venue can fill.
     Vars: $city (string), $total (int listed communities). --}}
<div class="mt-12 rounded-[2rem] border border-off-black/10 bg-primary/25 p-8 md:flex md:items-center md:justify-between md:gap-10">
    <div>
        <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">For venues</p>
        <h2 class="mt-3 font-montserrat text-2xl font-black uppercase leading-tight md:text-3xl">{{ $total }} active communities in {{ $city }} need a space to host.</h2>
        <p class="mt-3 max-w-xl text-off-black/75">Every one of them runs real events and is looking for somewhere to hold them. That is {{ $total }} standing chances to fill your venue on a quiet night, with a crowd that already wants to be there.</p>
    </div>
    <div class="mt-6 shrink-0 md:mt-0">
        <a href="{{ config('rankings.business_cta_url') }}" class="inline-block rounded-full bg-off-black px-7 py-3 text-center font-bold text-primary transition hover:bg-off-black/90">See the demand near you</a>
    </div>
</div>
