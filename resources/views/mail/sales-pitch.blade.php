{{--
    The outreach pitch. Generated copy sits between fixed furniture: the greeting,
    the estimate box, the CTA and the sign-off are the template's, never the model's.

    The estimate is rendered as its own arithmetic — attendees x average spend =
    total — rather than as a bare number. A business that can check the sum in its
    head is being given a reason to trust it; a number with no derivation is just a
    claim, and this one is about their money.
--}}
<x-mail::message>
{{ __('sales_mail.greeting', ['name' => $businessName]) }}

@if ($coverImageUrl)
<img src="{{ $coverImageUrl }}" alt="{{ $idea['title'] ?? '' }}" style="width:100%;max-width:560px;border-radius:8px;margin:0 0 18px;">
@endif

{{ $bodyMarkdown }}

<x-mail::panel>
**{{ __('sales_mail.estimate_heading') }}**

{{ __('sales_mail.estimate_line', [
    'attendees' => $attendees,
    'spend' => $avgSpend,
    'total' => $revenue,
]) }}

{{ __('sales_mail.estimate_disclaimer') }}
</x-mail::panel>

<x-mail::button :url="$ctaUrl">
{{ __('sales_mail.cta_button') }}
</x-mail::button>

{{ __('sales_mail.signoff') }}
</x-mail::message>
