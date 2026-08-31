{{--
    Re-proving it is you, before a change to how you sign in.

    ⚠️ THE SECOND HALF OF DECISION 660. `Illuminate\Auth\Middleware\
    RequirePassword` redirects to `route('password.confirm')`, Fortify registers
    that GET inside `if ($enableViews)`, and `fortify.views` is false — so every
    endpoint behind `password.confirm` was unreachable: enable 2FA, confirm 2FA,
    the QR code, the secret key, and both recovery-code routes. The way in and
    the way through were dead for the same reason, one config flag apart.

    `confirmPassword` stays true on both the two-factor and passkey features,
    which is Fortify's own recommendation and the safer setting: a hijacked
    session must not be able to register a permanent credential.

    ⚠️ THREE OF THE FOUR WAYS IN NEVER SET A PASSWORD. A magic-link or SSO user
    has nothing to type here, which is why MagicLinkController and
    OauthLoginController mark the session password-confirmed on completion — see
    the note in config/fortify.php. They never see this screen. It exists for the
    fourth way in, and for anyone whose confirmation window has expired.
--}}
<x-auth.layout title="Confirm it is you">
    <h1 class="font-display text-2xl font-semibold text-ink">Confirm it is you</h1>

    <p class="mt-2 text-ink-2">
        You are about to change how you sign in, so please enter your password
        once more.
    </p>

    @error('password')
        <p role="alert" class="mt-4 rounded-[--radius-control] bg-alert-bg px-3 py-2 text-alert">
            {{ $message }}
        </p>
    @enderror

    <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-6 grid gap-2">
        @csrf

        <label for="password" class="font-medium text-ink">Password</label>

        <input
            id="password"
            name="password"
            type="password"
            autocomplete="current-password"
            autofocus
            required
            class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >

        <button
            type="submit"
            class="w-full rounded-[--radius-control] bg-ink px-4 py-3 font-medium text-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >
            Confirm
        </button>
    </form>
</x-auth.layout>
