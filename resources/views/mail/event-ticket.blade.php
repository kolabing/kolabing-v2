<x-mail::message>
# {{ __("You're in") }}

{{ __('Your place at :event is confirmed.', ['event' => $eventName]) }}

@if ($startsAt)
**{{ __('When') }}** — {{ $startsAt->translatedFormat('l j F Y') }}@if ($startsAt->format('H:i') !== '00:00') {{ __('at') }} {{ $startsAt->format('H:i') }}@endif
@endif
@if ($where)
**{{ __('Where') }}** — {{ $where }}
@endif
@if ($hostName)
**{{ __('Host') }}** — {{ $hostName }}
@endif

{{-- The code is the ticket. It is printed as text because text is the one thing
     that survives every mail client, and because a doorkeeper can type it when a
     screen will not scan. --}}
**{{ __('Your ticket code') }}**

## {{ $ticketCode }}

<x-mail::button :url="$ticketUrl">
{{ __('Show my ticket') }}
</x-mail::button>

{{ __('Open the ticket on your phone at the door — it shows a QR code the host scans. If the QR will not scan, read out the code above.') }}

{{ __('Cannot make it? Open your ticket and cancel, so your place goes to the next person waiting.') }}

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
