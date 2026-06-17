@extends('adminlte::page')

@section('title', $title ?? 'Kolabing Admin')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-start">
        <div>
            <h1 class="mb-1">@yield('page_title', $title ?? 'Kolabing Admin')</h1>
            @hasSection('page_subtitle')
                <p class="text-muted mb-0">@yield('page_subtitle')</p>
            @endif
        </div>

        <div class="mt-2 mt-md-0 d-flex flex-wrap align-items-center">
            <div class="mr-2 mb-2 mb-md-0">
                @yield('page_actions')
            </div>

            <form method="post" action="{{ route('admin.logout') }}" class="mb-2 mb-md-0">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    Logout
                </button>
            </form>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <x-adminlte-alert theme="success" title="Success" dismissable>
            {{ session('status') }}
        </x-adminlte-alert>
    @endif

    @if ($errors->any())
        <x-adminlte-alert theme="danger" title="Validation Error" dismissable>
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-adminlte-alert>
    @endif

    @yield('admin_content')
@stop

{{-- Lucide icons are rendered SERVER-SIDE as inline SVG via
     @include('admin.partials.lucide', ...) (blade-lucide-icons / blade-icons).
     No external CDN and no client-side createIcons() timing, so the icons render
     identically locally and in production. The same Lucide slugs are what the
     mobile app renders, so admins see exactly what users see.
     window.renderLucide is kept as a no-op shim so any legacy caller is safe. --}}
@section('css')
    @parent
    <style>
        .lucide-icon { display: inline-block; vertical-align: middle; width: 1em; height: 1em; stroke-width: 2; }
        .lucide-22 { width: 22px; height: 22px; }
        .lucide-24 { width: 24px; height: 24px; }
        .lucide-28 { width: 28px; height: 28px; }
        .lucide-missing { opacity: .35; }
    </style>
@stop

@section('js')
    @parent
    <script>
        // Icons are inline SVG now; nothing to render client-side. Shim kept so
        // any older view that calls window.renderLucide() does not error.
        window.renderLucide = function () {};
    </script>
@stop
