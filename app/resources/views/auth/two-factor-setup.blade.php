{{--
    Turning on a second factor (`28` §9.1).

    Three states, in the order a person meets them: not started, enrolling, done.
    The middle one is the one an implementation forgets — a secret exists and was
    never confirmed, because somebody pressed "Turn on" and closed the tab — and
    `confirm => true` in config/fortify.php means that person does not have
    working 2FA. See the controller.

    Every form posts to a Fortify endpoint and every one works with scripting
    off: the QR is server-rendered SVG rather than a fetch, and Fortify's
    responses are `back()`, so this page is the referrer that receives them.

    Outcome language (`29` §2 rule 47) — "Turn on two-step sign-in", never
    "Enable TOTP". Colour is never the sole indicator (rule 46): every state
    carries a heading and a sentence, not a green dot.
--}}
<x-auth.layout title="Two-step sign-in">
    <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-semibold text-ink">Two-step sign-in</h1>
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="text-xs text-ink-3 hover:text-alert font-medium underline">
                Sign out
            </button>
        </form>
    </div>

    {{-- ⚠️ The banner that explains why somebody is here rather than where they
         meant to go. RequiresTwoFactor redirects with this key, and without the
         explanation an internal account meets an unexplained redirect loop. --}}
    @if (session('two_factor_required'))
        <p role="alert" class="mt-4 rounded-[--radius-control] bg-alert-bg px-3 py-2 text-alert">
            {{ session('two_factor_required') }}
        </p>
    @endif

    @if (session('status') === 'two-factor-authentication-enabled')
        <p role="status" class="mt-4 rounded-[--radius-control] bg-ok-bg px-3 py-2 text-ok">
            Scan the code below, then enter what your app shows to finish.
        </p>
    @endif

    @if (session('status') === 'two-factor-authentication-confirmed')
        <p role="status" class="mt-4 rounded-[--radius-control] bg-ok-bg px-3 py-2 text-ok">
            Two-step sign-in is on. Save your recovery codes somewhere safe.
        </p>
    @endif

    @error('code')
        <p role="alert" class="mt-4 rounded-[--radius-control] bg-alert-bg px-3 py-2 text-alert">
            {{ $message }}
        </p>
    @enderror

    @if ($confirmed)
        {{-- ── Done ──────────────────────────────────────────────────────── --}}
        <p class="mt-4 text-ink-2">
            You are asked for a code from your authenticator app each time you
            sign in.
        </p>

        <a
            href="{{ auth()->user()?->role?->isPlatformStaff() ? route('admin.automation-runs') : route('account.home') }}"
            class="mt-5 block w-full rounded-[--radius-control] bg-ink px-4 py-3 text-center font-medium text-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink hover:opacity-90"
        >
            Continue to Dashboard &rarr;
        </a>

        <h2 class="mt-8 font-display text-lg font-semibold text-ink">Recovery codes</h2>

        <p class="mt-2 text-ink-2">
            Each one works once, if you lose your phone. Keep them somewhere
            other than the phone.
        </p>

        <ul class="mt-3 grid gap-1 rounded-[--radius-control] border border-rule bg-paper p-3 font-mono text-ink">
            @foreach ($recoveryCodes as $code)
                <li>{{ $code }}</li>
            @endforeach
        </ul>

        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mt-3">
            @csrf

            <button
                type="submit"
                class="w-full rounded-[--radius-control] border border-rule-strong px-4 py-3 font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
            >
                Replace these codes
            </button>
        </form>
    @elseif ($started)
        {{-- ── Enrolling ─────────────────────────────────────────────────── --}}
        <p class="mt-4 text-ink-2">
            Scan this with your authenticator app, then enter the six-digit code
            it shows.
        </p>

        {{-- The SVG is built by us from our own secret — never user input — so
             it is printed unescaped deliberately. It carries no attacker-
             reachable value. Rendered on a pure white background so camera
             scanners recognize the QR code in all themes. --}}
        <div class="mt-4 flex justify-center rounded-[--radius-control] border border-rule bg-white p-6 shadow-sm">
            <div class="inline-block bg-white p-4 rounded-lg [&>svg]:block [&>svg]:mx-auto [&>svg]:bg-white">
                {!! $qrCodeSvg !!}
            </div>
        </div>

        {{-- ⚠️ Not a convenience. A QR code is unreadable to a screen-reader
             user and unusable when the phone showing it is the phone enrolling,
             and 2FA here is mandatory — so somebody who cannot scan would have
             no way into the application at all. --}}
        <p class="mt-3 text-ink-2">
            Cannot scan? Type this key into your app instead:
        </p>

        <p class="mt-1 break-all rounded-[--radius-control] border border-rule bg-paper p-3 font-mono text-ink">
            {{ $secretKey }}
        </p>

        <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-6 grid gap-2">
            @csrf

            <label for="code" class="font-medium text-ink">Six-digit code</label>

            <input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
                class="w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
            >

            <button
                type="submit"
                class="w-full rounded-[--radius-control] bg-ink px-4 py-3 font-medium text-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink hover:opacity-90"
            >
                Finish turning it on
            </button>
        </form>
    @else
        {{-- ── Not started ───────────────────────────────────────────────── --}}
        @if ($restart)
            <p class="mt-4 text-ink-2">
                The two-step key stored on this account cannot be used, so
                set-up has to start again. Turning it on issues a fresh key;
                anything already added to your authenticator app for this
                account can be removed.
            </p>
        @endif

        <p class="mt-4 text-ink-2">
            You will be asked for a code from your phone each time you sign in.
            It takes about a minute to set up.
        </p>

        <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-6">
            @csrf
            @if ($restart)
                {{-- Fortify keeps an existing secret unless told otherwise;
                     this one is dead, so ask for a new one. --}}
                <input type="hidden" name="force" value="1">
            @endif

            <button
                type="submit"
                class="w-full rounded-[--radius-control] bg-ink px-4 py-3 font-medium text-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink hover:opacity-90"
            >
                Turn on two-step sign-in
            </button>
        </form>
    @endif

    <div class="mt-8 pt-4 border-t border-rule flex items-center justify-between text-sm">
        @if (auth()->check() && (auth()->user()->two_factor_confirmed_at !== null || !auth()->user()->role->isPlatformStaff()))
            <a href="{{ auth()->user()->role->isPlatformStaff() ? route('admin.automation-runs') : route('account.home') }}" class="text-ink-2 hover:text-ink font-medium underline">
                &larr; Back to Dashboard
            </a>
        @else
            <span></span>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="text-ink-2 hover:text-alert font-medium underline">
                Sign out
            </button>
        </form>
    </div>
</x-auth.layout>
