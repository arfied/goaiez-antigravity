{{--
    The Google grants a deleted tenant left behind — decision 4888(a) and (b).

    TWO LISTS, ONE ACTION. "Owed" is recorded fact — TenantDeletion stamped it
    inside the transaction that destroyed the account — and an operator may
    retry one, per row. "Possibly orphaned" is an inference from before that
    recording existed, and NOTHING HERE ACTS ON IT: 4884's own words are that a
    sweep revoking on an inference disconnects a live customer's Google listing
    the first time a filter is wrong.

    "REVOKED" NEVER MEANS "CONFIRMED GONE AT GOOGLE". Every sentence on this
    page says what Zernio answered, not what is now true of the listing —
    4888(c)'s limit, stated in App\Enums\GbpRevocationOutcome::Revoked.

    NO PERSONAL DATA. A business number, a location number and an opaque Zernio
    profile reference are the only identifiers on this page. NO ACCOUNT IDS
    ANYWHERE — decision 4884: the caller of an orphan report never holds the
    value that decides whose Google listing a call reaches.

    THE THIRD SECTION ANSWERS A DIFFERENT QUESTION FROM THE FIRST TWO. Those are
    about businesses that no longer exist; "Connected at Zernio, unused here" is
    about accounts we are paying for right now — decision 6779(d).
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Google grants left behind by deleted accounts</h1>
        <p class="mt-1 text-base text-ink-2">
            When an account is deleted, Zernio still holds read and write access
            to that former customer's Google Business Profile until we tell it to
            stop. Ending that access happens automatically, every night — this is
            where that work is visible.
        </p>
    </div>

    {{-- ────────────────────────  Owed a revocation  ──────────────────────── --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">Owed a revocation</h2>

        <p class="mt-1 text-base text-ink-2">
            Recorded at the moment each account was deleted. A nightly check
            (<code class="font-mono text-sm">gbp:revoke-owed-grants</code>) retries
            every row here on its own; a retry from this page is the same request,
            made now instead of waiting for tonight.
        </p>

        @if ($owed->isEmpty())
            <x-ui.empty-state class="mt-4" icon="✓">
                Nothing is currently owed a revocation.
            </x-ui.empty-state>
        @else
            <ul class="mt-4 space-y-4">
                @foreach ($owed as $row)
                    @php
                        $binding = $row['binding'];
                        $lastAttempt = $row['lastAttempt'];
                    @endphp

                    <li wire:key="owed-{{ $binding->id }}" class="border-b border-rule pb-4 last:border-0 last:pb-0">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-base font-semibold text-ink">
                                    Business #{{ $binding->business_id }} — location #{{ $binding->location_id }}
                                </p>
                                <p class="text-sm text-ink-3">
                                    Deleted {{ $binding->revocation_owed_at?->format('j M Y, H:i') }}
                                    ({{ $binding->revocation_owed_at?->diffForHumans() }})
                                </p>
                            </div>

                            @if ($row['failedAttempts'] > 0)
                                <x-ui.status-pill
                                    :state="\App\Enums\SignalState::Attention"
                                    :label="$row['failedAttempts'].' failed '.\Illuminate\Support\Str::plural('attempt', $row['failedAttempts'])"
                                />
                            @endif
                        </div>

                        @if ($lastAttempt)
                            <p class="mt-2 text-sm text-ink-2">
                                Last tried {{ $lastAttempt->created_at->diffForHumans() }} by {{ $lastAttempt->actor }} —
                                @if ($lastAttempt->outcome === \App\Enums\GbpRevocationOutcome::Failed)
                                    Zernio refused it @if ($lastAttempt->reason) ({{ $lastAttempt->reason }})@endif.
                                @else
                                    Zernio accepted it.
                                @endif
                            </p>
                        @else
                            <p class="mt-2 text-sm text-ink-2">Not tried yet.</p>
                        @endif

                        {{--
                            A STAMP CAN NAME A BUSINESS THAT IS STILL HERE, and
                            pressing Retry on one of those disconnects a live
                            customer's Google listing — 4884's harm, reached from
                            the other end. Nothing clears revocation_owed_at
                            except deleting the row on success, so a re-connected
                            account used to carry the old obligation onto its new
                            owner. GbpConnections::retryRevocation() refuses it
                            too: the missing button is what an operator sees, not
                            what stops it.
                        --}}
                        @if ($row['businessSurvives'])
                            <x-ui.attention-card class="mt-3" state="attention" heading="This business still exists — do not revoke this grant">
                                Something recorded a revocation as owed for a business
                                that is still here, so this obligation is out of date.
                                Revoking it would disconnect a live customer's Google
                                Business Profile, and they cannot get it back without
                                connecting Google again. There is no action on this row
                                and the nightly check skips it.
                            </x-ui.attention-card>
                        @else
                            <div class="mt-3">
                                <x-ui.button
                                    size="default"
                                    variant="secondary"
                                    type="button"
                                    wire:click="retry({{ $binding->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="retry({{ $binding->id }})"
                                >
                                    Retry now
                                </x-ui.button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ────────────────────────  Possibly orphaned  ──────────────────────── --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">Possibly orphaned, from before this was tracked</h2>

        <p class="mt-1 text-base text-ink-2">
            These bindings name a business that no longer exists, from before
            deletions started recording an obligation to revoke. That makes this
            list an <span class="font-semibold text-ink">inference</span>, not a
            record — nothing here was ever confirmed to be a deleted customer's
            connection at the moment it was deleted.
        </p>

        {{--
            NO ACTION IS OFFERED, AND THAT IS THE POINT. Revoking on an
            inference, from a page, is how a live customer's Google listing
            gets disconnected by a bug in a filter — decision 4884.
        --}}
        <x-ui.attention-card class="mt-4" state="attention" heading="This list is never acted on automatically, and nothing here should be either">
            Confirm each one belonged to a genuinely deleted customer — against
            your own records, or against Zernio's own account list — before
            revoking anything by hand. A wrong guess here disconnects a business
            that is still a customer.
        </x-ui.attention-card>

        {{--
            ASKED FOR, NEVER RENDERED BY DEFAULT. Answering this question costs
            one tenancy switch per binding with no revocation stamp — which is
            every connected location of every paying customer — and decision
            4887 declined to pay it once a night. It was being paid on every
            page view and every Livewire round trip until 5076.
        --}}
        @if ($orphans === null)
            <div class="mt-4">
                <p class="text-sm text-ink-3">
                    Answering this means checking every connected location on the
                    platform, one at a time, so it runs only when you ask for it.
                </p>

                <div class="mt-3">
                    <x-ui.button
                        size="default"
                        variant="secondary"
                        type="button"
                        wire:click="findOrphans"
                        wire:loading.attr="disabled"
                        wire:target="findOrphans"
                    >
                        Check for orphaned bindings
                    </x-ui.button>
                </div>
            </div>
        @elseif ($orphans === [])
            <x-ui.empty-state class="mt-4" icon="✓">
                None found.
            </x-ui.empty-state>
        @else
            <ul class="mt-4 space-y-1 text-base text-ink-2">
                @foreach ($orphans as $businessId)
                    <li wire:key="orphan-{{ $businessId }}">Business #{{ $businessId }}</li>
                @endforeach
            </ul>

            <p class="mt-3 text-sm text-ink-3">
                Checked when you pressed the button — press it again after acting on
                any of these.
            </p>
        @endif
    </section>

    {{-- ──────────────  Billed at Zernio, unused here (6779(d))  ────────────── --}}
    {{--
        A DIFFERENT QUESTION FROM THE TWO SECTIONS ABOVE. Those are about
        businesses that no longer exist. This is about accounts we are paying
        for right now — an owner who granted access at Zernio and never came
        back leaves a live grant, a line on the invoice, and a pending row
        here that nothing revisits. ZernioSpend counts BINDINGS, so our own
        ledger cannot see it: 3297's "a money path with no counter does not
        look uncapped, it looks free".

        NO ACTION, AND NO ACCOUNT IDS. 4884's discipline on both counts. A
        business number where we recorded one, a profile reference where we
        did not, and never the value that decides whose listing a call
        reaches.
    --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">Connected at Zernio, unused here</h2>

        <p class="mt-1 text-base text-ink-2">
            Zernio charges for every account connected to it, every day, whether
            or not anything uses it. This compares what they say is connected
            with what this system is actually reading reviews for. The usual
            cause is an owner who granted access to Google and never came back
            to finish — their listing is connected, and nothing here knows.
        </p>

        @if ($reconciliation === null)
            <div class="mt-4">
                <p class="text-sm text-ink-3">
                    Asking Zernio costs nothing and takes a moment. Nothing is
                    changed, connected or disconnected by checking.
                </p>

                <div class="mt-3">
                    <x-ui.button
                        size="default"
                        variant="secondary"
                        type="button"
                        wire:click="reconcile"
                        wire:loading.attr="disabled"
                        wire:target="reconcile"
                    >
                        Check what Zernio is billing for
                    </x-ui.button>
                </div>
            </div>
        @else
            <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-ink-3">Connected at Zernio</dt>
                    <dd class="font-display text-2xl font-semibold text-ink">{{ $reconciliation['billed'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-3">In use here</dt>
                    <dd class="font-display text-2xl font-semibold text-ink">{{ $reconciliation['bound'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-3">Costing us, unused</dt>
                    <dd class="font-display text-2xl font-semibold text-ink">
                        ${{ number_format($reconciliation['orphanCents'] / 100, 2) }}<span class="text-base font-normal text-ink-2">/month</span>
                    </dd>
                </div>
            </dl>

            @if ($reconciliation['orphans'] === [])
                <x-ui.empty-state class="mt-4" icon="✓">
                    Everything Zernio has connected is in use here.
                </x-ui.empty-state>
            @else
                {{--
                    NEVER ACTED ON FROM HERE. An account connected straight from
                    Zernio's own console, and a redirect that simply has not
                    landed yet, arrive on this list looking exactly like an
                    abandoned flow — decision 6766.
                --}}
                <x-ui.attention-card class="mt-4" state="attention" heading="Confirm each one before disconnecting anything at Zernio">
                    A connection that completed moments ago looks exactly like one
                    that was abandoned. Disconnecting the wrong account takes a
                    paying customer's Google Business Profile offline, and they
                    cannot get it back without granting access again.
                </x-ui.attention-card>

                <ul class="mt-4 space-y-2 text-base text-ink-2">
                    @foreach ($reconciliation['orphans'] as $index => $orphan)
                        <li wire:key="unused-{{ $index }}">
                            @if ($orphan['business'] !== null)
                                Business #{{ $orphan['business'] }} started this connection
                            @else
                                Not attributable to a business here
                            @endif

                            @if ($orphan['profile'])
                                — Zernio profile <code class="font-mono text-sm">{{ $orphan['profile'] }}</code>
                            @endif

                            @if ($orphan['platform'] && $orphan['platform'] !== 'googlebusiness')
                                <span class="text-sm text-ink-3">({{ $orphan['platform'] }}, not Google)</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($reconciliation['unreadable'] > 0)
                {{--
                    Counted in the total above and absent from the list, so the
                    two visibly fail to add up rather than silently doing so.
                --}}
                <p class="mt-3 text-sm text-ink-3">
                    {{ $reconciliation['unreadable'] }} of the accounts Zernio listed could not be
                    read well enough to say anything about. They are counted in the
                    total and are not in the list.
                </p>
            @endif

            @if ($reconciliation['boundNotConnected'] !== [])
                {{--
                    THE OTHER DIRECTION, AND IT IS NOT HARMLESS. zernio:meter
                    writes an account-day per binding, so a binding Zernio no
                    longer lists makes our accrual overstate the bill and makes
                    the ceiling refuse connections the platform could afford.
                --}}
                <div class="mt-5 border-t border-rule pt-4">
                    <h3 class="text-base font-semibold text-ink">In use here, not connected at Zernio</h3>
                    <p class="mt-1 text-sm text-ink-2">
                        We are counting these towards the monthly ceiling and Zernio is
                        not charging for them, so new connections may be turned away
                        for spend that is not happening.
                    </p>
                    <ul class="mt-2 space-y-1 text-base text-ink-2">
                        @foreach ($reconciliation['boundNotConnected'] as $businessId)
                            <li wire:key="stale-binding-{{ $businessId }}">Business #{{ $businessId }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="mt-4 text-sm text-ink-3">
                A count taken just now. Zernio's invoice adds up connected days
                across the whole month, so an account connected and disconnected
                earlier this month is on the bill and on neither side of this.
            </p>
        @endif
    </section>
</div>
