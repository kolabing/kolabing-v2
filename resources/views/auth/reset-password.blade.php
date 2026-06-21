@php
    $title = 'Reset password';
    $description = 'Set a new password for your Kolabing account.';
    $canonical = route('password.reset');
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical">
    <section class="mx-auto flex max-w-md flex-col px-6 py-20">
        <h1 class="font-montserrat text-3xl font-black uppercase md:text-4xl">Reset password</h1>

        @if (session('status'))
            <div class="mt-8 rounded-3xl border border-off-black/10 bg-primary/20 p-8 shadow-sm">
                <h2 class="text-xl font-bold">All set</h2>
                <p class="mt-3 text-off-black/70">{{ session('status') }}</p>
                <p class="mt-3 text-off-black/70">You can now open the Kolabing app and sign in with your new password.</p>
            </div>
        @else
            <p class="mt-4 text-off-black/70">Choose a new password for
                <span class="font-semibold text-off-black">{{ $email }}</span>.
            </p>

            @if ($errors->any())
                <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.reset.update') }}" class="mt-8 space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div>
                    <label for="password" class="block text-sm font-bold text-off-black">New password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                        minlength="8" autofocus
                        class="mt-2 w-full rounded-2xl border-off-black/15 bg-white px-4 py-3 text-off-black shadow-sm focus:border-off-black focus:ring-off-black">
                    <p class="mt-2 text-xs text-off-black/50">At least 8 characters.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-bold text-off-black">Confirm new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required
                        autocomplete="new-password" minlength="8"
                        class="mt-2 w-full rounded-2xl border-off-black/15 bg-white px-4 py-3 text-off-black shadow-sm focus:border-off-black focus:ring-off-black">
                </div>

                <button type="submit"
                    class="inline-flex w-full justify-center rounded-full bg-off-black px-5 py-3 font-bold text-white transition hover:bg-off-black/90">
                    Reset password
                </button>
            </form>
        @endif

        <a href="{{ route('support') }}" class="mt-8 text-sm font-medium text-off-black/60 hover:text-off-black">Need help? Contact support</a>
    </section>
</x-layouts.marketing-page>
