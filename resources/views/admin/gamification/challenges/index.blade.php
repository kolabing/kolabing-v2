@extends('admin.layout', ['title' => 'Challenges'])

@section('page_title', 'Challenges')
@section('page_subtitle', 'Global system challenges. Custom event-scoped challenges are organizer-managed.')

@section('page_actions')
    <a href="{{ route('admin.gamification.challenges.create') }}" class="btn btn-primary">
        <i class="fas fa-plus mr-1"></i>
        New
    </a>
@endsection

@section('admin_content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.gamification.challenges.index') }}" class="form-row align-items-end">
                <div class="form-group col-md-4">
                    <label for="category" class="small text-muted mb-1">Category</label>
                    <select name="category" id="category" class="form-control">
                        <option value="">All</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->value }}" {{ $filters['category'] === $c->value ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $c->value)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="difficulty" class="small text-muted mb-1">Difficulty</label>
                    <select name="difficulty" id="difficulty" class="form-control">
                        <option value="">All</option>
                        @foreach ($difficulties as $d)
                            <option value="{{ $d->value }}" {{ $filters['difficulty'] === $d->value ? 'selected' : '' }}>
                                {{ ucfirst($d->value) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="audience" class="small text-muted mb-1">Audience</label>
                    <select name="audience" id="audience" class="form-control">
                        <option value="">All</option>
                        @foreach ($audiences as $a)
                            <option value="{{ $a->value }}" {{ $filters['audience'] === $a->value ? 'selected' : '' }}>
                                {{ $a->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <button type="submit" class="btn btn-primary btn-block">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Difficulty</th>
                            <th>Audience</th>
                            <th class="text-center">Pts</th>
                            <th class="text-right pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($challenges as $c)
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $c->name }}</div>
                                    @if ($c->description)
                                        <small class="text-muted">{{ \Illuminate\Support\Str::limit($c->description, 80) }}</small>
                                    @endif
                                </td>
                                <td><code>{{ $c->category?->value ?? '—' }}</code></td>
                                <td>{{ ucfirst($c->difficulty->value) }}</td>
                                <td>
                                    <span class="badge badge-light text-uppercase">{{ $c->audience?->label() ?? 'Both' }}</span>
                                </td>
                                <td class="text-center font-weight-bold">{{ $c->points }}</td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.gamification.challenges.edit', $c) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.gamification.challenges.destroy', $c) }}" class="d-inline" onsubmit="return confirm('Delete this challenge?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No challenges match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($challenges->hasPages())
            <div class="card-footer clearfix">
                {{ $challenges->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
@endsection
