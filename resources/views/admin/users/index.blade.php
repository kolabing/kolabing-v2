@extends('admin.layout', ['title' => 'Users'])

@section('page_title', 'Users')
@section('page_subtitle', 'Manage application profiles from a fixed maintainer panel.')

@section('page_actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="fas fa-user-plus mr-1"></i>
        Create User
    </a>
@endsection

@section('admin_content')
    {{-- The bulk bar and the row checkboxes are one form (#256). Two submit
         buttons with their own formaction, so choosing an action needs no
         JavaScript — the script below only adds select-all, the live count and
         the disabled state. With JS off, ticking boxes and pressing a button
         still works. The per-row Edit / Deactivate / Delete forms live OUTSIDE
         this form, via the form="" attribute, because HTML forbids nesting. --}}
    <form method="POST" id="bulk-users-form" action="{{ route('admin.users.bulk-deactivate') }}">
        @csrf

        <div class="card mb-3" id="bulk-bar">
            <div class="card-body py-2 d-flex flex-wrap align-items-center">
                <span class="mr-3 text-muted" id="bulk-count">No accounts selected</span>

                <button type="submit"
                        formaction="{{ route('admin.users.bulk-activate') }}"
                        class="btn btn-sm btn-outline-success mr-2"
                        data-bulk-submit>
                    Activate selected
                </button>

                <button type="submit"
                        formaction="{{ route('admin.users.bulk-deactivate') }}"
                        class="btn btn-sm btn-outline-danger"
                        data-bulk-submit
                        data-confirm-deactivate>
                    Deactivate selected
                </button>

                <small class="text-muted ml-auto">Selection applies to this page only.</small>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 2.5rem;">
                                    <input type="checkbox" id="bulk-select-all" aria-label="Select every account on this page">
                                </th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Phone</th>
                                <th>Verified</th>
                                <th>Status</th>
                                <th class="text-right pr-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($profiles as $profile)
                                @php
                                    $label = $profile->businessProfile?->name
                                        ?? $profile->communityProfile?->name
                                        ?? $profile->email;
                                @endphp
                                <tr class="{{ $profile->is_active ? '' : 'table-secondary text-muted' }}">
                                    <td>
                                        <input type="checkbox"
                                               name="profile_ids[]"
                                               value="{{ $profile->id }}"
                                               class="bulk-select-row"
                                               aria-label="Select {{ $label }}">
                                    </td>
                                    <td>
                                        <div class="font-weight-bold">{{ $label }}</div>
                                        <small class="text-muted">{{ $profile->id }}</small>
                                    </td>
                                    <td>{{ $profile->email }}</td>
                                    <td>
                                        <span class="badge badge-light text-uppercase">{{ $profile->user_type->value }}</span>
                                    </td>
                                    <td>{{ $profile->phone_number ?: '—' }}</td>
                                    <td>
                                        <span class="badge {{ $profile->email_verified_at ? 'badge-success' : 'badge-secondary' }}">
                                            {{ $profile->email_verified_at ? 'Verified' : 'Pending' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $profile->is_active ? 'badge-success' : 'badge-danger' }}">
                                            {{ $profile->is_active ? 'Active' : 'Passive' }}
                                        </span>
                                    </td>
                                    <td class="text-right pr-4">
                                        <a href="{{ route('admin.users.edit', $profile) }}" class="btn btn-sm btn-outline-primary">Edit</a>

                                        @if ($profile->is_active)
                                            <button type="submit"
                                                    form="row-deactivate-{{ $profile->id }}"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Hide from the app and block sign-in">Deactivate</button>
                                        @else
                                            <button type="submit"
                                                    form="row-activate-{{ $profile->id }}"
                                                    class="btn btn-sm btn-outline-success"
                                                    title="Restore full access">Activate</button>
                                        @endif

                                        @if ($profile->user_type->value === 'business')
                                            @php $subActive = $profile->subscription?->status?->value === 'active'; @endphp
                                            @if ($subActive)
                                                <button type="submit"
                                                        form="row-sub-revoke-{{ $profile->id }}"
                                                        class="btn btn-sm btn-outline-warning"
                                                        title="Revoke subscription">Revoke sub</button>
                                            @else
                                                <button type="submit"
                                                        form="row-sub-grant-{{ $profile->id }}"
                                                        class="btn btn-sm btn-outline-success"
                                                        title="Grant 12 months of access">Grant sub</button>
                                            @endif
                                        @endif

                                        <button type="submit"
                                                form="row-destroy-{{ $profile->id }}"
                                                class="btn btn-sm btn-outline-danger">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($profiles->hasPages())
                <div class="card-footer clearfix">
                    {{ $profiles->links('pagination::bootstrap-4') }}
                </div>
            @endif
        </div>
    </form>

    {{-- Per-row forms, kept out of the bulk form because HTML does not allow a
         form inside a form. The buttons above reference them by id. --}}
    @foreach ($profiles as $profile)
        <form method="POST" id="row-deactivate-{{ $profile->id }}" action="{{ route('admin.users.deactivate', $profile) }}"
              onsubmit="return confirm('Deactivate this account? It disappears from the app and cannot sign in. Nothing is deleted, and you can switch it back on.');">
            @csrf
        </form>
        <form method="POST" id="row-activate-{{ $profile->id }}" action="{{ route('admin.users.activate', $profile) }}">
            @csrf
        </form>
        @if ($profile->user_type->value === 'business')
            <form method="POST" id="row-sub-grant-{{ $profile->id }}" action="{{ route('admin.users.subscription.grant', $profile) }}">
                @csrf
            </form>
            <form method="POST" id="row-sub-revoke-{{ $profile->id }}" action="{{ route('admin.users.subscription.revoke', $profile) }}"
                  onsubmit="return confirm('Revoke subscription access for this user?');">
                @csrf
            </form>
        @endif
        <form method="POST" id="row-destroy-{{ $profile->id }}" action="{{ route('admin.users.destroy', $profile) }}"
              onsubmit="return confirm('Delete this user? They will be soft-deleted.');">
            @csrf
            @method('DELETE')
        </form>
    @endforeach

    <script>
        (function () {
            var form = document.getElementById('bulk-users-form');
            if (!form) { return; }

            var selectAll = document.getElementById('bulk-select-all');
            var rows = Array.prototype.slice.call(form.querySelectorAll('.bulk-select-row'));
            var counter = document.getElementById('bulk-count');
            var buttons = Array.prototype.slice.call(form.querySelectorAll('[data-bulk-submit]'));

            function selected() {
                return rows.filter(function (row) { return row.checked; });
            }

            function sync() {
                var n = selected().length;

                counter.textContent = n === 0
                    ? 'No accounts selected'
                    : n + (n === 1 ? ' account selected' : ' accounts selected');

                buttons.forEach(function (button) { button.disabled = n === 0; });

                if (selectAll) {
                    selectAll.checked = n > 0 && n === rows.length;
                    // Neither all nor none: show the third state rather than lying.
                    selectAll.indeterminate = n > 0 && n < rows.length;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    rows.forEach(function (row) { row.checked = selectAll.checked; });
                    sync();
                });
            }

            rows.forEach(function (row) { row.addEventListener('change', sync); });

            form.addEventListener('submit', function (event) {
                var n = selected().length;

                if (n === 0) {
                    event.preventDefault();
                    return;
                }

                // event.submitter is the button that was pressed, so the confirm
                // names the real count and only guards the destructive action.
                var submitter = event.submitter;
                if (submitter && submitter.hasAttribute('data-confirm-deactivate')) {
                    var message = n === 1
                        ? 'Deactivate 1 account? It disappears from the app and cannot sign in. Nothing is deleted.'
                        : 'Deactivate ' + n + ' accounts? They disappear from the app and cannot sign in. Nothing is deleted.';

                    if (!window.confirm(message)) {
                        event.preventDefault();
                    }
                }
            });

            sync();
        })();
    </script>
@endsection
