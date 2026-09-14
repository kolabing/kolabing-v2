@extends('admin.layout', ['title' => 'Email Templates'])

@section('page_title', 'Quick-Add Welcome Email — Languages')
@section('page_subtitle', 'Content for the email sent after a maintainer sends the welcome email from a listing.')

@section('page_actions')
    <a href="{{ route('admin.email-templates.create') }}" class="btn btn-primary">
        <i class="fas fa-plus mr-1"></i>
        Add language
    </a>
@endsection

@section('admin_content')
    <div class="card card-primary card-outline">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Language</th>
                        <th>Locale</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td>{{ $template->label }}</td>
                            <td><code>{{ $template->locale }}</code></td>
                            <td>{{ $template->subject }}</td>
                            <td>
                                @if ($template->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.email-templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No languages yet — add one to start sending welcome emails.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
