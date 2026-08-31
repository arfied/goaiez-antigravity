{{--
    Health-information tenants and their Business Associate Agreements.

    Deliberately plain, like the review queue and the legal documents screen —
    this exists so `BaaRecords` and `TenantClassification::reclassify()` have a
    caller. See the component for why it acts on one business at a time rather
    than listing them.

    THE SCREEN NEVER OFFERS AN ACTION THE SERVICE WILL REFUSE, where it can tell
    in advance: no execution form until a final version of the template is
    published (`29` §12.2), no "raise" control for a tenant already raised, and
    no lowering control at all. A button that exists and then explains why it
    cannot work is how somebody concludes the rule is a bug.

    ⚠️ THE INVERSE IS NOT CLAIMED, AND ONE CASE IS LIVE. "Where it can tell in
    advance" is doing real work: this screen gates the execution form on
    `current()` — is any final version published at all — while the service
    resolves and re-checks `currentAsOf()`, the version live on the day the
    tenant signed. A signature dated before that version was published is
    therefore offered the form and refused by the service, which is deliberate:
    resolving a version would mean knowing the date before it has been typed.
    See the comment above that form. What carries it is that the refusal is
    never silent — `act()` shows the service's own sentence, which explains the
    rule and names both dates.

    COLOUR IS NOT THE SIGNAL (`22`). Every state here is a word.

    ⚠️ SIGNER NAMES ARE PII AND APPEAR ONLY IN THIS PANEL, behind the
    platform-staff gate. They are never in a toast, a URL or a log.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Health information</h1>
        <p class="mt-1 text-base text-ink-2">
            Tenants whose customers' words are patient records, and the
            agreement that has to be signed before we hold them.
        </p>
    </div>

    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Find a business</h2>

        <p class="mt-1 text-base text-ink-2">
            By its number. There is no list yet — see the note at the foot of
            this page.
        </p>

        <form wire:submit="lookUp" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-sm text-ink-2">Business number</span>
                <input
                    wire:model="lookup"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 w-40 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                >
            </label>

            <x-ui.submit size="default" target="lookUp" busy="Looking…">Show this business</x-ui.submit>
        </form>
    </section>

    @if ($business)
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">{{ $business->name }}</h2>

            <p class="mt-1 text-base text-ink-2">
                Business {{ $business->id }} · Data handling:
                <span class="font-medium text-ink">{{ $business->data_classification->label() }}</span>
            </p>

            @if ($business->data_classification->requiresPhiIsolation())
                <p class="mt-4 text-base text-ink-2">
                    Agreement:
                    <span class="font-medium text-ink">
                        {{ $record?->status->label() ?? 'No agreement opened' }}
                    </span>
                </p>

                @if ($inForce)
                    {{-- The evidence, in the one place it is shown. --}}
                    <ul class="mt-3 space-y-1 text-base text-ink-2">
                        <li>
                            Signed for the business by {{ $record->tenant_signer_name }}@if ($record->tenant_signer_title), {{ $record->tenant_signer_title }}@endif
                            on {{ $record->tenant_signed_at?->format('j F Y') }}.
                        </li>
                        <li>
                            Signed for GO AI EZ by {{ $record->goaiez_signer }}
                            on {{ $record->goaiez_signed_at?->format('j F Y') }}.
                        </li>
                        <li>
                            Against version {{ $record->legalDocument?->version }} of the
                            Business Associate Agreement.
                        </li>
                    </ul>

                    <div class="mt-6 border-t border-rule pt-5">
                        <h3 class="font-display text-base font-semibold text-ink">End this agreement</h3>

                        {{--
                            ⚠️ THE WARNING SITS BEFORE THE BUTTON BECAUSE
                            THIS IS THE MOST TERMINAL CONTROL ON THE SCREEN,
                            and it read as the least. The raise form says
                            "this cannot be undone here" and this one said
                            nothing, while every exit is closed behind it:
                            `revoke()` refuses a second ending,
                            `recordExecution()` refuses anything but a
                            pending record, re-execution after revocation is
                            not built, and `reclassify()` will not lower a
                            tenant out of health information. So a covered
                            entity is left handling health information with
                            no agreement in force and no path in this
                            application back to one.
                        --}}
                        <p class="mt-1 text-base text-ink-2">
                            This cannot be undone here. A tenant cannot be moved back
                            out of health information, and this screen cannot open a
                            replacement agreement — so they stay a health-information
                            tenant with no agreement in force.
                        </p>

                        <form wire:submit="endAgreement" class="mt-3 flex flex-wrap items-end gap-3">
                            <label class="block grow" for="end-reason">
                                <span class="text-sm text-ink-2">Why it ended</span>
                                <input
                                    wire:model="endReason"
                                    id="end-reason"
                                    type="text"
                                    @error('endReason') aria-describedby="end-reason-error" @enderror
                                    class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                                >
                                @error('endReason')
                                    {{-- Text, not colour alone (`29` §2 rule 46). --}}
                                    <span id="end-reason-error" class="mt-1 block text-sm text-alert">{{ $message }}</span>
                                @enderror
                            </label>

                            <x-ui.submit size="default" variant="secondary" target="endAgreement" busy="Ending…">
                                End agreement
                            </x-ui.submit>
                        </form>
                    </div>
                @elseif ($record?->status === \App\Enums\BaaStatus::Revoked)
                    <p class="mt-3 text-base text-ink-2">
                        Ended {{ $record->revoked_at?->format('j F Y') }} — {{ $record->revoke_reason }}.
                        A new agreement is not something this screen can open yet.
                    </p>
                @elseif (! $template || $template->is_placeholder)
                    {{--
                        ⚠️ WHAT THIS SAYS AND WHAT IT MUST NOT SAY. It said
                        "healthcare counsel reviews and publishes it first",
                        which told the operator counsel had already acted.
                        Nothing here can know that: the reviewer name, the
                        placeholder checkbox and the publish button are three
                        controls one staff user operates in one session. What
                        is enforced is that a named reviewer is recorded and
                        the text is not a working draft. Whether that
                        reviewer was healthcare counsel is `29` §12.2 gate 2
                        — a human prelaunch gate, and a person's job.

                        The service refuses this execution too; the screen
                        simply does not offer it.
                    --}}
                    <p class="mt-3 text-base text-ink-2">
                        No final version of the Business Associate Agreement is
                        published yet. Record a reviewer and publish a final version
                        first — nothing can be signed against a working draft.
                        Whether that reviewer is healthcare counsel is a judgement
                        somebody has to make; this screen cannot check it.
                    </p>
                @else
                    <div class="mt-6 border-t border-rule pt-5">
                        <h3 class="font-display text-base font-semibold text-ink">
                            Record the signed agreement
                        </h3>

                        {{--
                            ⚠️ THE VERSION NAMED HERE IS THE NEWEST ONE, AND
                            THE VERSION RECORDED IS THE ONE THAT WAS CURRENT
                            ON THE DAY THEY SIGNED. Those are the same thing
                            until a second version is published, and this
                            sentence has to survive the day they differ —
                            the alternative is a screen that names v1.1 and
                            writes v1.0, which is worse than saying so.

                            ⚠️ AND THIS IS THE INVERSE OF THE HEADER'S CLAIM,
                            WHICH THE HEADER DOES NOT MENTION. "The screen
                            never offers an action the service will refuse"
                            is one direction; the gate above this form is
                            `current()` — is *anything* final published — and
                            the service resolves and checks `currentAsOf()`,
                            the version live on the signing date. So the
                            screen can offer an execution the service then
                            refuses: a signature dated before the first final
                            version was published gets the form and a
                            sentence back. Closing it means resolving a
                            version before a date has been typed, and the
                            owner has ruled that it stays. The refusal is
                            the one that must not be silent, and it is not —
                            `act()` shows the service's own message.
                        --}}
                        <p class="mt-1 text-base text-ink-2">
                            The version recorded is the one that was published when
                            they signed. The newest is {{ $template->version }},
                            published {{ $template->published_at?->format('j F Y') }}.
                            You sign for GO AI EZ by recording it.
                        </p>

                        <form wire:submit="recordExecution" class="mt-4 space-y-4">
                            {{--
                                All five error blocks on this screen carry an
                                id and their input carries `aria-describedby`
                                — WCAG 2.2 AA, the same association
                                `x-admin.form` makes, and the reason these
                                are not that component: it renders `Field`
                                objects bound to `state.*` and submits to a
                                `save()` action, none of which this screen
                                has.
                            --}}
                            <label class="block" for="tenant-signer">
                                <span class="text-sm text-ink-2">Who signed for the business</span>
                                <input
                                    wire:model="tenantSigner"
                                    id="tenant-signer"
                                    type="text"
                                    @error('tenantSigner') aria-describedby="tenant-signer-error" @enderror
                                    class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                                >
                                @error('tenantSigner')
                                    <span id="tenant-signer-error" class="mt-1 block text-sm text-alert">{{ $message }}</span>
                                @enderror
                            </label>

                            <label class="block" for="tenant-title">
                                <span class="text-sm text-ink-2">Their title</span>
                                <input
                                    wire:model="tenantTitle"
                                    id="tenant-title"
                                    type="text"
                                    @error('tenantTitle') aria-describedby="tenant-title-error" @enderror
                                    class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                                >
                                @error('tenantTitle')
                                    <span id="tenant-title-error" class="mt-1 block text-sm text-alert">{{ $message }}</span>
                                @enderror
                            </label>

                            <label class="block" for="tenant-signed-on">
                                <span class="text-sm text-ink-2">The date they signed</span>
                                <input
                                    wire:model="tenantSignedOn"
                                    id="tenant-signed-on"
                                    type="date"
                                    @error('tenantSignedOn') aria-describedby="tenant-signed-on-error" @enderror
                                    class="mt-1 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                                >
                                @error('tenantSignedOn')
                                    <span id="tenant-signed-on-error" class="mt-1 block text-sm text-alert">{{ $message }}</span>
                                @enderror
                            </label>

                            <x-ui.submit size="default" target="recordExecution" busy="Recording…">
                                Record the agreement
                            </x-ui.submit>
                        </form>
                    </div>
                @endif
            @else
                <div class="mt-5 border-t border-rule pt-5">
                    <h3 class="font-display text-base font-semibold text-ink">
                        Move to health information handling
                    </h3>

                    <p class="mt-1 text-base text-ink-2">
                        Their reviews stop going to an AI model and wait for a person
                        instead, and their Business Associate Agreement opens. This
                        cannot be undone here.
                    </p>

                    <form wire:submit="raiseToPhi" class="mt-4 flex flex-wrap items-end gap-3">
                        <label class="block grow" for="raise-reason">
                            <span class="text-sm text-ink-2">Why</span>
                            <input
                                wire:model="raiseReason"
                                id="raise-reason"
                                type="text"
                                @error('raiseReason') aria-describedby="raise-reason-error" @enderror
                                class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                            >
                            @error('raiseReason')
                                {{-- Text, not colour alone (`29` §2 rule 46). --}}
                                <span id="raise-reason-error" class="mt-1 block text-sm text-alert">{{ $message }}</span>
                            @enderror
                        </label>

                        <x-ui.submit size="default" target="raiseToPhi" busy="Moving…">
                            Move to health information
                        </x-ui.submit>
                    </form>
                </div>
            @endif
        </section>
    @endif

    {{--
        NO BACKTICKS IN RENDERED PROSE (11119, corrected at 11396). A
        Markdown-quoted table name prints as a literal backtick, and this
        paragraph is the one sentence explaining why the page has no list.
        `owner-channel-texts.blade.php` said it without the table name at all
        and that is the wording taken here.
    --}}
    <p class="text-sm text-ink-3">
        There is no list of health-information tenants on this page because the database will
        not produce one: it admits a reader only as a single business or as that
        business's owner. Reading them all would mean widening that
        deliberately, and nobody has.
    </p>
</div>
