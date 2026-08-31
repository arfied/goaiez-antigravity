{{--
    `28` §9.5's plain status page.

    OUTCOME LANGUAGE, AND NOT ONE WORD OF OURS (`22`, `29` §2 rule 47). Not
    "suspended", not "compliance hold", not "policy violation" — "on hold" is
    what happened to their business, and the rest is our vocabulary for our
    mechanism. It also matches the feed entry the owner reads afterwards, so the
    same event is not described two ways.

    COLOUR IS NOT THE SIGNAL. The state is carried by the sentence; there is no
    badge, no red panel and no icon doing work on its own.

    NOTHING HERE IS AN ACCUSATION. The page says what is true — we stopped it,
    and a person will talk to them — and nothing about why. The reason is an
    internal note; see the controller.

    ⚠️ THE ONE OWNER SCREEN WITH NO NAVIGATION, AND IT IS NOT A STYLING CHOICE.
    `SuspendedTenantStatus` redirects a suspended owner here from every other
    screen in that nav, so every link would land back on this page — a row of
    six dead ends on the page whose whole job is to say one clear thing. `28`
    §9.5 asks for a *plain status page*, and this is what plain means when the
    shell around it has grown a site.
--}}

<x-account.layout title="Your account is on hold" :nav="false">
    <div class="space-y-8">
        <div>
            <h1 class="font-display text-2xl font-semibold text-ink">Your account is on hold</h1>
            <p class="mt-2 text-base text-ink-2">
                We have paused everything we do for you while we look into something.
                @if ($suspendedAt)
                    This started {{ $suspendedAt->diffForHumans() }}.
                @endif
            </p>
        </div>

        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">What this means</h2>

            <ul class="mt-3 space-y-2 text-base text-ink-2">
                {{-- Said plainly, because it is the first thing an owner will assume. --}}
                <li>We are not asking your customers for reviews, replying, or publishing anything.</li>

                {{--
                    Decision 821, and still true under this stop: the QR code on
                    their counter keeps working and the words their customers
                    leave are waiting for them.
                --}}
                <li>Your customers can still leave you feedback, and it is being kept for you.</li>

                {{--
                    `28` §9.5: "billing unaffected until resolved." Stated on the
                    page because an owner locked out of their own account will
                    otherwise assume the worst about their card.
                --}}
                <li>Nothing has changed about your subscription.</li>
            </ul>
        </div>

        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Talk to us</h2>

            @if ($supportEmail)
                <p class="mt-2 text-base text-ink-2">
                    Email
                    <a href="mailto:{{ $supportEmail }}" class="text-ink underline">{{ $supportEmail }}</a>
                    and we will explain where things stand.
                </p>
            @else
                {{--
                    Decision 482's rule: no address is printed until an operator
                    sets one, because an unmonitored mailbox on this page looks
                    like an answer and is not. Replying to any message we have
                    sent reaches a real place today.
                --}}
                <p class="mt-2 text-base text-ink-2">
                    Reply to any email you have had from us and we will explain where
                    things stand.
                </p>
            @endif
        </div>

        {{--
            ⚠️ THE ONE THING THIS PAGE MUST OFFER (1900). `28` §3.7 — exporting
            is "never delayed, gated on retention offers, or degraded" — and
            §9.5's cooling window is specified "with export offered". A
            suspended owner is redirected here from every other screen, so
            without this panel the promise is broken at exactly the moment it
            is worth something, and the record would show we offered nothing.

            NOTHING HERE MENTIONS THE HOLD. The data is theirs on a normal day
            and it is theirs today; wording it as a concession would make it
            read like an exit interview.

            A plain form POST to a route the suspension middleware exempts by
            name — a Livewire action would post to `livewire.update`, which is
            not exempt and cannot be without exempting every other control in
            the application.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Download my data</h2>

            <p class="mt-2 text-base text-ink-2">
                Everything we have on file for your account — contacts, reviews, and
                messages we sent — in one ZIP file.
            </p>

            @if ($latestExport)
                <p class="mt-3 text-sm text-ink-2">
                    @if ($exportDownloadUrl)
                        Ready — built {{ $latestExport->built_at?->diffForHumans() }}. This
                        link stops working {{ $latestExport->expires_at?->diffForHumans() }}.
                    @elseif ($latestExport->status->value === 'failed')
                        The last attempt could not be built. Try again below.
                    @else
                        Building your last request now — this takes a few minutes.
                    @endif
                </p>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-3">
                @if ($exportDownloadUrl)
                    <x-ui.button :href="$exportDownloadUrl" size="default">Download my data</x-ui.button>
                @endif

                <form method="POST" action="{{ route('account.data-export.request') }}">
                    @csrf
                    <x-ui.button
                        type="submit"
                        size="default"
                        :variant="$exportDownloadUrl ? 'secondary' : 'primary'"
                    >
                        {{ $exportDownloadUrl ? 'Build a new download' : 'Download my data' }}
                    </x-ui.button>
                </form>
            </div>
        </div>

        {{--
            The way out of the session. A person who cannot use their account
            must still be able to leave it — `RequiresTwoFactor`'s fourth
            exemption, for the same reason, and the middleware exempts this
            route by name.
        --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
            >
                Sign out
            </button>
        </form>
    </div>
</x-account.layout>
