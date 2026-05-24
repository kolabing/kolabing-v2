@extends('admin.layout', ['title' => 'Dashboard'])

@section('page_title', 'Dashboard')
@section('page_subtitle', 'Maintainer home for Kolabing operations.')

@section('admin_content')
    <div class="row">
        <div class="col-12">
            <x-adminlte-card title="Admin Panel" theme="lightblue" icon="fas fa-shield-halved">
                <p class="mb-0">This dashboard is intentionally simple for now. Use the left sidebar to manage application users.</p>
            </x-adminlte-card>
        </div>
    </div>
@endsection
