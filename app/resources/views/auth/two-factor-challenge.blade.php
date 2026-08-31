{{--
    The second step of a password sign-in, for anyone who has a second factor.

    ⚠️ THIS SCREEN IS WHY 2FA COULD NOT BE MANDATED BEFORE IT EXISTED (660).
    Fortify registers `GET /two-factor-challenge` inside `if ($enableViews)` and
    its POST outside, and `fortify.views` is false here — so the route name
    existed nowhere while `RedirectIfTwoFactorAuthenticatable` ended every
    password login by a user with a confirmed factor at
    `redirect()->route('two-factor.login')`. Enabling 2FA locked the account out
    by succeeding at the password step.

    So this is the third GET route in this application that exists because the
    framework redirects to it by name rather than because we wanted a page —
    `login` and `password.reset` are the other two, and routes/web.php lists all
    of them together for that reason.

    Outcome language (`29` §2 rule 47): "Sign in", never "Verify OTP". Colour is
    never the sole indicator (rule 46) — the error is announced with role="alert"
    and reads as a sentence.
--}}
<x-auth.layout title="One more step">
    <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-semibold text-ink">One more step</h1>
        <a href="{{ route('login') }}" class="text-xs text-ink-3 hover:text-ink font-medium underline">
            Sign in as different user
        </a>
    </div>

    <p class="mt-2 text-ink-2">
        Enter the six-digit code from your authenticator app.
    </p>

    @error('code')
        <p role="alert" class="mt-4 rounded-[--radius-control] bg-alert-bg px-3 py-2 text-alert">
            {{ $message }}
        </p>
    @enderror

    @error('recovery_code')
        <p role="alert" class="mt-4 rounded-[--radius-control] bg-alert-bg px-3 py-2 text-alert">
            {{ $message }}
        </p>
    @enderror

    {{-- The code. `inputmode="numeric"` and `autocomplete="one-time-code"` are
         what let a phone offer the code from the notification shade rather than
         making somebody switch apps and retype it. --}}
    <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6 grid gap-2">
        @csrf

        <label for="code" class="font-medium text-ink">Six-digit code</label>

        <input
            id="code"
            name="code"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            autofocus
            required
            class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >

        <button
            type="submit"
            class="w-full rounded-[--radius-control] bg-ink px-4 py-3 font-medium text-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink hover:opacity-90"
        >
            Sign in
        </button>
    </form>

    <hr class="my-6 border-rule">

    {{-- The recovery code, on the same screen rather than behind a toggle: a
         person reaching for one has lost their phone, and a control they have to
         find first is a support ticket. Both fields post to the same Fortify
         endpoint, which reads whichever one is filled in. --}}
    <form method="POST" action="{{ route('two-factor.login.store') }}" class="grid gap-2">
        @csrf

        <label for="recovery_code" class="font-medium text-ink">
            Lost your phone? Use a recovery code
        </label>

        <input
            id="recovery_code"
            name="recovery_code"
            type="text"
            autocomplete="one-time-code"
            required
            class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >

        <button
            type="submit"
            class="w-full rounded-[--radius-control] border border-rule-strong px-4 py-3 font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >
            Use recovery code
        </button>
    </form>

    <div class="mt-8 pt-4 border-t border-rule text-center">
        <a href="{{ route('login') }}" class="text-sm text-ink-2 hover:text-ink font-medium underline">
            &larr; Cancel and return to sign in
        </a>
    </div>
</x-auth.layout>
