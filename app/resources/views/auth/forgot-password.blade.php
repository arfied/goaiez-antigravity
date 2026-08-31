{{--
    The door a customer who has forgotten their password can actually open.

    ⛔ **UNTIL THIS SCREEN, `POST /forgot-password` WAS REACHABLE BY EVERY
    STRANGER WITH `curl` AND BY NO CUSTOMER WITH A BROWSER** (9628b). Fortify
    registers the GET inside `if ($enableViews)` and the POST outside it, and
    `config/fortify.php` sets `views => false` — so the endpoint that mails the
    reset link had been live for the life of the application with nothing in
    front of it, and `login.blade.php` did not link to it either.

    ⚠️ **A BROWSER DID NOT GET A 404, IT GOT A 405** — measured against a running
    server on 2026-08-26, `allow: POST`. The URI existed and only the method did
    not, so the sentence "the customer gets a 404" that this screen was briefed
    from was wrong about what a person met. It does not change what is owed; it
    changes what the door was saying while nobody looked.

    ⛔ **THE UNIFORM ANSWER IS THIS SCREEN'S TO PRESERVE AND NOT TO CREATE**
    (9505, 9621). `PasswordResetLinkRequestedResponse` is bound to BOTH of
    Fortify's contracts, so a known and an unknown address are answered with one
    sentence flashed as `status`. This page therefore renders `status` — and
    renders it in the SAME place, with the same markup, whatever produced it.

    ⚠️ **RENDERING `status` IS NOT DECORATION, IT IS THE OTHER HALF OF THE
    RESPONSE.** `toResponse()` ends in `back()->with('status', …)`, and `back()`
    resolves to whatever the form was submitted from. Before this screen existed
    that was wherever the caller claimed; now it is here, and a page that did not
    render `status` would answer a customer's submission with an unchanged form
    and no word at all — which reads as a broken button and is the shape a
    support ticket is made of.

    ⚠️ **`@error('email')` IS FOR MALFORMED INPUT ONLY AND CANNOT CARRY THE
    ORACLE.** Fortify's `SendPasswordResetLinkRequest` validates
    `required|email` before the broker is asked, so the only sentence that
    reaches this block is one about the shape of what was typed. Every arm that
    depends on whether an account exists — `INVALID_USER`, `RESET_THROTTLED` and
    the successful send alike — goes through the bound response above and lands
    in `status`.

    Outcome language (`29` §2 rule 47): "Email me a reset link", never "Request
    password reset token". Tokens from resources/css/app.css, 16px body text, a
    real <label>, focus-visible rings, one <h1>, and it works at 320px.
--}}
<x-auth.layout title="Reset your password">
    <h1 class="font-display text-2xl font-semibold text-ink">Reset your password</h1>

    <p class="mt-2 text-ink-2">
        Tell us the address you sign in with and we will send you a link to choose a new password.
    </p>

    {{-- Announced, not merely coloured: colour is never the sole indicator
         (`29` §2 rule 46). --}}
    @if (session('status'))
        <p role="status" class="mt-4 rounded-[--radius-control] bg-ok-bg px-3 py-2 text-ok">
            {{ session('status') }}
        </p>
    @endif

    @error('email')
        <p role="alert" class="mt-4 rounded-[--radius-control] bg-alert-bg px-3 py-2 text-alert">
            {{ $message }}
        </p>
    @enderror

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 grid gap-2">
        @csrf

        <label for="email" class="font-medium text-ink">Email</label>

        {{-- ⛔ NO `old('email')`. `PasswordResetLinkRequestedResponse` drops
             `withInput()` deliberately, because Fortify flashed the address back
             on the failure arm and not on the success one — a second, quieter
             copy of the answer, visible to exactly the caller who was asking.
             Reading `old()` here would put it straight back. --}}
        <input
            id="email"
            name="email"
            type="email"
            autocomplete="email"
            required
            class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >

        <button
            type="submit"
            class="mt-4 w-full rounded-[--radius-control] bg-ink px-4 py-3 font-medium text-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >
            Email me a reset link
        </button>
    </form>

    {{-- The way back, because three of this application's four sign-in doors
         establish no password at all (9628b) and somebody who arrived here by
         accident should not have to reach for the browser's back button. --}}
    <p class="mt-6 text-ink-2">
        <a
            href="{{ route('login') }}"
            class="font-semibold text-ink underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >Back to sign in</a>
    </p>
</x-auth.layout>
