<div class="w-full max-w-3xl space-y-8">
<header>
    <h1 class="font-display text-2xl font-semibold text-ink">Data requests</h1>
    <p class="mt-2 text-base text-ink-2">
        GDPR and CCPA asks with due dates. Erasure uses the two-person delete path.
        Consent audits use the stored consent trail. A full account download uses
        the same two-person approval, then builds through the export engine.
    </p>
</header>

@error('queue')
    <p class="text-base text-alert" role="alert">{{ $message }}</p>
@enderror

@if ($mayAct)
    <section class="rounded-[--radius-panel] border border-rule bg-card p-5" aria-label="File a request">
        <h2 class="font-display text-lg font-semibold text-ink">File a request</h2>

        @if ($filing === '')
            <div class="mt-4 flex flex-wrap gap-3">
                <x-ui.button type="button" wire:click="startFiling('erasure')">Erase an account</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="startFiling('hard_offboard')">Close an account</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="startFiling('consent_audit')">Consent audit</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="startFiling('tenant_export')">Record an export ask</x-ui.button>
            </div>
        @else
            <p class="mt-2 text-base text-ink-2">
                @switch($filing)
                    @case('erasure')
                        Right-to-erasure. A second person must approve; then a seven-day cooling window.
                        @break
                    @case('hard_offboard')
                        Close the account with no rights claim. Same two-person path; clock is operational.
                        @break
                    @case('consent_audit')
                        Produce the consent trail for one contact. Done in one step.
                        @break
                    @case('tenant_export')
                        Records the ask. A second person must approve it before the
                        download builds.
                        @break
                @endswitch
            </p>

            <label class="mt-4 flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">Account number</span>
                <input
                    id="support-data-request-business-ref"
                    type="text"
                    wire:model="businessRef"
                    inputmode="numeric"
                    class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                />
            </label>
            @error('businessRef')
                <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
            @enderror

            @if ($filing === 'consent_audit')
                <label class="mt-4 flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Contact number</span>
                    <input
                        type="text"
                        wire:model="customerRef"
                        inputmode="numeric"
                        class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                </label>
                @error('customerRef')
                    <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
                @enderror
            @endif

            <label class="mt-4 flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">Note (optional)</span>
                <textarea
                    wire:model="detail"
                    rows="2"
                    class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                ></textarea>
            </label>

            <div class="mt-4 flex flex-wrap gap-3">
                <x-ui.button type="button" wire:click="file">File</x-ui.button>
                <button
                    type="button"
                    wire:click="cancelFiling"
                    class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                >
                    Cancel
                </button>
            </div>
        @endif
    </section>
@endif

<section aria-label="Open requests">
    <h2 class="font-display text-lg font-semibold text-ink">Open</h2>

    @if ($items->isEmpty())
        {{--
            ⚠️ NO ACTION AND NO REASSURANCE. An empty queue here means no
            erasure or access request is outstanding — a statutory clock
            nobody is behind on — and the honest sentence says only that.
            A button would offer an agent a way to make work that a data
            subject is the only person who can create.
        --}}
        <x-ui.empty-state class="mt-3" icon="◇">
            Nothing waiting. Requests appear here the moment somebody asks for
            their data or asks us to erase it.
        </x-ui.empty-state>
    @else
        <ul class="mt-4 space-y-4">
            @foreach ($items as $item)
                <li class="rounded-[--radius-panel] border border-rule bg-card p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="font-display text-base font-semibold text-ink">
                            {{ $item->kind->label() }}
                        </h3>
                        <p class="font-mono text-sm text-ink-2">
                            Due {{ $item->due_at->toDateString() }}
                            @if ($item->due_at->isPast())
                                <span class="text-alert"> · overdue</span>
                            @endif
                        </p>
                    </div>
                    <p class="mt-1 text-base text-ink-2">
                        Account {{ $item->business_ref }}
                        @if ($item->customer_ref)
                            · contact {{ $item->customer_ref }}
                        @endif
                        · {{ $item->status->label() }}
                    </p>
                    @if ($item->detail)
                        <p class="mt-2 text-base text-ink">{{ $item->detail }}</p>
                    @endif
                    @if ($item->outcome_note)
                        <p class="mt-2 text-sm text-ink-2">{{ $item->outcome_note }}</p>
                    @endif

                    {{--
                        ⚠️ 1999. A built export is not a delivered one, and an
                        agent seeing "Done" on a row nobody has collected is the
                        whole defect. Paired with the status label rather than
                        standing on colour alone — `22`'s rule.
                    --}}
                    @if ($item->status === $builtStatus)
                        <p class="mt-2 text-base text-ink-2">
                            The download is ready and the tenant has not collected it yet.
                            It closes on their first fetch; nobody here can fetch it for them.
                        </p>
                    @endif

                    @if ($mayAct && in_array($item->kind, [\App\Enums\DataRequestKind::Erasure, \App\Enums\DataRequestKind::TenantExport], true))
                        @if ($confirmingApproveId === $item->id)
                            <div class="mt-4 rounded-[--radius-control] border border-rule p-4">
                                <p class="text-base text-ink">
                                    @if ($item->kind === \App\Enums\DataRequestKind::TenantExport)
                                        Approve this export? Building starts now and an email
                                        attempts to send when it is ready.
                                    @else
                                        Approve this erasure? The seven-day cooling window starts now.
                                        Erasure is delete-plus-survivors, not crypto-shred.
                                    @endif
                                </p>
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <x-ui.button id="confirm-approve-request-{{ $item->id }}" type="button" wire:click="approve({{ $item->id }})">
                                        @if ($item->kind === \App\Enums\DataRequestKind::TenantExport)
                                            Yes, build it
                                        @else
                                            Yes, start the window
                                        @endif
                                    </x-ui.button>
                                    <button
                                        type="button"
                                        wire:click="dismissConfirm"
                                        class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                                    >
                                        Not now
                                    </button>
                                </div>
                            </div>
                        @elseif ($confirmingCancelId === $item->id)
                            <div class="mt-4 rounded-[--radius-control] border border-rule p-4">
                                <label class="flex flex-col gap-1">
                                    <span class="text-sm font-medium text-ink-2">Why cancel (optional)</span>
                                    <input
                                        type="text"
                                        wire:model="cancelReason"
                                        class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                                    />
                                </label>
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <x-ui.button type="button" wire:click="cancelRequest({{ $item->id }})">
                                        Cancel the request
                                    </x-ui.button>
                                    <button
                                        type="button"
                                        wire:click="dismissConfirm"
                                        class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                                    >
                                        Keep it
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="mt-4 flex flex-wrap gap-3">
                                @if ($item->status === $openStatus)
                                    <x-ui.button id="approve-request-{{ $item->id }}" type="button" wire:click="confirmApprove({{ $item->id }})">
                                        Approve (second person)
                                    </x-ui.button>
                                @endif
                                <button
                                    type="button"
                                    wire:click="confirmCancel({{ $item->id }})"
                                    class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                                >
                                    Cancel request
                                </button>
                            </div>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

<p class="text-sm text-ink-2">
    What this queue does not do: crypto-shred (no per-identity key), or anything
    from R2 (pixel L0 unbuilt).
</p>
</div>
