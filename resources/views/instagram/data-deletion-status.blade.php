<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Instagram data deletion · Kolabing</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 36rem; margin: 3rem auto; padding: 0 1rem; color: #111; line-height: 1.5; }
        code { background: #f3f3f3; padding: .1rem .3rem; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Instagram data deletion</h1>
    <p>Confirmation code: <code>{{ $deletion->confirmation_code }}</code></p>
    @if ($deletion->status === \App\Models\InstagramDataDeletionRequest::STATUS_COMPLETED)
        <p>Completed on {{ $deletion->completed_at?->toDayDateTimeString() }} (UTC).</p>
        <p>Kolabing deleted the Instagram connection linked to your account and every photo and video that was imported from Instagram into Kolabing ({{ $deletion->media_deleted }} item(s)).</p>
    @else
        <p>Your request was received and is being processed.</p>
    @endif
    <p>Questions: <a href="mailto:hello@kolabing.com">hello@kolabing.com</a></p>
</body>
</html>
