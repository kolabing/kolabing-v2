<x-mail::message>
# {{ $cancelled ? 'This event has been cancelled' : $event->name }}

{{ $recipientName ? 'Hi '.$recipientName.',' : 'Hi,' }}

@if ($cancelled)
{{ $event->name }} is no longer happening, and it has been removed from your calendar.
@else
You're going. The attached invitation adds it to your calendar, and we'll remind you a day before and an hour before it starts.
@endif

@if (! $cancelled)
**When:** {{ optional($event->starts_at)->format('D j M Y, H:i') ?? $event->event_date }}
@php
    $where = collect([$event->location, $event->address])
        ->filter(fn ($part) => filled($part))
        ->unique()
        ->implode(', ');
@endphp
@if ($where !== '')
**Where:** {{ $where }}
@endif

<x-mail::button :url="rtrim(config('app.url'), '/').'/events/'.$event->id">
View the event
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
