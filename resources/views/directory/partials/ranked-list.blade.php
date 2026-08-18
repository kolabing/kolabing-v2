{{-- The ranked list (joiner-first hero). $ranked = Collection<CrmAccount>.
     $counts (optional) = [listing_id => ['vouches'=>int,'verified_members'=>int,'verified'=>bool]]. --}}
<ol class="mt-8 space-y-4">
    @foreach ($ranked as $i => $a)
        @include('directory.partials.community-card', ['a' => $a, 'i' => $i, 'counts' => $counts ?? []])
    @endforeach
</ol>
