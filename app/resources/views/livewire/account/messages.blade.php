{{--
    The message log (`28` §3.4) — everything we sent, in the owner's words.

    COLOUR IS NOT THE SIGNAL (`22`). §3.4 asks for a "status icon"; every state
    here is carried by its words instead, because a glyph or a hue alone fails
    for a screen reader and for the 8% of men who would not tell the red one from
    the green one. A failed send says it didn't go through and why.

    ⚠️ OUTBOUND ONLY, AND THE PAGE SAYS SO. §3.4 wants "sent or received", and
    `outreach_messages` has no direction column because nothing inbound exists to
    put in one — inbound SMS is row 4's Infobip webhooks, behind 10DLC. Implying
    completeness here would be worse than the gap.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Messages we sent</h1>
        <p class="mt-1 text-base text-ink-2">
            Everything we sent your customers on your behalf. Replies they send back
            aren’t here yet.
        </p>
    </div>

    @if ($inviteEmailsHeld)
        {{--
            ⛔ THE DAY-ONE SENTENCE AND A TOTAL FAILURE SHARED ONE APPEARANCE
            (10042). A review invite refused by our own mail gate rolls back the
            row that would have recorded it — correctly, because no message went
            — so the log below stays empty and reads exactly like a brand new
            account. This is the state that made those two indistinguishable,
            said out loud.

            ⚠️ NO ACTION SLOT, AND THAT IS THE HONEST SHAPE. `29` §5.4 gives an
            attention card one action; there is nothing here the owner can press,
            because every condition behind it is ours — a transport with no
            bounce feed, an unstated sending ceiling, the reserve held back for
            sign-in links. Offering a button would be the dead-retry defect the
            empty-state component already refuses.

            ⚠️ EMAIL ONLY, DELIBERATELY. The invite falls through to a text when
            email declines, so on a tenant with texting on the invites are still
            going out — just not by email. "Review invites aren't going out"
            would be false for that tenant.

            ⚠️ NOTHING FROM THE REFUSAL ITSELF IS RENDERED. The stored reason
            names our mailer and an Ops row; it belongs to the operator screen,
            on the same rule that keeps a raw SMTP rejection out of the list
            below.
        --}}
        <x-ui.attention-card state="attention" heading="Email invites aren’t going out">
            We’ve paused review invites by email at our end, so none have reached
            your customers. It isn’t anything on your account and there’s nothing
            for you to do. They’ll appear here when they go out.
        </x-ui.attention-card>
    @endif

    @if ($neverSent)
        {{--
            ⚠️ "Nothing yet" is a different statement from "no results", and this
            branch exists so the two never share wording. Every tenant is in this
            state on day one, and a tenant who reads "no messages found" reasons
            that something was lost.
        --}}
        {{--
            No action: nothing on this screen sends a message, and the thing that
            will — the review-invite run — is the system's to start. A button
            here would be an invitation to do our job for us.
        --}}
        {{--
            ⛔ AND THE SENTENCE ITSELF MOVES WHEN THE INVITES ARE HELD (10042).
            The card above is not enough on its own: leaving "when we start
            asking your customers for reviews" underneath it would put a promise
            and its contradiction on one screen, and a reader who skims takes the
            sentence in the box with the heading over it. The held wording states
            the same fact the card does and adds no second explanation, so there
            is one reason on this screen rather than two that could drift.
        --}}
        <x-ui.empty-state heading="Nothing sent yet" icon="✉">
            @if ($inviteEmailsHeld)
                Nothing has gone to your customers, and email invites are being
                held at our end.
            @else
                When we start asking your customers for reviews, every message will
                appear here.
            @endif
        </x-ui.empty-state>
    @else
        {{--
            ⚠️ A SECOND EMPTY STATE, FOR THE CASE THE FIRST ONE CANNOT SEE.
            `$neverSent` is the whole log being empty; this list is one page of
            it, so a URL with ?page=9 on a two-page log renders nothing with
            `$neverSent` false — a blank screen under a heading, and the reader's
            own address bar is the cause. The day-one sentence would be a lie
            there, so this branch says what actually happened.
        --}}
        <ul class="space-y-3">
            @forelse ($messages as $message)
                <li class="rounded-[--radius-panel] border border-rule bg-card p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-base font-medium text-ink">
                            {{ $message->customer?->name ?: $message->customer?->email ?: $message->customer?->phone ?: 'A customer' }}
                        </p>
                        <p class="text-sm text-ink-2">
                            {{ $message->sent_at?->diffForHumans() ?? $message->created_at?->diffForHumans() ?? 'Not dated' }}
                        </p>
                    </div>

                    {{--
                        The one-line preview §3.4 asks for. `Str::limit` rather
                        than a CSS truncation so the body is not shipped whole to
                        a page that only shows its first line — the shorter the
                        journey a customer's words take, the fewer places they
                        can leak.
                    --}}
                    <p class="mt-1 text-base text-ink-2">
                        {{ $message->body ? \Illuminate\Support\Str::limit($message->body, 120) : 'No message text was recorded.' }}
                    </p>

                    <p class="mt-3 text-sm text-ink" data-message-status="{{ $message->id }}">
                        {{ \App\Services\Messaging\MessageLog::statusLabel($message->status) }}
                        <span class="text-ink-2">· {{ $message->channel->value === 'email' ? 'Email' : 'Text' }}</span>
                    </p>

                    @php $why = \App\Services\Messaging\MessageLog::explain($message); @endphp

                    @if ($why)
                        {{-- The auto-explain §3.4 asks for. Never the stored vendor string. --}}
                        <p class="mt-1 text-sm text-ink-2">{{ $why }}</p>
                    @endif
                </li>
            @empty
                <li>
                    {{--
                        The paginator's own first-page URL rather than this
                        screen's route name: this is a link to itself, and
                        naming the route would put a literal in a template that
                        `OwnerNavTest` reads — correctly — as a hand-written
                        link between owner screens. ⚠️ That lint reads the whole
                        file, comments included, so even writing the call here
                        to explain why it is not written reddens it.
                    --}}
                    <x-ui.empty-state
                        icon="✉"
                        action="Back to the first page"
                        :href="$messages->url(1)"
                    >
                        There is nothing on this page. There are messages further back.
                    </x-ui.empty-state>
                </li>
            @endforelse
        </ul>

        <div>{{ $messages->links() }}</div>
    @endif

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Not here yet</h2>
        <ul class="mt-2 space-y-1 text-base text-ink-2">
            <li>Replies from your customers — we can’t receive texts yet.</li>
            <li>Filters, full conversations and export.</li>
            <li>Recovery conversations — nothing records what was said in them yet.</li>
        </ul>
    </div>

    {{--
        ⚠️ THE HAND-WRITTEN LINK IS GONE AND ITS ORIGINAL WARNING IS WHY THE
        SHELL EXISTS. It read: *"no link to Home, deliberately — `account.home`
        is on an unmerged branch, and naming a route that does not exist on
        `main` would 500 this page the moment it merged first."* That is the cost
        of a per-page link graph paid out loud: two branches could not link to
        each other without one of them breaking. Movement between owner screens
        is `OwnerNav`'s now, and a route that does not resolve reddens
        `OwnerNavTest` rather than reaching an owner.
    --}}
</div>
