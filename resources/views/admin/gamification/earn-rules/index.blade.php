@extends('admin.layout', ['title' => 'XP earn rules'])

@section('page_title', 'XP earn rules')
@section('page_subtitle', 'Per-action XP awards. Editing a row here changes both what the ledger writes AND the value the app renders.')

@section('admin_content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Label</th>
                            <th class="text-center">Points</th>
                            <th class="text-center">Active</th>
                            <th class="text-right pr-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rules as $rule)
                            <tr>
                                <td>
                                    <code>{{ $rule->event_type }}</code>
                                </td>
                                <td>{{ $rule->label }}</td>
                                <td class="text-center">
                                    <span class="font-weight-bold">{{ $rule->points }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($rule->is_active)
                                        <span class="badge badge-success">ACTIVE</span>
                                    @else
                                        <span class="badge badge-secondary">INACTIVE</span>
                                    @endif
                                </td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.gamification.earn-rules.edit', $rule) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No XP earn rules defined. Run <code>php artisan db:seed --class=XpEarnRuleSeeder</code>.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
