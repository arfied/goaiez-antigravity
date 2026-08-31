{{--
    The email sending meter — T137 R3's visible limit, and the screen decision
    4442 recorded as owed (built at 4600).

    THIS TEMPLATE DOES NO ARITHMETIC AND HOLDS NO THRESHOLD. Every figure comes
    out of one `MailQuota::reading()` call, which is the object the alert and
    the send gate are built from. Decision 4485 is why: two panels on one screen
    gave two answers about one business at one moment, because each renderer
    remembered the rule separately, and the fix was that they agree by
    construction rather than by convention.

    A CEILING NOBODY HAS STATED IS NOT A ZERO AND NOT A DASH. It is an outage,
    and it is written as one: no message goes out at all, and the row to set is
    named on the page rather than left in a docblock.

    COLOUR IS NOT THE SIGNAL (`22`, `29` §5.5). Every state here is a word and a
    sentence and the pill carries the icon; nothing on this page is legible by
    hue alone.

    NO PERSONAL DATA. The only address rendered is ours — the sending account,
    which is the Workspace user or the configured from address, never a
    recipient's. `platform_mail_sends` stores no recipient at all, so there is
    none here to leak.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Email sending</h1>
        <p class="mt-1 text-base text-ink-2">
            How much of this account's 24-hour sending allowance is used, and
            what stops when it runs out.
        </p>
    </div>

    {{-- ────────────────────  The last 24 hours  ──────────────────── --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-lg font-semibold text-ink">The last 24 hours</h2>

            <x-ui.status-pill :state="$state" :label="$stateLabel" />
        </div>

        @if ($reading['ceiling'] === null)
            <p class="mt-3 text-base text-ink-2">
                {{--
                    THE PHRASE STAYS ON ONE SOURCE LINE, because Blade keeps
                    the newlines and a sentence wrapped mid-phrase is one
                    `assertSee()` cannot find.
                --}}
                <span class="font-semibold text-ink">No email is going out.</span>
                Nobody has stated a 24-hour sending limit for this transport,
                so every message is refused rather than sent — including
                sign-in links. The limit belongs to the sending account, and
                only the person who set that account up knows it: a new
                Amazon SES account may send 200 a day until AWS grants
                production access, and whatever was granted afterwards.
            </p>

            <p class="mt-3 text-base text-ink-2">
                Set it under
                <a href="{{ route('admin.platform-settings') }}" class="font-semibold text-ink underline">Settings</a>,
                on the row named
                <span class="font-mono text-sm text-ink">{{ $reading['ceilingKey'] }}</span>.
            </p>

            <p class="mt-3 text-base text-ink-2">
                {{ number_format($reading['used']) }} {{ Str::plural('message', $reading['used']) }} went out in the
                last 24 hours before this took effect.
            </p>
        @else
            <p class="mt-4 font-mono text-3xl tabular-nums text-ink">
                {{ number_format($reading['used']) }}<span class="text-ink-3"> / {{ number_format($reading['ceiling']) }}</span>
            </p>

            {{--
                The bar is a second rendering of one number, never a second
                number: its width is the ratio the pill above was decided
                from, converted in the component so that this file's "no
                arithmetic" is true rather than nearly true. `aria-hidden`,
                because the figures beside it already say this and a bar that
                repeats them to a screen reader is noise.
            --}}
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-rule" aria-hidden="true">
                <div
                    class="h-full rounded-full {{ $state->backgroundClass() }}"
                    style="width: {{ $barPercent }}%"
                ></div>
            </div>

            <p class="mt-3 text-base text-ink-2">
                Sent in the last 24 hours, out of what this account may send.
                <span class="font-semibold text-ink">The window rolls</span> —
                it is not a calendar day, so it clears message by message as
                sends age out rather than resetting at midnight.
            </p>

            @if ($reading['alerting'])
                <p class="mt-3 text-base text-ink-2">
                    {{-- THE PHRASE STAYS ON ONE SOURCE LINE. Blade keeps the
                        newlines, and a sentence wrapped mid-phrase is one
                        `assertSee()` cannot find — which this file learned
                        by watching it fail. --}}
                    <span class="font-semibold text-ink">An alert has already been raised</span>
                    for whoever is on call. Going over the limit stops the
                    account accepting mail for up to 24 hours, so messages
                    are refused here first — one refused message costs less
                    than a day of silence.
                </p>
            @endif
        @endif
    </section>

    {{-- ────────────────────  How fast it goes out  ──────────────────── --}}
    {{--
        THE SECOND LIMIT, WHICH THE 24-HOUR CEILING CANNOT EXPRESS (4432,
        built at 4648). It is on this page for one reason: an unstated rate
        refuses nothing, so unlike the ceiling above it nothing anywhere
        forces an operator to discover it. A fail-open control nobody ever
        sees is a control with no writer, and being visible beside the
        fail-closed one is the answer to that.

        IT IS DELIBERATELY NOT WRITTEN AS A GUARANTEE. Amazon's own
        documentation says the rate may be exceeded for short bursts, and
        that the rate it accepts can be lower than the one granted — so a
        page promising that nothing is ever throttled would assert a
        protection that is not true.
    --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">How fast it goes out</h2>

        @if ($sendRatePerSecond === null)
            <p class="mt-3 text-base text-ink-2">
                {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                <span class="font-semibold text-ink">Messages go out as fast as the queue drains.</span>
                Nobody has stated a per-second rate for this transport, so
                nothing here slows a batch down. That is not an outage — mail
                still sends — but a provider that meters by the second will
                refuse individual messages during a burst, and each one comes
                back through the queue a minute later.
            </p>

            <p class="mt-3 text-base text-ink-2">
                Set it under
                <a href="{{ route('admin.platform-settings') }}" class="font-semibold text-ink underline">Settings</a>,
                on the row named
                <span class="font-mono text-sm text-ink">{{ $sendRateKey }}</span>.
                A new Amazon SES account is allowed one message a second
                until AWS grants production access.
            </p>
        @else
            <p class="mt-4 font-mono text-3xl tabular-nums text-ink">
                {{ number_format($sendRatePerSecond) }}<span class="text-ink-3"> / second</span>
            </p>

            <p class="mt-3 text-base text-ink-2">
                A message that would go over this waits its turn — for a few
                seconds at most — and is then handed over anyway.
                <span class="font-semibold text-ink">Nothing is refused and nothing is dropped</span>
                by this limit: going over it costs one message a retry, where
                going over the daily limit costs the account a day.
            </p>
        @endif
    </section>

    {{-- ────────────────────  What stops first  ──────────────────── --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">What stops first</h2>

        <p class="mt-3 text-base text-ink-2">
            Mail to a business's own customers stops before the limit is
            reached, so a sign-in link still goes out on an account that a
            batch of review invites has otherwise used up. An owner locked
            out of their account because their own invites ate the allowance
            is the worst version of this failure.
        </p>

        @if ($customerStopsAt === null)
            <p class="mt-3 text-base text-ink-2">
                Nothing is being sent at all, so nothing is being held back
                either.
            </p>
        @else
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded-[--radius-control] border border-rule bg-paper p-4">
                    <dt class="text-sm font-semibold text-ink-2">Customer mail stops at</dt>
                    <dd class="mt-1 font-mono text-2xl tabular-nums text-ink">{{ number_format($customerStopsAt) }}</dd>
                    <dd class="mt-2 text-base text-ink-2">Messages. After that only the platform's own mail goes out.</dd>
                </div>

                <div class="rounded-[--radius-control] border border-rule bg-paper p-4">
                    <dt class="text-sm font-semibold text-ink-2">Held back for platform mail</dt>
                    <dd class="mt-1 font-mono text-2xl tabular-nums text-ink">{{ number_format($reading['reserve']) }}</dd>
                    <dd class="mt-2 text-base text-ink-2">
                        @if ($reading['reserve'] !== $reading['reserveConfigured'])
                            {{--
                                THE DERIVATION, STATED RATHER THAN LEFT TO BE
                                DISCOVERED. The Settings screen shows the row
                                and this shows its effect; without this
                                sentence the two give two answers about one
                                deployment, which is 3418's shape and 4485's
                                finding.
                            --}}
                            {{ number_format($reading['reserveConfigured']) }} is set in Settings and is being held
                            one below the limit — a reserve at or above the
                            limit would refuse every customer message for
                            ever.
                        @else
                            Messages, set in Settings.
                        @endif
                    </dd>
                </div>
            </dl>
        @endif

        <p class="mt-4 text-base text-ink-2">
            @if ($customerMailPermitted)
                <span class="font-semibold text-ink">Mail to customers is allowed</span> on this
                transport: it reports bounces and complaints back, so a bad
                address can be suppressed and a complaint can be seen.
            @else
                <span class="font-semibold text-ink">Mail to customers is refused</span> on this
                transport, whatever the allowance says. {{ $feedbackRefusal }}
            @endif
        </p>
    </section>

    {{-- ────────────────────  The account  ──────────────────── --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">The account this is measured on</h2>

        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-semibold text-ink-2">Transport</dt>
                <dd class="mt-1 font-mono text-base text-ink">{{ $reading['mailer'] }}</dd>
            </div>

            <div>
                <dt class="text-sm font-semibold text-ink-2">Sending account</dt>
                <dd class="mt-1 font-mono text-base break-all text-ink">{{ $reading['account'] }}</dd>
            </div>
        </dl>

        <p class="mt-4 text-base text-ink-2">
            Each transport has its own limit, because the providers do: a
            Google Workspace user may send 2,000 a day and a new Amazon SES
            account 200. Changing the transport changes which limit applies.
        </p>

        <p class="mt-3 text-base text-ink-2">
            This counts what this application handed to the transport. Mail
            sent from the same account by a person, or by anything else,
            counts against the provider's limit and not against this one — so
            read the figure as ours rather than as the provider's.
        </p>
    </section>

    {{-- ────────────────────  How this transport signs in  ──────────────────── --}}
    {{--
        THE ONE CREDENTIAL ON THIS PLATFORM THAT IS ON NO REGISTER, AND THIS IS
        WHERE IT IS SAID (9374). `config/mail.php` reads MAIL_USERNAME and
        MAIL_PASSWORD from the environment, so the Credentials screen cannot
        list them, nothing tests them, and the credential bell built one wave
        ago cannot fire about them in any state — absent, blank, wrong or
        rotated. Production met exactly that on 2026-08-20: an SES transport
        holding another vendor's username, three permanent failures eighteen
        minutes apart, and every row on the Credentials board green throughout.

        IT IS AN ABSENCE, WHICH IS THE HARDEST THING FOR A SCREEN TO SHOW. Every
        other panel on this page reports a state the platform measured; this one
        reports that a state is unmeasurable, which nothing else here says and
        nothing else here can.

        THREE SENTENCES, NOT ONE, BECAUSE A TRANSPORT THAT SIGNS IN WITH
        NOTHING IS NOT THE SAME AS ONE THAT SIGNS IN WITH SOMETHING WE CANNOT
        SEE. Sending an operator to check a password that does not exist is the
        wasted hour this split exists to avoid.
    --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">How this transport signs in</h2>

        @if ($mailerCredential['key'] !== null)
            <p class="mt-3 text-base text-ink-2">
                {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                <span class="font-semibold text-ink">This transport signs in with a credential this platform holds.</span>
                It is on the credentials board under
                <span class="font-mono text-sm text-ink">{{ $mailerCredential['key'] }}</span>,
                where you can see whether it is set and replace it without a
                deploy.
            </p>

            <p class="mt-3 text-base text-ink-2">
                Open
                <a href="{{ route('admin.credentials') }}" class="font-semibold text-ink underline">Credentials</a>.
            </p>
        @elseif ($mailerCredential['authenticates'])
            <p class="mt-3 text-base text-ink-2">
                {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                <span class="font-semibold text-ink">This transport signs in with a username and password read from this server's .env file.</span>
                They are on no register on this platform: the credentials board
                cannot list them, nothing here tests them, and no alert can
                tell you they have stopped working.
            </p>

            <p class="mt-3 text-base text-ink-2">
                A wrong password looks exactly like a working one until a
                message fails — and then it fails permanently, because the
                queue gives up after three attempts and nothing tries again.
                That failure does now ring: the alert is
                <span class="font-semibold text-ink">Email this platform sent was not delivered, and nothing will retry it</span>.
            </p>

            <p class="mt-3 text-base text-ink-2">
                Changing them means editing
                <span class="font-mono text-sm text-ink">MAIL_USERNAME</span> and
                <span class="font-mono text-sm text-ink">MAIL_PASSWORD</span>
                on the server and running
                <span class="font-mono text-sm text-ink">composer deploy</span>
                afterwards — a credential edited after the deploy reads back as
                empty, with nothing pointing at the config cache.
            </p>
        @else
            <p class="mt-3 text-base text-ink-2">
                {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                <span class="font-semibold text-ink">This transport presents no credentials at all.</span>
                Nothing signs in, so there is no password here to be wrong. If
                mail is not arriving, the transport itself is the thing to
                look at rather than a credential.
            </p>
        @endif
    </section>
</div>
