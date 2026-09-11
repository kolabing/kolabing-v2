<x-mail::message>
# {{ __('Welcome to Kolabing, :name!', ['name' => $name]) }}

{{ __("We've listed you on Kolabing so :who can already find you.", [
    'who' => $isBusiness ? __('communities') : __('businesses'),
]) }}

<x-mail::button :url="$profileUrl">
{{ __('View your listing') }}
</x-mail::button>

## {{ __("What happens next") }}

@if ($isBusiness)
{{ __('Communities browsing Kolabing can already see your listing and reach out to collaborate. Set your password below to manage your profile, review requests, and reply.') }}
@else
{{ __('Businesses browsing Kolabing can already see your listing and reach out to collaborate. Set your password below to manage your profile, review requests, and reply.') }}
@endif

<x-mail::button :url="$createPasswordUrl">
{{ __('Set your password') }}
</x-mail::button>

{{ __('This link is only valid for a limited time — if it expires, you can always request a new one from the sign-in screen.') }}

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
