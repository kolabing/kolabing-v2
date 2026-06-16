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

{{-- Lucide icon font/runtime, loaded admin-wide so any view can render
     <i data-lucide="slug"></i>. The app renders the same Lucide icons by slug,
     so what an admin sees here matches what the mobile app shows. --}}
@section('js')
    @parent
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        window.renderLucide = function () {
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        };
        document.addEventListener('DOMContentLoaded', window.renderLucide);
        window.renderLucide();
    </script>
@stop
