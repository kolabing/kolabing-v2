<x-mail::message>
# {{ __('You have been invited to join :community', ['community' => $communityName]) }}

@if ($inviterName)
{{ __(':inviter invited you to join :community on Kolabing.', ['inviter' => $inviterName, 'community' => $communityName]) }}
@else
{{ __('You have been invited to join :community on Kolabing.', ['community' => $communityName]) }}
@endif

<x-mail::button :url="$joinUrl">
{{ __('View the community') }}
</x-mail::button>

{{ __('This invitation is valid until :date.', ['date' => $expiresAt->toFormattedDateString()]) }}

{{ __('If you were not expecting this, you can ignore this email.') }}

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
