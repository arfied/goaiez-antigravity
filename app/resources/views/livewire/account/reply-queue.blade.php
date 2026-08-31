{{--
    Reply approval queue (`17` GBP-05).

    COLOUR IS NOT THE SIGNAL (`22`): rating is a number, actions are labelled
    verbs, and a posting-unavailable note is plain text — never a red badge alone.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Reply drafts</h1>
        <p class="mt-1 text-base text-ink-2">
            Suggested replies to your Google reviews. Edit, approve, or skip.
        </p>
    </div>

    @if ($replies->isEmpty())
        {{--
            ⚠️ **THIS COMMENT SAID TWO FALSE THINGS UNTIL 2026-08-13 (3050, 3051),
            AND BOTH ARE 2505'S SHAPE — A CLAIM THAT READS AS A FACT ABOUT TODAY.**

            It said `Account\ReviewRules` HAS NO ROUTE, on the evidence of
            `route:list`. It is a *nested Livewire panel*, not a page: the account
            settings view renders it as `<livewire:account-review-rules>` and
            `AppServiceProvider::registerNestableLivewireComponents()` gives it the
            hyphenated alias Livewire 4 requires. A nested panel never appears in
            `route:list`, so that evidence could not have settled it either way —
            and `ReviewGating::gateable()`'s own docblock already said "re-saving
            on `/account` applies their answer".

            It also said the setting behind this queue lives on that screen. It
            does not. `Account\ReviewRules` holds the invite threshold alone;
            `autopilot_settings.auto_reply` is written **only** by
            `Admin\LocationSettings` — Ops, not the owner (3051).

            ⛔ **STILL NO LINK, AND NOW FOR THE STATED REASON.** The account
            settings screen is a top-level owner screen `OwnerNav` already carries
            as "Your account". A hand-written cross-link between top-level screens
            is exactly what `OwnerNavTest`'s scan refuses, and that scan reads
            whole file contents, comments included — so naming the route in prose
            rather than in a `route()` call is deliberate here. 3009 reached the
            right conclusion from the wrong premise.
        --}}
        {{--
            ⛔ THE SENTENCE ABOVE IS A PROMISE ABOUT A PIPELINE WHOSE FIRST STEP
            CAN BE BROKEN (10120–10139). "When a new Google review comes in, a
            suggested reply appears here" is true of the reply half and says
            nothing about the reading half — and an owner whose Google sync had
            been failing read exactly this over an empty queue, with every
            reason to conclude that their customers had stopped writing.

            ⚠️ APPENDED RATHER THAN SUBSTITUTED. The promise is still true, and
            the note is the caveat on it; replacing it would leave somebody who
            has simply had no reviews this week with no explanation of what this
            screen is for.

            ⚠️ ABSENT WHENEVER THE LAST READ READ, and never a positive claim
            (9921). A null here means only that reading is not what stopped a
            draft appearing — never that reviews are arriving.
        --}}
        <x-ui.empty-state icon="✍">
            No drafts waiting. When a new Google review comes in, a suggested reply appears here.
            @if ($googleReadNote !== null)
                {{-- Anchored for the same reason the Home tile is. --}}
                <span data-google-read-note>{{ $googleReadNote }}</span>
            @endif
        </x-ui.empty-state>
    @else
        <ul class="space-y-4">
            @foreach ($replies as $reply)
                @php($review = $reply->review)
                <li
                    wire:key="reply-{{ $reply->id }}"
                    class="rounded-[--radius-panel] border border-rule bg-card p-5 space-y-4"
                >
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-display text-lg font-semibold text-ink">
                            {{ $review?->rating }}/5
                            @if ($review?->reviewer_name)
                                — {{ $review->reviewer_name }}
                            @endif
                        </p>
                        @if ($locations->get($review?->location_id)?->name)
                            <p class="text-sm text-ink-2">{{ $locations->get($review->location_id)->name }}</p>
                        @endif
                    </div>

                    @if ($review?->comment)
                        <blockquote class="border-l-2 border-rule pl-3 text-base text-ink-2">
                            {{ $review->comment }}
                        </blockquote>
                    @endif

                    <label class="block">
                        <span class="sr-only">Draft reply</span>
                        <textarea
                            wire:model="drafts.{{ $reply->id }}"
                            rows="4"
                            class="w-full rounded-[--radius-control] border border-rule bg-canvas px-3 py-2 text-base text-ink"
                        ></textarea>
                    </label>

                    {{--
                        NO `error_message` RENDER HERE ANY MORE, BECAUSE NO ROW
                        THIS LIST CAN SHOW IS ABLE TO CARRY ONE (1747).

                        Its only writer is `ReviewReplies::markPostingUnavailable()`,
                        which `PostReplyJob` reaches only for a *publishable* row —
                        `Approved`, with `approved_at` and `approved_by` set. This
                        queue lists `Suggested` only, and `recordSuggestion()` and
                        `approve()` both null the column. So the block that used to
                        sit here rendered a value that could never be present:
                        markup carrying a promise of feedback the owner will never
                        receive, which is decision 272's writerless shape pointed at
                        a view instead of a table.

                        It comes back with 1738's owed "waiting to post" view, which
                        is the surface an approved-but-unpublished reply belongs on —
                        and that view is where the message is worth rendering,
                        because there it can actually be there.
                    --}}

                    {{--
                        The booking link the business gave its assistant (T176
                        P7, R13).

                        RENDERED ONLY WHEN THERE IS ONE. No disabled button and
                        no "set one in settings" prompt for a business that has
                        not: R13's rule is that a skill without its grounding is
                        absent, and the same reading applies to the control that
                        would use it. An owner who has no booking link sees this
                        screen exactly as it was before P7.

                        LAST, AND SECONDARY. Approve is the decision this queue
                        exists for and keeps first position; inserting a link is
                        an edit to the draft on the way there, and it changes
                        nothing until they approve.
                    --}}
                    <div class="flex flex-wrap gap-3">
                        <button
                            type="button"
                            wire:click="approve({{ $reply->id }})"
                            class="rounded-[--radius-control] bg-ink px-4 py-2 text-base font-medium text-canvas"
                        >
                            Approve
                        </button>
                        <button
                            type="button"
                            wire:click="skip({{ $reply->id }})"
                            class="rounded-[--radius-control] border border-rule px-4 py-2 text-base font-medium text-ink"
                        >
                            Skip
                        </button>
                        @if ($canInsertBookingLink)
                            <button
                                type="button"
                                wire:click="insertBookingLink({{ $reply->id }})"
                                class="rounded-[--radius-control] border border-rule px-4 py-2 text-base font-medium text-ink"
                            >
                                Add booking link
                            </button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{--
        1738's OWED "WAITING TO POST" VIEW, BUILT — AND DELIBERATELY NOT CALLED
        THAT (6523).

        `pendingAcrossTenant()` lists `suggested` only (1724, so an approved card
        cannot re-list) and `markPostingUnavailable()` leaves `status` alone
        (1728, so an outage cannot un-approve somebody). Between them an
        `Approved` row left the queue above and appeared on no screen at all,
        while its failure text landed in `replies.error_message`, which nothing
        renders.

        ⛔ **THE HEADING IS NOT "WAITING TO POST" AND NO STRING HERE IS IN THE
        FUTURE TENSE.** The heading stands, **and its original premise is dead —
        corrected 2026-08-28 (11265).** It read: *"Nothing re-dispatches
        `PostReplyJob` and nothing watches for a blocker clearing, so 'waiting'
        would name a queue that does not exist."* **The queue exists.**
        `reviews:retry-stranded-replies` runs every fifteen minutes and is
        exactly that watcher (11040–11046). ⛔ **THIS IS THE FILE A LANE OPENS TO
        DECIDE WHETHER THE HEADING MAY CHANGE, AND IT WAS ARGUING AGAINST THE
        CHANGE ON A PREMISE THE SAME WAVE MADE FALSE** — the component's own
        docblock was corrected and this was not, so the two artefacts about one
        screen disagreed about whether the mechanism exists.

        ⚠️ **WHAT A LANE MAY NOW CONCLUDE, AND IT IS NOT "SO THE FUTURE TENSE IS
        FINE".** The sweep gives each approved reply **one** automatic attempt
        per owner decision, bounded by `replies.publish_retry_dispatched_at`,
        and it refuses outright every row about which *"this reply is not on
        Google"* is unprovable — `PublishUnconfirmed`, `NotAccepted`, an
        abandoned run, and a row it has already had its turn at. **So a
        future-tense card would still be false of most of this list**, which is
        11044's own reason and is a property of the sweep rather than of the
        wave it shipped in. ⛔ **AND THE HEADING IS THE OWNER'S EITHER WAY** —
        7048 records *"Approved, not yet on Google"* as theirs to rule on, 11044
        left it deliberately unchanged, and *a queue existing is not a queue
        anybody has watched run.* **Fix the premise; do not take the ruling.**

        What is true on this screen is a state — approved, not on Google — and
        one action the owner controls, which is approving again.

        ⛔ **`error_message` IS STILL NOT RENDERED, AND THAT IS 1748 HONOURED
        RATHER THAN REVERSED.** 1748 said the message "comes back with 1738's
        view, which is where it can actually be present". It can be present —
        and it is a vendor-facing machine string by construction, which rule 47
        keeps off owner screens. `ReplyPublicationStatus` reads it as a boolean
        and `ReplyPublicationState` supplies the sentence, exactly as
        `MessageLog::explain()` does for a failed send.

        ⛔ **THAT LAST SENTENCE STOPPED BEING TRUE ON 2026-08-21 AND IS KEPT
        BECAUSE IT IS THE EVIDENCE** (6720, 6946). `ReplyPublicationStatus` does
        **not** read `error_message` as a boolean and no longer names the column
        at all: a lint in `Architecture\ReviewsTest` fails the build if it does.
        The column was written by refusals that never reached Google, so the
        boolean it offered was not the boolean `NotAccepted` needed. The rule
        this paragraph states is unchanged and is the half that matters — the
        column never reaches this page, and the sentence is written for the
        owner rather than echoed from the row.

        ⛔ **AND THE HEADING BELOW IS A CLAIM THIS SCREEN CANNOT MAKE ABOUT ONE
        OF ITS FIVE STATES, AND IT IS DELIBERATELY LEFT ALONE** (6947). A card in
        `ReplyPublicationState::PublishUnconfirmed` may be live on the listing
        right now, so *"Approved, not yet on Google"* is false about it. **6523
        ruled that heading**, in those words, with its argument written down, and
        `CLAUDE.md` forbids contradicting a locked decision without a ruling. The
        lead sentence under it was **not** ruled and made the same claim in a
        flatter form, so that one is fixed here and the heading is raised. The
        card's own sentence contradicts the heading in plain words directly
        beneath it, which is the most this slice may do.

        ⛔ **EVERY *"FIVE"* THIS FILE STATED AS A FACT WAS A COUNT OF
        `ReplyPublicationState`, AND THERE ARE SIX — KEPT AND DATED, 2026-08-28
        (11363).** There were **four such claims**, in five occurrences of the
        word — one above this paragraph and three below — and every other
        *"five"* in this file is inside a correction quoting one of them. They
        are left word for word on 6940's own rule,
        because what each says about its **subject** is unchanged by the sixth:
        `NotEntitled` is a claim about the account, so *"Approved, not yet on
        Google"* is true about it and `PublishUnconfirmed` is still the only
        state the heading cannot be said of. **Only the denominators moved.**

        ⛔ **AND NOT ONE OF THEM WAS WRONG WHEN IT WAS WRITTEN — THE LAST WAS
        FALSIFIED BY A SIBLING BRANCH, WHICH IS THE PART WORTH CARRYING**
        (11363). The *"four of the five"* below arrived in `d348caa0`, whose
        **parent's** `ReplyPublicationState` has five cases and no
        `NotEntitled`: it was correct on the tree it was written against, and it
        became false at the **merge** with `eeb45db6`. ⚠️ **No gate on either
        branch could see it** — each was green, each count was right, and the
        conflict has no marker because the two lanes edited different files.
        **A hand-kept count is falsifiable by a lane that never opens the file
        it is in.**

        ⚠️ **SO THE COUNTS ARE NOT RESTATED AS SIX** (8460, 8861). This screen's
        states are enumerated by a machine — `ReplyPublicationState::cases()` —
        and a hand-kept twin of a machine's count is what has gone stale twice
        here in a week. What each corrected sentence states instead is the
        **property**, which survives the next case without an edit.

        COLOUR IS NOT THE SIGNAL (`22`): the state is a sentence, the action is
        a labelled verb, and nothing here is a bare red badge.
    --}}
    @if ($awaiting->isNotEmpty())
        <section class="space-y-4">
            <div>
                <h2 class="font-display text-xl font-semibold text-ink">Approved, not yet on Google</h2>
                {{--
                    ⛔ **THIS READ "These replies are approved and are not on
                    your Google listing." AND THAT IS FOUR OF THE FIVE STATES,
                    NOT FIVE** (6947). `PublishUnconfirmed` is a reply we sent
                    and heard nothing about, which may be on the listing right
                    now — so the flat universal claim was the same falsehood the
                    card's own sentence was changed to stop making, one line
                    higher up the page and in every list.

                    What replaces it makes no claim about the listing at all and
                    is true of all five, which is the property a sentence
                    covering a whole list has to have.

                    ⚠️ **"ALL FIVE" IS SIX SINCE 2026-08-28 AND THE PROPERTY IS
                    WHAT THIS PARAGRAPH IS FOR** (11363). The sentence below
                    makes no claim about any listing, so it is true of **every**
                    state this screen can derive — including `NotEntitled`, and
                    including whatever the seventh turns out to be. **That is
                    the test a sentence covering a whole list has to pass**, and
                    stating it as a count is what put an expiry date on a
                    paragraph whose subject has none.
                --}}
                <p class="mt-1 text-base text-ink-2">
                    These replies are approved. Each one below says where it stands.
                </p>
            </div>

            <ul class="space-y-4">
                {{--
                    empty-state: absent because a tenant with nothing stuck is
                    the healthy case, and every account is in it on the day it
                    starts. A panel reading "nothing is waiting to go up" would
                    be a report on a query rather than an invitation (`29`
                    §5.7) — and worse, it would tell somebody a problem does not
                    exist on a screen they only opened to approve a draft. The
                    section is behind `@if ($awaiting->isNotEmpty())`, so it
                    vanishes entirely; the queue above already carries this
                    screen's one empty state, which is the invitation that
                    belongs here.
                --}}
                @foreach ($awaiting as $reply)
                    @php($review = $reply->review)
                    @php($state = $publicationStates[$reply->id] ?? null)
                    <li
                        wire:key="awaiting-{{ $reply->id }}"
                        class="rounded-[--radius-panel] border border-rule bg-card p-5 space-y-4"
                    >
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-display text-lg font-semibold text-ink">
                                {{ $review?->rating }}/5
                                @if ($review?->reviewer_name)
                                    — {{ $review->reviewer_name }}
                                @endif
                            </p>
                            @if ($locations->get($review?->location_id)?->name)
                                <p class="text-sm text-ink-2">{{ $locations->get($review->location_id)->name }}</p>
                            @endif
                        </div>

                        @if ($review?->comment)
                            <blockquote class="border-l-2 border-rule pl-3 text-base text-ink-2">
                                {{ $review->comment }}
                            </blockquote>
                        @endif

                        {{--
                            THE AGE IS THE HONEST SIGNAL AND IT IS WHY IT LEADS.
                            The row cannot tell a job dispatched forty seconds
                            ago from one that gave up three weeks ago —
                            `PostReplyJob::execute()`'s `unavailable` returns
                            write nothing at all (6528) — so rather than invent
                            a distinction, the card shows when the decision was
                            taken and lets a three-week-old one speak for
                            itself.
                        --}}
                        @if ($reply->approved_at)
                            <p class="text-base text-ink-2">Approved {{ $reply->approved_at->diffForHumans() }}.</p>
                        @endif

                        @if ($state)
                            <p class="text-base text-ink">{{ $state->label() }}</p>
                            @if ($state->nextStep())
                                <p class="text-base text-ink-2">{{ $state->nextStep() }}</p>
                            @endif
                        @endif

                        <label class="block">
                            <span class="sr-only">Approved reply</span>
                            <textarea
                                wire:model="drafts.{{ $reply->id }}"
                                rows="4"
                                class="w-full rounded-[--radius-control] border border-rule bg-canvas px-3 py-2 text-base text-ink"
                            ></textarea>
                        </label>

                        {{--
                            THE RETRY, AND IT IS 1755's DECISION MADE REACHABLE
                            (6524). `ReviewReplies::approve()` already permits
                            an `Approved` row — it refuses only `Posted` — and
                            1755 deliberately left that permission in place for
                            whoever built this view. It stays: `PostReplyJob`
                            itself writes *"It is still saved here and can be
                            edited and approved again"* onto the row when Google
                            declines, and refusing here would make that sentence
                            a lie told by the application to the person it was
                            written for. ⛔ **AND IT IS STILL THE RETRY THIS
                            PRODUCT HAS, FOR A NARROWER REASON THAN THE ONE
                            WRITTEN HERE UNTIL 2026-08-28 (11265).** This read
                            *"it is also the only path to publication a blocked
                            reply has, because nothing re-dispatches"*, and
                            `reviews:retry-stranded-replies` re-dispatches
                            (11040–11046). **What survives is that the sweep is
                            one attempt per owner decision** — it stamps
                            `replies.publish_retry_dispatched_at` and never
                            clears it outside `approve()` — **and that it
                            refuses four of the five cases a card here can
                            carry.** So for a reply Google declined, one whose
                            attempt went unanswered, one a worker killed
                            mid-flight, and one the sweep has already had its
                            turn at, **this button is the only path left**
                            (1755, 6524). ⚠️ **The one-shot bound is the load-
                            bearing half of that sentence**: if the stamp ever
                            stops being per-decision, this paragraph is the
                            first thing that becomes false.

                            ⛔ **"FOUR OF THE FIVE" WAS TRUE ON THE BRANCH IT
                            WAS WRITTEN ON AND IS FALSE ON THIS TREE — 2026-08-28
                            (11364).** `d348caa0`'s parent has five states; the
                            merge with `eeb45db6` gave it a sixth. It is kept
                            above on 6940's rule. What is true is a property
                            rather than a fraction: **the sweep
                            dispatches exactly one of the states a card here can
                            carry — `NotPublishedYet` — and refuses every
                            other**, because `strandedAwaitingPublication()`
                            filters `publish_unconfirmed_at` and
                            `provider_declined_at` out, `abandonedReplyIds()`
                            skips the killed run, and the command's own
                            per-business gates answer the platform switch, the
                            plan and the connection.

                            ⛔ **AND THE FOUR SITUATIONS LISTED ABOVE DO NOT
                            GROW BY ONE, WHICH IS THE TRAP THIS CORRECTION HAD
                            TO AVOID** (11365). *"Refused by the sweep"* and
                            *"this button is the only path left"* are two
                            different sets. `PublishingOff`, `NotConnected` and
                            now `NotEntitled` are the **transient** blockers the
                            sweep exists to wait for — every one of their gates
                            *"skips without touching the column"*, so the sweep
                            takes those replies the hour the blocker clears and
                            the button is not the only path. The four above are
                            the rows the sweep will **never** take again on its
                            own. ⚠️ **THE SENTENCE THAT SAT HERE WAS FALSE IN
                            BOTH TENSES AND IS KEPT AND DATED RATHER THAN
                            DELETED** (4368's rule; corrected 2026-08-28,
                            11560). It read: *"`Account\ReplyQueue`'s docblock
                            says the list `grows a fifth member — a reply on an
                            account with no running plan`; it does not, and
                            correcting that file is owed to whoever owns it"*.
                            ⛔ **`ReplyQueue.php` was corrected in the same
                            commit that left this paragraph standing** (11452):
                            its docblock now says *"IT GROWS A FIFTH MEMBER"
                            WAS WRONG AND IS CORRECTED HERE*, so the claim was
                            already false when it was written and the debt was
                            already paid. ⚠️ **Nothing reddened for either
                            half** — a citation of a *class docblock* is
                            outside `CitationTest`'s document lint and outside
                            its test-file lint both, which is a gap named at
                            11561 rather than closed.

                            NO SKIP BUTTON, DELIBERATELY. `skip()` **deletes**
                            the row, and offering that beside a decision the
                            owner already recorded turns a "why is this not up
                            yet?" visit into a one-click discard of the text and
                            the audit subject both. Editing it to nothing is not
                            possible either — `approve()` refuses empty text.

                            NO NEW COMPONENT METHOD: this calls the same
                            `approve()` the queue above does, so there is one
                            authorisation path rather than two.
                        --}}
                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                wire:click="approve({{ $reply->id }})"
                                class="rounded-[--radius-control] bg-ink px-4 py-2 text-base font-medium text-canvas"
                            >
                                Approve again
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
