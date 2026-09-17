<div>
    <h2 class="text-lg font-bold text-ink">Collections package</h2>

    <p class="text-base text-ink-2">The bundle is built here. Sending it to an agency is a human action, and it happens only after a resolution attempt is on record.</p>

    @if($error)
        <x-ui.error-panel heading="We couldn't package that">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    @if($success)
        <p>{{ $success }}</p>
    @endif

    @if($packages->isNotEmpty() && $packages->whereNull('transmitted_at')->isNotEmpty())
        <x-ui.attention-card state="attention" heading="Built, not sent">
            No collections agency is connected yet — transmission waits on a collections partner. Every bundle below is kept here until one is named.
        </x-ui.attention-card>
    @endif

    <div wire:loading>
        <x-ui.skeleton label="Building the bundle…" />
    </div>

    @if($packages->isNotEmpty())
        <h3 class="font-semibold text-ink">Packaged</h3>
        <ul class="space-y-2">
            @foreach($packages as $package)
                <li class="border rounded p-4 shadow bg-card">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold">{{ $package->contents['invoice_number'] ?? ('#'.$package->invoice_id) }}</span>
                        <x-ui.status-pill :state="$package->transmitted_at ? 'ok' : 'attention'" :label="$package->transmitted_at ? 'recorded as sent to '.$package->partner.'; nothing was sent from here' : 'waiting on a collections partner'" />
                    </div>
                    <p class="text-sm text-ink-2 mt-2">{{ number_format(($package->contents['balance_cents'] ?? 0) / 100, 2) }} owed · {{ count($package->contents['lines'] ?? []) }} lines · {{ count($package->contents['payments'] ?? []) }} payments · {{ count($package->contents['actions'] ?? []) }} actions · {{ $package->contents['messages_count'] ?? 0 }} messages · packaged {{ $package->created_at->diffForHumans() }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    @if($candidates->isEmpty())
        <x-ui.empty-state heading="Nothing to package.">No overdue invoice is waiting on collections. Nothing in this checkout raises one from a completed job, and a draft is never issued, so no invoice can go overdue yet.</x-ui.empty-state>
    @else
        <h3 class="font-semibold text-ink">Overdue, not yet packaged</h3>
        <ul class="space-y-4">
            @foreach($candidates as $inv)
                <li class="border rounded p-4 shadow bg-card">
                    <div class="flex justify-between items-center mb-2">
                        <div>
                            <span class="font-semibold">{{ $inv->invoice_number }}</span>
                            <span class="text-ink-2 text-sm ml-2">Due {{ $inv->due_date->toDateString() }}</span>
                        </div>
                        <div class="tabular-nums">{{ number_format($inv->balance_cents / 100, 2) }} owed</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <x-ui.status-pill :state="$inv->days_overdue > 60 ? 'alert' : 'attention'" :label="$inv->days_overdue.' days overdue'" />
                        <x-ui.status-pill :state="$inv->attempts > 0 ? 'ok' : 'attention'" :label="$inv->attempts > 0 ? $inv->attempts.' resolution attempts on record' : 'no resolution attempt yet'" />
                    </div>
                    <form wire:submit="package({{ $inv->id }})">
                        <x-ui.submit target="package({{ $inv->id }})" busy="Packaging…">Package for collections</x-ui.submit>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>
