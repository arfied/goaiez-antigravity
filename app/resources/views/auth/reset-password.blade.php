{{--
    Required by name, not by choice.

    Illuminate\Auth\Notifications\ResetPassword builds its URL from the
    `password.reset` route, so this screen has to exist for the reset email to be
    sendable at all — Fortify's own documentation says so when views are
    disabled. Fortify owns POST /reset-password (named `password.update`).

    Placeholder for the design system in `22`, like the login screen.
--}}
<x-auth.layout title="Choose a new password">
    <h1 class="font-display text-2xl font-semibold text-ink">Choose a new password</h1>

    @error('email')
        <p role="alert" class="mt-4 rounded-[--radius-control] bg-alert-bg px-3 py-2 text-alert">
            {{ $message }}
        </p>
    @enderror

    @error('password')
        <p role="alert" class="mt-4 rounded-[--radius-control] bg-alert-bg px-3 py-2 text-alert">
            {{ $message }}
        </p>
    @enderror

    <form method="POST" action="{{ route('password.update') }}" class="mt-6 grid gap-2">
        @csrf

        <input type="hidden" name="token" value="{{ request()->route('token') }}">

        <label for="email" class="font-medium text-ink">Email</label>
        <input
            id="email"
            name="email"
            type="email"
            autocomplete="email"
            required
            value="{{ old('email', request('email')) }}"
            class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >

        <label for="password" class="mt-2 font-medium text-ink">New password</label>
        <input
            id="password"
            name="password"
            type="password"
            autocomplete="new-password"
            required
            class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >

        <label for="password_confirmation" class="mt-2 font-medium text-ink">Confirm new password</label>
        <input
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            required
            class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >

        <button
            type="submit"
            class="mt-4 w-full rounded-[--radius-control] bg-ink px-4 py-3 font-medium text-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >
            Save the new password
        </button>
    </form>
</x-auth.layout>
