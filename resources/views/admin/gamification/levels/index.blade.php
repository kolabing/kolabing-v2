@extends('admin.layout', ['title' => 'XP levels'])

@section('page_title', 'XP levels')
@section('page_subtitle', 'The ladder rendered by the app. Bands must be contiguous and non-overlapping, with exactly one open-ended top level.')

@section('admin_content')
    @if (! empty($ladderErrors))
        <x-adminlte-alert theme="danger" title="Ladder integrity issues" dismissable>
            <ul class="mb-0 pl-3">
                @foreach ($ladderErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-adminlte-alert>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>Title</th>
                            <th class="text-center">XP range</th>
                            <th class="text-center">Color</th>
                            <th class="text-right pr-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($levels as $level)
                            <tr>
                                <td class="text-center font-weight-bold">{{ $level->number }}</td>
                                <td>{{ $level->title }}</td>
                                <td class="text-center">
                                    {{ $level->min_xp }} — {{ $level->max_xp ?? '∞' }}
                                </td>
                                <td class="text-center">
                                    <span style="display:inline-block; width:18px; height:18px; vertical-align:middle; border-radius:4px; background:{{ $level->color }};"></span>
                                    <code class="ml-1">{{ $level->color }}</code>
                                </td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.gamification.levels.edit', $level) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No XP levels defined. Run <code>php artisan db:seed --class=XpLevelSeeder</code>.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
