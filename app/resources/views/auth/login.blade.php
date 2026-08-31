{{--
    All four ways in, on one screen.

    FOUND-04 requires password, passkey, SSO and magic link to work end-to-end.
    They are offered in the order of least friction: a passkey needs no typing,
    SSO needs one click, a magic link needs an address, a password needs memory.

    Outcome language throughout (`29` §2 rule 47) — every string names what the
    person gets, never how the system works. "Sign in", not "Authenticate";
    "Email me a link", not "Request magic link token".
--}}
<x-auth.layout title="Sign in">
    <h1 class="font-display text-2xl font-semibold text-ink">Sign in</h1>

    <p class="mt-2 text-ink-2">
        Use whichever is easiest. They all get you to the same place.
    </p>

    {{-- Status and errors are announced, not just coloured: colour is never the
         sole indicator (`29` §2 rule 46). --}}
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

    {{-- 1. Passkey. Progressive enhancement: the button only appears where the
         browser can actually use one, because an button that cannot work is
         worse than no button. --}}
    <div id="passkey-block" hidden class="mt-6">
        <button
            type="button"
            id="passkey-signin"
            class="w-full rounded-[--radius-control] bg-ink px-4 py-3 font-medium text-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >
            Sign in with a passkey
        </button>
    </div>

    {{-- 2. Single sign-on. --}}
    <div class="mt-4 grid gap-2">
        <a
            href="{{ route('oauth.redirect', ['provider' => 'google']) }}"
            class="block w-full rounded-[--radius-control] border border-rule-strong px-4 py-3 text-center font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >
            Continue with Google
        </a>

        <a
            href="{{ route('oauth.redirect', ['provider' => 'microsoft']) }}"
            class="block w-full rounded-[--radius-control] border border-rule-strong px-4 py-3 text-center font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >
            Continue with Microsoft
        </a>

        {{--
            ⚠️ THE SECOND SIGNUP DOOR'S TERMS NOTICE (T176 P22). A first sign-in
            with either provider creates an account **and a tenant**, so pressing
            one of these buttons is the acceptance — there is no box to tick, and
            `TermsAcceptanceMethod::SsoContinue` is what records it as the weaker
            act rather than as a ticked box.

            The sentence is the enum's own, which is the same string stored in
            the acceptance's proof blob: the page and the record cannot disagree
            about what was shown.

            Hidden while nothing is published, which is also when the callback
            refuses to open a new account — a notice pointing at documents
            nobody can read is worse than none. The buttons themselves stay, so
            an existing customer can still sign in.
        --}}
        @if ($termsLinks !== [])
            <p class="text-base text-ink-2">
                {{ $termsNotice }}
                <span class="mt-1 block">
                    @foreach ($termsLinks as $document)
                        <a
                            href="{{ $document['url'] }}"
                            class="font-semibold text-ink underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                        >{{ $document['title'] }}</a>@unless ($loop->last)<span aria-hidden="true"> · </span>@endunless
                    @endforeach
                </span>
            </p>
        @endif
    </div>

    <hr class="my-6 border-rule">

    {{-- 3. Magic link. --}}
    <form method="POST" action="{{ route('magic-link.request') }}" class="grid gap-2">
        @csrf

        <label for="magic-email" class="font-medium text-ink">Email me a sign-in link</label>

        <input
            id="magic-email"
            name="email"
            type="email"
            autocomplete="email webauthn"
            required
            class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >

        <button
            type="submit"
            class="w-full rounded-[--radius-control] border border-rule-strong px-4 py-3 font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >
            Send the link
        </button>
    </form>

    <hr class="my-6 border-rule">

    {{-- 4. Password. Fortify owns POST /login. --}}
    <form method="POST" action="{{ route('login.store') }}" class="grid gap-2">
        @csrf

        <label for="password-email" class="font-medium text-ink">Or sign in with a password</label>

        <input
            id="password-email"
            name="email"
            type="email"
            autocomplete="email"
            required
            placeholder="Email"
            class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >

        <input
            id="password"
            name="password"
            type="password"
            autocomplete="current-password"
            required
            placeholder="Password"
            aria-label="Password"
            class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >

        <button
            type="submit"
            class="w-full rounded-[--radius-control] border border-rule-strong px-4 py-3 font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        >
            Sign in
        </button>

        {{--
            ⛔ THE OTHER HALF OF THE RESET SCREEN, AND WITHOUT IT THE SCREEN IS
            THE SAME DEFECT ONE STEP LATER (9880). This page had no
            forgot-password link at all — grep returned nothing — for the life
            of the application, while `POST /forgot-password` was live,
            throttled and metered. A route nobody can reach from the page they
            are stuck on is a door in a wall with no corridor to it.

            ⚠️ INSIDE THE PASSWORD FORM AND NOWHERE ELSE, which is where the
            person who needs it is standing. The three doors above establish no
            password — a passkey, an SSO account and a magic link each sign
            somebody in without one — so offering "forgotten your password?"
            beside them would name a credential most of this page's callers do
            not have.

            ⚠️ IT IS AN ANCHOR AND NOT A SUBMIT, so it must not sit where a
            `type` default would make it a second submit button; a bare
            `<button>` inside a form posts it. Outcome language: it names what
            the person gets, and the verb survives on to the page it opens.
        --}}
        <p class="mt-1 text-ink-2">
            <a
                href="{{ route('password.request') }}"
                class="font-semibold text-ink underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
            >Forgotten your password?</a>
        </p>
    </form>

    {{--
        Passkey sign-in talks to Fortify's own endpoints — GET
        /passkeys/login/options for the challenge, POST /passkeys/login with the
        credential. The @laravel/passkeys npm package wraps the WebAuthn
        ceremony; until the frontend build pulls it in, the block above stays
        hidden rather than offering a button that cannot work.
    --}}
    <script>
        if (window.PublicKeyCredential) {
            document.getElementById('passkey-block').hidden = false;
        }
    </script>
</x-auth.layout>
