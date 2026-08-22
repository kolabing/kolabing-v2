@php
    $stageColors = [
        'Target' => '#6c757d', 'Contacted' => '#3d7fd6', 'Interested' => '#8b3fd0',
        'Negotiating' => '#e07b00', 'Onboarded' => '#1f9d57', 'Rejected' => '#c0392b',
    ];
    $c = $stageColors[$stage] ?? '#6c757d';
@endphp
<span class="badge" style="background:{{ $c }};color:#fff;font-weight:600;{{ $small ?? false ? 'font-size:11px' : '' }}">{{ $stage }}</span>
