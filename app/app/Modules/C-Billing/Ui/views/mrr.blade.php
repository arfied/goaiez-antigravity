<div>
    <x-surface.sample-state module="C-Billing" screen="mrr" />
    <h1>MRR</h1>

    <x-ui.attention-card state="attention" heading="One account at a time">
        MRR across every account is an operator roll-up. Every table it sums is behind row-level security keyed to this account and there is no operator read path yet — this waits on Track 1 (OWNER ACTION 15). What follows is the row this account contributes.
    </x-ui.attention-card>

    @if($error)
        <x-ui.error-panel heading="We couldn't read the ledger">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    @if($success)
        <p>{{ $success }}</p>
    @endif

    <div wire:loading>
        <x-ui.skeleton label="Adding it up…" />
    </div>

    @if($sub === null)
        <x-ui.empty-state heading="No subscription on this account yet.">Signing up creates it; there is nothing to bill until then.</x-ui.empty-state>
    @else
        <h2>Recurring</h2>
        <div class="border rounded p-4 shadow bg-white">
            <div class="flex flex-wrap justify-between items-center gap-2">
                <div>
                    <span class="font-semibold">{{ $sub->plan?->value ?? 'no plan' }}</span>
                    <span class="text-sm text-ink-2 ml-2">{{ $sub->term?->value ?? 'no term' }}</span>
                    @if($sub->current_period_end)
                        <span class="text-sm text-ink-2 ml-2">renews {{ $sub->current_period_end->toDateString() }}</span>
                    @endif
                </div>
                <x-ui.status-pill :state="$sub->status?->value === 'active' ? 'ok' : 'attention'" :label="$sub->status?->value ?? 'no status'" />
            </div>
            @if($monthly['cents'] === null)
                <x-ui.attention-card state="attention" heading="No agreed price on this row">
                    This account predates the agreed-price columns (3443); the registry figure is not quoted in its place. It waits on the price being written to the row.
                </x-ui.attention-card>
            @else
                <p class="mt-2 tabular-nums"><span class="font-semibold">{{ number_format($monthly['cents'] / 100, 2) }} a month</span> {{ $monthly['currency'] }}@if($monthly['yearly']) — billed yearly, shown as a twelfth @endif</p>
                @if($sub->additional_locations)
                    <p class="text-sm text-ink-2">includes {{ $sub->additional_locations }} extra location(s) at {{ number_format($sub->additional_location_cents / 100, 2) }}</p>
                @endif
            @endif
            <div class="mt-3">
                <x-ui.button size="default" wire:click="topup" wire:loading.attr="disabled" wire:target="topup">Add 50.00 credit</x-ui.button>
            </div>
        </div>

        <h2>Meters</h2>
        @if($meters->isEmpty())
            <x-ui.empty-state heading="Nothing metered yet.">Nothing in this checkout writes a usage meter, so this list fills once the telephony, SMS and agent modules meter what they use.</x-ui.empty-state>
        @else
            <ul class="space-y-2">
                @foreach($meters as $meter)
                    <li class="flex items-center justify-between p-2 border rounded tabular-nums">
                        <span>{{ $meterLabels[$meter->meter_type] ?? $meter->meter_type }}</span>
                        <span>{{ number_format($meter->units_used) }} units</span>
                        <span>{{ number_format($meter->cost_hundredths_cents / 10000, 4) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <h2>This month's ledger</h2>
        @if($entries->isEmpty())
            <x-ui.empty-state heading="No ledger entries this month.">A top-up from the credits screen writes a row here. Nothing in this checkout raises a debit or a grant, so usage charges and plan credits appear once they are built.</x-ui.empty-state>
        @else
            <ul class="space-y-2">
                @foreach($entries as $entry)
                    <li class="p-2 border rounded">
                        <div class="flex flex-wrap items-center justify-between gap-2 tabular-nums">
                            <span>{{ $entry->entry_type }}</span>
                            <span>{{ $entry->description }}</span>
                            <span>{{ number_format($entry->amount_hundredths_cents / 10000, 4) }}</span>
                            <span class="text-sm text-ink-2">after {{ number_format($entry->balance_after_hundredths_cents / 10000, 4) }}</span>
                            <x-ui.button size="default" variant="secondary" wire:click="explain({{ $entry->id }})" wire:loading.attr="disabled" wire:target="explain({{ $entry->id }})">Explain</x-ui.button>
                        </div>
                        @if($explainedEntryId === $entry->id && $explanation)
                            <p class="mt-2 text-sm">{{ $explanation['description'] ?? '' }} <span class="text-ink-2">ref {{ $explanation['reference_id'] ?? 'none' }}</span></p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
