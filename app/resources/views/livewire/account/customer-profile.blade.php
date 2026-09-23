{{--
    One contact — "a timeline, not a form" (`34` §1.2).

    ⚠️ NO QUICK-ACTIONS ROW. §1.2 asks for Text · Log a call · Book · Send bill ·
    Request review, and every one of the five needs a subsystem this application
    does not have. Five disabled buttons would fail `22`'s rule that a disabled
    control explains itself in one sentence — the honest sentence for all five is
    the same one, and it belongs in a roadmap rather than on every contact.

    COLOUR IS NOT THE SIGNAL (`22`): consent state, suppression state and routing
    outcomes are all carried by words.
--}}

<div class="space-y-8">
    <div>
        {{--
            ⚠️ THE ONE HAND-WRITTEN LINK BETWEEN OWNER SCREENS THAT SURVIVES THE
            SHELL, AND IT IS NOT AN OVERSIGHT. This is the only owner screen with
            no nav entry of its own — a contact is one row rather than a
            destination — so the nav marks *Your customers* as current and offers
            nothing that means "back to the list I came from". Removing this
            leaves a screen you can reach and cannot leave by any deliberate
            affordance.
        --}}
        <p class="text-sm text-ink-2">
            <a href="{{ route('account.customers') }}" class="underline">Your customers</a>
        </p>
        <h1 class="mt-1 font-display text-2xl font-semibold text-ink">
            {{ $customer->name ?: $customer->email ?: $customer->phone ?: 'A customer' }}
        </h1>
        <p class="mt-1 text-base text-ink-2">
            {{ $customer->email ?: 'No email' }} ·
            {{--
                Tap-to-call, never tap-to-text (decision 1328): no SMS sender
                exists, so lane-legal is false for every contact and an sms:
                link would be a button that cannot work.
            --}}
            @if ($customer->phone)
                <a href="tel:{{ $customer->phone }}" class="underline">{{ $customer->phone }}</a>
            @else
                No phone
            @endif
        </p>

        {{--
            The header consent badge (§1.2) — the same ConsentService::badgesFor()
            the list renders from, so the two screens cannot disagree, and
            standing suppression rather than the trail, so it agrees with the
            Never-contact control below. The panel at the bottom is the history.
        --}}
        <p class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $consentBadge->stopped !== [] ? 'bg-alert-bg text-alert' : ($consentBadge->agreed !== [] ? 'bg-ok-bg text-ok' : 'bg-paper text-ink-2 border border-rule') }}" data-consent-badge>
            {{ $consentBadge->label() }}
        </p>
        @if ($customer->tags)
            <p class="mt-2 flex flex-wrap gap-2" data-tags>
                @foreach ($customer->tags as $tag)
                    <span class="rounded-full border border-rule bg-paper px-2 py-0.5 text-sm text-ink-2">{{ $tag }}</span>
                @endforeach
            </p>
        @endif

        {{--
            ⚠️ NO "Came from" LINE, AND THAT IS A FINDING RATHER THAN A CUT.
            §1.2's header names source ("Came from: Google · March") and
            `customers` has no acquisition-source column with a writer —
            `consent_source` exists but is the *newest* consent event's capture
            surface, which changes when an imported contact later submits
            feedback, so labelling it "came from" would misstate the one thing
            the field claims. An empty "Came from" reads as "we don't know"
            rather than "nothing fills this yet" — decision 1222's mistake in a
            header. The field arrives with a real source column and its writer.
        --}}

        {{--
            §1.2's inline-editable name and tags, and the state (1594).

            ⚠️ THE STATE AND NOT AN ADDRESS. `customers` has no address columns
            and the only reader — the state mini-TCPA gate — consumes a
            two-letter USPS code, so a street address here would be personal data
            stored for nobody. Asked plainly, because the reason an owner would
            fill it in is the one written under the label.

            ⚠️ AND NOTHING GUESSES IT (1595). Not from the phone number's area
            code (numbers port), not from the business's own address. A guessed
            jurisdiction produces confident compliance with the wrong statute.
        --}}
        <details class="mt-3 group">
            <summary class="flex min-h-11 cursor-pointer items-center text-base font-medium text-ink underline [&::-webkit-details-marker]:hidden">
                Edit name, tags and state
                <svg class="ml-2 h-4 w-4 text-ink-2 transition-transform group-open:-rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </summary>
            <form wire:submit="saveDetails" class="mt-2 space-y-3">
                <div>
                    <label for="contact-name" class="block text-sm font-medium text-ink">Name</label>
                    <input
                        id="contact-name"
                        type="text"
                        wire:model="contactName"
                        class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                    >
                    @error('contactName')
                        <p class="mt-1 text-sm text-ink">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="contact-tags" class="block text-sm font-medium text-ink">
                        Tags <span class="font-normal text-ink-2">— separated by commas</span>
                    </label>
                    <input
                        id="contact-tags"
                        type="text"
                        wire:model="contactTags"
                        placeholder="vip, repeat customer"
                        class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                    >
                    @error('contactTags')
                        <p class="mt-1 text-sm text-ink">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="contact-region" class="block text-sm font-medium text-ink">
                        State
                        <span class="font-normal text-ink-2">— where they are, not where you are</span>
                    </label>
                    <select
                        id="contact-region"
                        wire:model="contactRegion"
                        aria-describedby="contact-region-help"
                        class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                    >
                        <option value="">Not known</option>
                        @foreach (\App\Enums\UsState::cases() as $state)
                            <option value="{{ $state->value }}">{{ $state->label() }}</option>
                        @endforeach
                    </select>
                    <p id="contact-region-help" class="mt-1 text-sm text-ink-2">
                        Some states set their own rules about when a business may text
                        someone. Leave this blank if you are not sure — we will not send
                        offers to a customer whose state we do not know.
                    </p>
                    @error('contactRegion')
                        <p class="mt-1 text-sm text-ink">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <x-ui.submit size="default" target="saveDetails" busy="Saving…">Save</x-ui.submit>
                </div>
            </form>
        </details>
    </div>

    {{--
        The merged-away notice — the profile of a contact that has been folded
        into another one. `CustomerDirectory::find()` deliberately still resolves
        it, because a 404 on a contact somebody merged last week reads as data
        loss rather than as tidying. There is no Undo here on purpose: the undo
        belongs beside the history it would split, which is the survivor's.
    --}}
    @if ($mergedAway !== null)
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5" data-merged-away>
            <h2 class="font-display text-lg font-semibold text-ink">This customer was merged</h2>
            <p class="mt-2 text-base text-ink-2">
                You told us this was the same person as another customer, so everything
                here now lives on their profile. We won’t message this one.
            </p>
            <p class="mt-3 text-base">
                <a href="{{ route('account.customers.show', $mergedAway->survivor_id) }}" class="underline">
                    Open the customer they were merged into
                </a>
            </p>
        </section>
    @endif

    {{--
        Merge duplicates (`34` §1.2) — the banner on both profiles, and the
        side-by-side picker it opens.

        ⚠️ CONTACT DETAILS ARE NOT ON THE PICKER, AND THE SENTENCE SAYS SO
        RATHER THAN THE ABSENCE SPEAKING FOR ITSELF. A consent record names a
        customer row and a channel and carries no address, so moving an email
        onto a row that already holds one would grant a permit for an address
        nobody proved themselves reachable on — see CustomerMerges.
    --}}
    {{--
        ⚠️ NO `$mergedAway === null` HERE, AND ITS ABSENCE IS THE DECISION.
        The first version carried one and mutation found it decorative: the
        detector already refuses to propose anything to a folded-away contact,
        so the extra condition made that filter unfalsifiable from this side and
        this side unfalsifiable from that one — decision 1461's badge, in a
        banner. `MergeDuplicateDetector` is the one owner of "who may be
        proposed to whom".
    --}}
    @if ($duplicatesFailed)
        {{--
            §1.2's error state on the second read a retry can actually fix. It
            says what is missing rather than claiming there are no duplicates —
            an owner shown "no duplicates found" after a failed scan would
            reasonably stop looking, which is 1222's mistake (an empty answer
            standing in for an unanswered question) in a banner.
        --}}
        <x-ui.error-panel heading="We couldn’t check for duplicates" data-duplicates-error>
            This customer’s details loaded fine. We just couldn’t look for other
            customers who share them this time.
        </x-ui.error-panel>
    @elseif ($duplicates->isNotEmpty())
        {{--
            empty-state: absent because a panel announcing "no duplicates" would
            appear on every well-kept customer in the book, and the answer it
            gives is one nobody came to this page to ask. The section appearing
            at all is the finding.
        --}}
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5" data-duplicates>
            <h2 class="font-display text-lg font-semibold text-ink">This might be the same person</h2>

            @if ($mergeCandidate === null)
                <p class="mt-2 text-base text-ink-2">
                    These customers share a phone number or an email address with this one.
                    Merging keeps this customer and folds the other one’s history in.
                </p>
                <ul class="mt-3 space-y-3">
                    @foreach ($duplicates as $duplicate)
                        <li class="flex flex-wrap items-center justify-between gap-3 border-t border-rule pt-3">
                            <span class="text-base text-ink">
                                {{ $duplicate->name ?: $duplicate->email ?: $duplicate->phone ?: 'A customer' }}
                                <span class="text-ink-2">
                                    · {{ $duplicate->email ?: 'No email' }} · {{ $duplicate->phone ?: 'No phone' }}
                                </span>
                            </span>
                            <x-ui.button size="default" variant="secondary" wire:click="startMerge({{ $duplicate->id }})">
                                Compare and merge
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-base text-ink-2">
                    Choose what the one customer should be called. Everything else — their
                    reviews, messages, notes and consent — comes across either way.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2" data-merge-picker>
                    <div class="rounded-[--radius-panel] border border-rule p-4">
                        <p class="text-sm font-medium text-ink">The customer you keep</p>
                        <p class="mt-1 text-base text-ink">{{ $customer->name ?: 'No name' }}</p>
                        <p class="mt-1 text-sm text-ink-2">
                            {{ $customer->email ?: 'No email' }} · {{ $customer->phone ?: 'No phone' }}
                        </p>
                        <p class="mt-1 text-sm text-ink-2">
                            {{ $customer->tags ? implode(', ', $customer->tags) : 'No tags' }}
                        </p>
                    </div>
                    <div class="rounded-[--radius-panel] border border-rule p-4">
                        <p class="text-sm font-medium text-ink">The one you fold in</p>
                        <p class="mt-1 text-base text-ink">{{ $mergeCandidate->name ?: 'No name' }}</p>
                        <p class="mt-1 text-sm text-ink-2">
                            {{ $mergeCandidate->email ?: 'No email' }} · {{ $mergeCandidate->phone ?: 'No phone' }}
                        </p>
                        <p class="mt-1 text-sm text-ink-2">
                            {{ $mergeCandidate->tags ? implode(', ', $mergeCandidate->tags) : 'No tags' }}
                        </p>
                    </div>
                </div>

                <form wire:submit="merge" class="mt-4 space-y-3">
                    <fieldset>
                        <legend class="text-sm font-medium text-ink">Name to keep</legend>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-2">
                            <label class="flex min-h-11 items-center gap-2 text-base text-ink">
                                <input type="radio" wire:model="chooseName" value="survivor">
                                {{ $customer->name ?: 'No name' }}
                            </label>
                            <label class="flex min-h-11 items-center gap-2 text-base text-ink">
                                <input type="radio" wire:model="chooseName" value="merged">
                                {{ $mergeCandidate->name ?: 'No name' }}
                            </label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="text-sm font-medium text-ink">Tags to keep</legend>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-2">
                            <label class="flex min-h-11 items-center gap-2 text-base text-ink">
                                <input type="radio" wire:model="chooseTags" value="survivor">
                                {{ $customer->tags ? implode(', ', $customer->tags) : 'No tags' }}
                            </label>
                            <label class="flex min-h-11 items-center gap-2 text-base text-ink">
                                <input type="radio" wire:model="chooseTags" value="merged">
                                {{ $mergeCandidate->tags ? implode(', ', $mergeCandidate->tags) : 'No tags' }}
                            </label>
                        </div>
                    </fieldset>

                    <p class="text-sm text-ink-2">
                        We keep this customer’s email and phone. The other one’s are freed up,
                        and come back if you undo.
                    </p>

                    <div class="flex flex-wrap gap-3">
                        <x-ui.button size="default" type="submit">Merge them</x-ui.button>
                        <x-ui.button size="default" variant="secondary" wire:click="cancelMerge" type="button">
                            Keep them separate
                        </x-ui.button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    {{--
        The undo (`34` §7's build-failing "merge is undoable 30 days"). It shows
        only while a merge is still reversible — a button that would be refused
        is decision 1227's greyed control in a different costume, inviting
        somebody to ask us to press it.
    --}}
    @if ($undoableMerges->isNotEmpty())
        {{--
            empty-state: absent because an undo panel with nothing in it invites
            somebody to reverse a merge that never happened, and the comment
            above already records why a refused button is worse than none.
        --}}
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5" data-undoable-merges>
            <h2 class="font-display text-lg font-semibold text-ink">Recently merged</h2>
            <ul class="mt-3 space-y-3">
                @foreach ($undoableMerges as $merge)
                    <li class="flex flex-wrap items-center justify-between gap-3 border-t border-rule pt-3">
                        <span class="text-base text-ink-2">
                            Merged {{ $merge->merged_at->diffForHumans() }}. You can undo this
                            until {{ $merge->merged_at->addDays(app(\App\Services\Crm\CustomerMerges::class)->undoWindowDays())->toFormattedDateString() }}.
                        </span>
                        <x-ui.button size="default" variant="secondary" wire:click="undoMerge({{ $merge->id }})">
                            Undo this merge
                        </x-ui.button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{--
        Archive (`44` §8) — the third contact state, and not a suppression:
        hidden from the default list and pickers, excluded from sends by
        ConsentService::decide() asking the column (1327), fully restorable,
        timeline intact. The sentences say all of that at the point of use,
        which is D-205's rule.
    --}}
    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Keep or archive</h2>

        @if ($customer->archived_at !== null)
            <p class="mt-2 text-base text-ink" data-archived="on">
                This customer is archived — hidden from your list and left out of
                every message. Their history is untouched.
            </p>
            <div class="mt-4">
                <x-ui.button size="default" wire:click="restore" variant="secondary">
                    Restore this customer
                </x-ui.button>
            </div>
        @else
            <p class="mt-2 text-base text-ink-2" data-archived="off">
                Archiving hides this customer from your list and leaves them out of
                every message. Their history stays, and you can restore them any time.
            </p>
            <div class="mt-4">
                <x-ui.button size="default" wire:click="archive" variant="secondary">
                    Archive this customer
                </x-ui.button>
            </div>
        @endif
    </section>

    {{--
        Delete — D-205's third state (1540), and `34` §1.2's own word for it is
        TOMBSTONE, which is why this renders rather than 404s. The profile of a
        deleted contact is reachable by bookmark and says plainly what state
        they are in and how long the undo has left; after that it says the same
        thing with no button, because "we cannot undo this any more" is an
        answer and a 404 is not.

        The sentences carry the whole meaning at the point of use (D-205's
        rule), including the part an owner would otherwise assume wrong: their
        history and their consent records are kept. Nothing is ever purged.
    --}}
    <section class="rounded-[--radius-panel] border border-rule bg-card p-5" data-delete>
        <h2 class="font-display text-lg font-semibold text-ink">Remove from your customers</h2>

        @if ($customer->deleted_at !== null)
            @php($restorableUntil = $customer->deleted_at->addDays(\App\Services\Crm\CustomerEditor::RESTORE_WINDOW_DAYS))

            <p class="mt-2 text-base text-ink" data-deleted="on">
                You deleted this customer {{ $customer->deleted_at->diffForHumans() }}. They are
                gone from your list and left out of every message. Their history and
                their consent records are kept.
            </p>

            @if ($restorableUntil->isFuture())
                <p class="mt-2 text-base text-ink-2" data-restorable="yes">
                    You can bring them back until {{ $restorableUntil->toFormattedDateString() }}.
                </p>
                <div class="mt-4">
                    <x-ui.button size="default" wire:click="undelete" variant="secondary">
                        Bring this customer back
                    </x-ui.button>
                </div>
            @else
                {{--
                    No button, and no greyed one either — 1227's rule, that a
                    disabled control is an invitation to ask for it to be
                    enabled. The sentence is the whole answer.
                --}}
                <p class="mt-2 text-base text-ink-2" data-restorable="no">
                    The {{ \App\Services\Crm\CustomerEditor::RESTORE_WINDOW_DAYS }} days to bring
                    them back have passed, so this cannot be undone. Nothing was destroyed.
                </p>
            @endif
        @elseif ($confirmingDelete)
            {{--
                The confirm archive deliberately does not have (1503, 1546). It
                is here because this is the one contact action whose undo
                expires, and the sentence says so before the tap rather than
                after it.
            --}}
            <p class="mt-2 text-base text-ink" data-confirming-delete>
                Delete {{ $customer->name ?: $customer->email ?: $customer->phone ?: 'this customer' }}?
                They leave your list and every message. You can bring them back for
                {{ \App\Services\Crm\CustomerEditor::RESTORE_WINDOW_DAYS }} days, and after that
                you cannot.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                {{--
                    ⚠️ `id="confirm-delete"` IS FOR THE TEST HARNESS, NOT FOR
                    PRODUCTION BEHAVIOUR. The button's own text carries a
                    comma, and Pest's browser plugin's `click('Yes, delete
                    them')` treats a comma as CSS selector syntax rather than
                    text to match — silently routed to the raw CSS engine,
                    where it fails to parse (a mid-wave finding, wave 36 lane
                    B). `tests/Browser/AccountCustomerScreenTest.php` clicks
                    this id rather than the label.
                --}}
                <x-ui.button id="confirm-delete" size="default" wire:click="delete">
                    Yes, delete them
                </x-ui.button>
                <x-ui.button size="default" wire:click="cancelDelete" variant="secondary">
                    Keep this customer
                </x-ui.button>
            </div>
        @else
            <p class="mt-2 text-base text-ink-2" data-deleted="off">
                Deleting takes this customer off your list and out of every message. You
                can bring them back for {{ \App\Services\Crm\CustomerEditor::RESTORE_WINDOW_DAYS }}
                days. Their history and their consent records are always kept.
            </p>
            <div class="mt-4">
                <x-ui.button size="default" wire:click="confirmDelete" variant="secondary">
                    Delete this customer
                </x-ui.button>
            </div>
        @endif
    </section>

    {{-- Never contact (§1.2). The consequences are stated before the tap, not after. --}}
    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Contacting them</h2>

        @if ($neverContact->on)
            <p class="mt-2 text-base text-ink" data-never-contact="on">
                We won’t contact this customer.
            </p>

            @if ($neverContact->releasable)
                <p class="mt-2 text-base text-ink-2">
                    You asked us not to. You can undo this.
                </p>
                <div class="mt-4">
                    <x-ui.button size="default" wire:click="clearNeverContact" variant="secondary">
                        Allow contact again
                    </x-ui.button>
                </div>
            @else
                {{--
                    ⚠️ THE BUTTON IS ABSENT, NOT DISABLED, AND THE SENTENCE IS THE
                    WHOLE FEATURE. A customer's own STOP is not the owner's to
                    reverse; showing a greyed control would invite them to ask us
                    to press it for them.
                --}}
                <p class="mt-2 text-base text-ink-2" data-never-contact-refusal>
                    {{ $neverContact->refusal }}
                </p>
            @endif
        @elseif ($confirmingNeverContact)
            <p class="mt-2 text-base text-ink">
                We’ll stop messaging this customer on every channel — review invites
                included. Their reviews and history stay exactly as they are.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-ui.button size="default" wire:click="markNeverContact">
                    Never contact them
                </x-ui.button>
                <x-ui.button size="default" wire:click="$set('confirmingNeverContact', false)" variant="secondary">
                    Keep contacting them
                </x-ui.button>
            </div>
        @else
            <p class="mt-2 text-base text-ink-2" data-never-contact="off">
                We may message this customer when there’s a reason to.
            </p>
            <div class="mt-4">
                <x-ui.button size="default" wire:click="$set('confirmingNeverContact', true)" variant="secondary">
                    Never contact them
                </x-ui.button>
            </div>
        @endif
    </section>

    {{--
        "Export this customer" (`44` §10) — the surface an individual
        data-subject request is answered from, and the reason this panel is
        worded at the person forwarding the file rather than at the person
        asking for it.

        ⚠️ THE COPY NAMES WHAT IS AND IS NOT IN THE FILE ON THE SCREEN AS WELL AS
        IN THE MANIFEST, because the manifest is inside a ZIP and this sentence
        is the one an owner actually reads before they press the button.

        ⚠️ NO COLOUR CARRIES ANY OF IT — the state is a sentence, `22`'s rule.
    --}}
    <section class="rounded-[--radius-panel] border border-rule bg-card p-5" data-contact-export>
        <h2 class="font-display text-lg font-semibold text-ink">Everything you hold about them</h2>

        <p class="mt-2 text-base text-ink-2">
            A ZIP file with their record, their full history and their consent trail — the
            answer to send when somebody asks what you hold about them.
        </p>

        <p class="mt-2 text-base text-ink-2">
            Read the manifest inside before you send it on. Anything merged into this contact
            is in the file, and your own notes about them go with it.
        </p>

        @if ($contactExport)
            <p class="mt-3 text-sm text-ink-2" data-contact-export-state>
                @if ($contactExportUrl)
                    Ready — this link stops working
                    {{ $contactExport->expires_at?->diffForHumans() }}.
                @elseif ($contactExport->status->value === 'failed')
                    The last attempt could not be built. Try again below.
                @else
                    Building it now — we’ll email you a link when it’s ready.
                @endif
            </p>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-3">
            @if ($contactExportUrl)
                <x-ui.button :href="$contactExportUrl" size="default">Download it</x-ui.button>
            @endif

            <x-ui.button
                size="default"
                wire:click="exportContact"
                :variant="$contactExportUrl ? 'secondary' : 'primary'"
            >
                {{ $contactExportUrl ? 'Build a new one' : 'Export this customer' }}
            </x-ui.button>
        </div>
    </section>

    {{--
        "Remind me" (`44` §2) — a follow-up on this contact, with the four date
        choices. The created and completed events appear in the history below;
        the list of everything due lives under More → Follow-ups.
    --}}
    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Remind me</h2>

        <form wire:submit="remind" class="mt-3 space-y-3">
            <div>
                <label for="reminder-title" class="block text-sm font-medium text-ink">
                    What
                </label>
                <input
                    id="reminder-title"
                    type="text"
                    wire:model="reminderTitle"
                    placeholder="Call back · Send quote · Check they’re happy"
                    class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                >
                @error('reminderTitle')
                    <p class="mt-1 text-sm text-ink">{{ $message }}</p>
                @enderror
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-ink">When</legend>
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-2">
                    <label class="flex min-h-11 items-center gap-2 text-base text-ink">
                        <input type="radio" wire:model.live="reminderWhen" value="today"> Today
                    </label>
                    <label class="flex min-h-11 items-center gap-2 text-base text-ink">
                        <input type="radio" wire:model.live="reminderWhen" value="tomorrow"> Tomorrow
                    </label>
                    <label class="flex min-h-11 items-center gap-2 text-base text-ink">
                        <input type="radio" wire:model.live="reminderWhen" value="next_week"> Next week
                    </label>
                    <label class="flex min-h-11 items-center gap-2 text-base text-ink">
                        <input type="radio" wire:model.live="reminderWhen" value="date"> Pick a date
                    </label>
                </div>
            </fieldset>

            @if ($reminderWhen === 'date')
                <div>
                    <label for="reminder-date" class="block text-sm font-medium text-ink">
                        Date
                    </label>
                    <input
                        id="reminder-date"
                        type="date"
                        wire:model="reminderDate"
                        class="mt-1 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                    >
                    @error('reminderDate')
                        <p class="mt-1 text-sm text-ink">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div>
                <x-ui.button size="default" type="submit">Remind me</x-ui.button>
            </div>
        </form>
    </section>

    {{-- Notes (§1.2). Append only — see CustomerProfile::addNote() for why. --}}
    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Your notes</h2>

        <form wire:submit="addNote" class="mt-3">
            <label for="note" class="sr-only">Add a note</label>
            <textarea
                id="note"
                wire:model="note"
                rows="3"
                placeholder="Wants the early slot. Prefers a text."
                class="w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
            ></textarea>
            @error('note')
                <p class="mt-1 text-sm text-ink">{{ $message }}</p>
            @enderror
            <div class="mt-3">
                <x-ui.button size="default" type="submit">Add note</x-ui.button>
            </div>
        </form>
    </section>

    {{--
        The timeline (§1.2). Six sources, because six stores have writers — the
        sixth is short-link clicks, which joined when `ReviewInviteSender` began
        minting links (2495's gap). The count moves with the union in
        `CustomerTimeline`; it read "four" while there were five.
    --}}
    <section>
        <h2 class="font-display text-lg font-semibold text-ink">History</h2>

        {{--
            §1.2's loading skeleton, with the honest label the component
            demands. It covers the reads a person waits on — adding a note, a
            reminder, a merge — rather than the whole page.
        --}}
        <div wire:loading.delay wire:target="addNote, remind, merge, undoMerge, undelete, delete" class="mt-3">
            <x-ui.skeleton label="Loading their history…" :lines="3" />
        </div>

        <div wire:loading.delay.remove wire:target="addNote, remind, merge, undoMerge, undelete, delete">
        @if ($timelineFailed)
            {{--
                §1.2's error state: what happened, and a retry. It names the
                section rather than the system, and the rest of the profile is
                still on screen above it — which is the whole reason the catch
                in render() is around this one read.
            --}}
            <x-ui.error-panel heading="We couldn’t load their history" class="mt-3" data-timeline-error>
                Everything else on this page loaded. Their history is still there — we
                just couldn’t read it this time.
            </x-ui.error-panel>
        @elseif ($entries->isEmpty())
            {{--
                No action: everything that writes to this history is something
                the customer does or the system does on their behalf, so the one
                button that would belong here is one nobody can honestly offer.
            --}}
            <x-ui.empty-state class="mt-3" icon="◴">
                Nothing has happened with this customer yet. Calls, messages, reviews and
                notes all land here as they happen.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($entries as $entry)
                    <li class="rounded-[--radius-panel] border border-rule bg-card p-5" data-entry="{{ $entry->type->value }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-base text-ink">{{ $entry->headline }}</p>
                            <p class="text-sm text-ink-2">
                                {{-- "Not dated" rather than a blank: a blank reads as a rendering fault. --}}
                                {{ $entry->occurredAt?->diffForHumans() ?? 'Not dated' }}
                            </p>
                        </div>

                        @if ($entry->detail)
                            <p class="mt-1 text-base text-ink-2">{{ $entry->detail }}</p>
                        @endif

                        @if ($entry->actor)
                            <p class="mt-2 text-sm text-ink-2">{{ $entry->actor }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        </div>
    </section>

    {{--
        The consent panel (§1.2) — "surface, timestamp, notice version, readable
        by a human". This is ConsentService::proofFor() rendered, and it needed no
        new reader: that method already returns grants and withdrawals together,
        because a trail showing grants alone is a document in which every row is
        true and the whole is false.
    --}}
    <section>
        <h2 class="font-display text-lg font-semibold text-ink">Consent history</h2>

        @if ($consentTrail->isEmpty())
            {{--
                ⚠️ NO ACTION HERE, AND THAT IS A COMPLIANCE LINE RATHER THAN A
                DESIGN ONE. The action a reader wants is "ask them" — and consent
                is the customer's to give, captured on a page they filled in, so
                a button here would be an invitation to manufacture the one
                record in this system whose entire job is to be true.
            --}}
            <x-ui.empty-state class="mt-3" icon="✓">
                This customer has never agreed to messages, so we can’t message them.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($consentTrail as $event)
                    <li class="rounded-[--radius-panel] border border-rule bg-card p-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-base text-ink">
                                {{ $event->type === \App\Enums\ConsentEventType::Consent
                                    ? 'Agreed to '.$event->channel->value
                                    : 'Asked us to stop — '.$event->channel->value }}
                            </p>
                            <p class="text-sm text-ink-2">
                                {{ $event->occurredAt?->diffForHumans() ?? 'Not dated' }}
                            </p>
                        </div>
                        <p class="mt-1 text-sm text-ink-2">{{ $event->reason() }}</p>
                        @if ($event->consentRecord?->disclosure_version)
                            <p class="mt-1 text-sm text-ink-2">
                                Wording version {{ $event->consentRecord->disclosure_version }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
