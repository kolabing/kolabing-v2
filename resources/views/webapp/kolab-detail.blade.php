@extends('webapp.layout')
@section('title', __('webapp.intent.kolab'))

@section('body')
{{-- Deep-linked Kolab: the design renders a Kolab as an overlay card, so the page
     is the app shell with that same overlay opened on load. --}}
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), kbModalMixin(), kolabDetailPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'kolabs'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10">
        <h1 class="font-anton text-[28px] tracking-[1px] text-ink">{{ __('webapp.intent.kolab') }}</h1>
        <template x-if="pageError">
            <div class="mt-5 rounded-2xl bg-[#F8D7DA] text-[#721C24] text-sm px-4 py-3 whitespace-pre-line" x-text="pageError"></div>
        </template>
        <template x-if="!pageError">
            <p class="mt-4 text-sm text-muted">{{ __('webapp.common.loading') }}</p>
        </template>
        <a href="{{ $base }}/feed" class="inline-flex items-center h-11 px-6 mt-5 rounded-pill bg-white border border-line text-sm font-bold hover:border-ink transition">{{ __('webapp.detail.back_to_explore') }}</a>
    </div>
    </main>

    @include('webapp.partials.kolab-modals')
</div>

@push('scripts')
<script>
    function kolabDetailPage() {
        return {
            // Modal state owned by kbModalMixin().
            dk: null, detailLoading: true, detailError: '', appliedIds: [], pageError: '',
            applyOpen: false, applyErr: '', applyBusy: false, applySuccess: false,
            applyDates: [], applyStart: '10:00', applyEnd: '13:00', applyMsg: '', applyNotes: '',
            timeOptions: ['7:00','8:00','9:00','10:00','11:00','12:00','13:00','14:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00'],

            id: location.pathname.slice((window.KB_BASE || '').length).split('/')[2],

            async init() {
                if (!window.kb.requireAuth()) return;
                if (!await this.loadShell()) return;
                await Promise.all([this.openDetail(this.id), this.loadAppliedIds()]);
                if (!this.dk) this.pageError = this.detailError || t('detail.not_found');
            },
            // Closing the overlay on a deep link returns to Explore rather than
            // leaving the user on an empty shell.
            closeDetail() { window.nav('/feed'); },
            closeSuccess() { this.applySuccess = false; },
        };
    }
</script>
@endpush
@endsection
