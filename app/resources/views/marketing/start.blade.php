{{--
    Signup (`29` §6.1's `/start`).

    THIN ON PURPOSE, AND IT EXISTS BECAUSE NOTHING ELSE BUILT IT. Fortify already
    owns the endpoint — `config/fortify.php` enables Features::registration() with
    `views => false`, so `POST /register` works and no page renders in front of
    it. Slice H is specified as "`/start?audit={token}` copies name, findings and
    categories into `wizard_progress.data`", which assumes this route exists; the
    plan never schedules building it. G2 builds the page so the home page's
    primary call to action has somewhere to go, and H adds what it does with the
    token.

    The audit token is carried through as a hidden field rather than dropped, so
    that when H lands the link already carries what it needs and no marketing
    copy has to change.

    `29` §7.1 puts passkey and magic-link ahead of a password. Neither is wired
    into signup yet — the passkey browser ceremony is a known Stage 0 gap — so
    this is the password path Fortify already validates, and the ordering is a
    note for whoever closes that gap rather than a claim this page makes.
--}}

{{--
    ⚠️ THE TRIAL LENGTH IS READ, NOT SPELLED OUT — and this page said "Seven
    days" in both of the sentences below until row 22 slice B (decision 691).
    `billing.trial_days` has been 14 since the owner reversed it (544), the home
    page was corrected to read the key when CFG1 landed (518), and this one was
    missed because a `Route::view` has nothing to read a key with. Nothing could
    have caught it: 511 records that the registry lint's numeric half is scoped
    to prices and that a term written in words is one of the gaps it does not
    close. The page that takes the promise was the one still making the old one.
--}}
<x-marketing.layout
    title="Start free"
    {{-- 2065 again (4523): no card required, card optional. This meta
         description carried decision 98's superseded card requirement — the same
         false promise the home page was making one screen earlier. --}}
    :description="$trialDays.' days free, no card required. You can cancel in one click.'"
>
    <section class="mx-auto w-full max-w-md px-4 py-16">
        <h1 class="font-display text-3xl font-semibold tracking-tight text-ink">Start free</h1>

        <p class="mt-3 text-base text-ink-2">
            {{ $trialDays }} days free. You can cancel in one click.
        </p>

        {{--
            ⛔ NO FORM WHEN THERE ARE NO PUBLISHED TERMS (T176 P22). `SignupTerms`
            refuses the registration in that state, so rendering the form would
            be a page promising something the application will decline — and the
            person would spend one of their three registration attempts finding
            out. Nothing seeds a *published* legal document on purpose, so this
            is the state a fresh install is in until an admin publishes
            counsel's text.
        --}}
        @if ($termsLinks === [])
            <p class="mt-8 rounded-[--radius-card] border border-rule bg-card px-4 py-3 text-base text-ink-2">
                We are not opening new accounts at this moment. Please check back shortly.
            </p>
        @else

        {{--
            ⛔ INSIDE THE `@else`, AND IT SAT ABOVE THE WHOLE BLOCK UNTIL
            2026-08-26 (9882). `links()` returns `[]` exactly when
            `SignupTerms::current()` is null, which is exactly when
            `CreateNewUser` refuses — so an install with no published terms
            rendered this list of errors ABOVE the notice below, and a person
            met two sentences that disagree about whether their problem is
            fixable. Measured on a running server: *"The email has already been
            taken."* directly above *"We are not opening new accounts at this
            moment."*

            ⚠️ NOTHING IS HIDDEN THAT A PERSON COULD ACT ON. In that state the
            only error this action can now produce is the terms refusal, whose
            own wording — "We cannot open new accounts just now" — is the notice
            above said twice. On every install that can open an account this
            block renders exactly as it always did.
        --}}
        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-[--radius-card] border border-rule bg-alert-bg px-4 py-3 text-base text-alert">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ url('/register') }}" class="mt-8 space-y-5">
            @csrf

            {{-- Carried for slice H, which turns it into a pre-filled wizard. --}}
            @if (request()->filled('audit'))
                <input type="hidden" name="audit" value="{{ request()->query('audit') }}">
            @endif

            <div>
                <label for="name" class="block text-base font-medium text-ink">Your name</label>
                <input
                    id="name" name="name" type="text" required autocomplete="name"
                    value="{{ old('name') }}"
                    class="mt-1 min-h-12 w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 text-base text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                >
            </div>

            <div>
                <label for="email" class="block text-base font-medium text-ink">Email</label>
                <input
                    id="email" name="email" type="email" required autocomplete="email"
                    value="{{ old('email') }}"
                    class="mt-1 min-h-12 w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 text-base text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                >
            </div>

            <div>
                <label for="password" class="block text-base font-medium text-ink">Password</label>
                <input
                    id="password" name="password" type="password" required autocomplete="new-password"
                    class="mt-1 min-h-12 w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 text-base text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                >
            </div>

            <div>
                <label for="password_confirmation" class="block text-base font-medium text-ink">Confirm password</label>
                <input
                    id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    class="mt-1 min-h-12 w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 text-base text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                >
            </div>

            {{--
                ⚠️ UNCHECKED, ALWAYS (`29` §2). A pre-ticked box is not agreement
                in any jurisdiction that matters, and `ConsentProof` refuses a
                stored record that claims otherwise — so `checked` must never
                appear on this input, and `old('terms')` is deliberately not
                consulted either: a failed submission re-asks rather than
                remembering that somebody once agreed.

                The label text and the three links come from the controller.
                The wording is `TermsAcceptanceMethod::Checkbox->notice()`, which
                is the same string stored in the acceptance's proof blob — the
                page and the record cannot disagree about what was shown.
            --}}
            <div class="flex gap-3">
                <input
                    id="terms" name="terms" type="checkbox" value="1" required
                    class="mt-1 size-5 shrink-0 rounded-[--radius-control] border border-rule-strong bg-card text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                >
                <label for="terms" class="text-base text-ink-2">
                    {{ $termsLabel }}
                    <span class="mt-1 block">
                        @foreach ($termsLinks as $document)
                            <a
                                href="{{ $document['url'] }}"
                                class="font-semibold text-ink underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                            >{{ $document['title'] }}</a>@unless ($loop->last)<span aria-hidden="true"> · </span>@endunless
                        @endforeach
                    </span>
                </label>
            </div>

            <x-ui.button type="submit" class="w-full">Create my account</x-ui.button>
        </form>

        @endif

        <p class="mt-6 text-base text-ink-2">
            @auth
                Already signed in?
                <a href="{{ auth()->user()->role?->isPlatformStaff() ? route('admin.automation-runs') : route('account.home') }}" class="font-semibold text-ink underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none">Go to dashboard &rarr;</a>
            @else
                Already with us?
                <a href="{{ route('login') }}" class="font-semibold text-ink underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none">Sign in</a>
            @endauth
        </p>
    </section>
</x-marketing.layout>
