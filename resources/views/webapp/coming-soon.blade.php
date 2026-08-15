@extends('webapp.layout')
@section('title', 'Coming soon')

@section('body')
<div x-data="{ init() { window.kb.requireAuth(); } }" x-init="init()">
    @include('webapp.partials.nav')

    <main class="max-w-2xl mx-auto px-5 py-16 text-center">
        <h1 class="font-montserrat font-black text-2xl tracking-tight">Coming soon to the web</h1>
        <p class="text-off-black/60 mt-2">Creating and browsing Kolabs is landing on the web shortly. For now, it's all in the app.</p>
        <a href="/welcome" class="inline-block mt-5 rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2">Open the app</a>
        <a href="/dashboard" class="inline-block mt-3 text-sm text-off-black/60 underline w-full">Back home</a>
    </main>
</div>
@endsection
