<x-mail::message>
{{ $introMarkdown }}

<x-mail::button :url="$profileUrl">
{{ __('admin_mail.view_listing_button') }}
</x-mail::button>

{{ $nextStepsMarkdown }}

<x-mail::button :url="$createPasswordUrl">
{{ __('admin_mail.set_password_button') }}
</x-mail::button>

{{ $footerMarkdown }}
</x-mail::message>
