@props(['kolab'])

@php
    /**
     * One public Kolab card. Used by the homepage strip, the /kolabs listing and the
     * "also open" row on a detail page, so all three stay identical — including the
     * identity rule, which is the part most likely to drift if this were copied.
     */
    use App\Enums\IntentType;
    use App\Support\OfferOptionLabels;
    use App\Support\PublicKolabLink;
    use App\Support\PublicKolabPoster;

    $poster = PublicKolabPoster::describe($kolab);

    // What this Kolab puts on the table, named concretely (§2.3 / §3.3: never the
    // abstract word "match"). A community asks via needs[] and repays via
    // offers_in_return[]; a business offers via offering[] and asks via expects[].
    $isCommunityAsk = $kolab->intent_type === IntentType::CommunitySeeking;

    $gives = $isCommunityAsk
        ? OfferOptionLabels::many('deliverable', $kolab->offers_in_return)
        : OfferOptionLabels::many('offering', $kolab->offering);

    $wants = $isCommunityAsk
        ? OfferOptionLabels::many('need', $kolab->needs)
        : OfferOptionLabels::many('deliverable', $kolab->expects);

    $kind = match ($kolab->intent_type) {
        IntentType::CommunitySeeking => 'Community looking for a partner',
        IntentType::VenuePromotion => 'Venue offering space',
        IntentType::ProductPromotion => 'Product looking for communities',
    };

    $reach = $kolab->typical_attendance ?? $kolab->community_size;
@endphp

<a href="{{ PublicKolabLink::urlFor($kolab) }}"
   class="group flex flex-col rounded-3xl border border-off-black/10 bg-white p-6 transition hover:border-off-black/30">
    <p class="text-xs font-bold uppercase tracking-[0.12em] text-primary-dark">{{ $kind }}</p>

    <h3 class="mt-2 font-display text-lg font-black leading-snug text-off-black">{{ $kolab->title }}</h3>

    <p class="mt-1 text-sm text-off-black/60">{{ $poster['description'] }}</p>

    @if ($gives !== [])
        <p class="mt-4 text-sm text-off-black/80">
            <span class="font-bold text-off-black">Offers</span>
            {{ implode(' · ', array_slice($gives, 0, 3)) }}
        </p>
    @endif

    @if ($wants !== [])
        <p class="mt-1 text-sm text-off-black/80">
            <span class="font-bold text-off-black">Looking for</span>
            {{ implode(' · ', array_slice($wants, 0, 3)) }}
        </p>
    @endif

    <div class="mt-4 flex flex-wrap gap-x-3 gap-y-1 text-xs font-semibold text-off-black/50">
        @if ($reach)
            <span>{{ $reach }} people</span>
        @endif
        @if ($kolab->availability_end)
            <span>Open until {{ $kolab->availability_end->translatedFormat('j M') }}</span>
        @endif
    </div>

    <span class="mt-auto pt-4 text-sm font-bold text-off-black group-hover:underline">See the Kolab →</span>
</a>
