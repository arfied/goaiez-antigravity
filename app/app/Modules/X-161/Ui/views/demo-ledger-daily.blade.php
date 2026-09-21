<div>
    <div class="demo-ledger-daily-view p-4">
        <h3 class="text-lg font-bold">Interactive Demo Mock Ledger Entries</h3>

        @if ($error)
            <div class="bg-surface text-ink border rounded p-4 mb-4">
                {{ $error }}
            </div>
        @endif

        @if ($success)
            <div class="bg-surface text-ink border rounded p-4 mb-4">
                {{ $success }}
            </div>
        @endif

        <form wire:submit="provisionDemo" class="mb-6 flex flex-col gap-4 bg-surface p-4 border rounded mt-4">
            <div>
                <label class="text-ink-2">Prospect Domain</label>
                <input type="text" wire:model="prospectDomain" class="border rounded p-2 text-ink w-full bg-surface">
            </div>
            <button type="submit" class="bg-surface text-ink border rounded p-2 w-48">Provision Demo</button>
        </form>

        <form wire:submit="sendTestMessage" class="mb-6 flex flex-col gap-4 bg-surface p-4 border rounded mt-4">
            <div>
                <label class="text-ink-2">Demo Tenant</label>
                <select wire:model="demoTenantId" class="border rounded p-2 text-ink w-full bg-surface">
                    <option value="0">Select a tenant...</option>
                    @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->demo_slug }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-ink-2">Message</label>
                <input type="text" wire:model="message" class="border rounded p-2 text-ink w-full bg-surface">
            </div>
            <button type="submit" class="bg-surface text-ink border rounded p-2 w-48">Send Test Message</button>
        </form>

        <form wire:submit="resetDemo" class="mb-6 flex flex-col gap-4 bg-surface p-4 border rounded mt-4">
            <div>
                <label class="text-ink-2">Demo Tenant to Reset</label>
                <select wire:model="resetTenantId" class="border rounded p-2 text-ink w-full bg-surface">
                    <option value="0">Select a tenant...</option>
                    @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->demo_slug }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="confirmReset" id="confirmReset" class="border rounded bg-surface">
                <label for="confirmReset" class="text-ink-2">Confirm reset</label>
            </div>
            <button type="submit" class="bg-surface text-ink border rounded p-2 w-48">Reset Debits</button>
        </form>

        @if ($entries->isEmpty())
            <x-ui.empty-state heading="No mock ledger entries">
                No mock demo ledger entries have been created yet.
            </x-ui.empty-state>
        @else
            <div class="flex flex-col gap-2">
                @foreach ($entries as $entry)
                    <div class="bg-surface text-ink border rounded p-4 flex flex-col">
                        <span><span class="text-ink-2">Type:</span> {{ $entry->entry_type }}</span>
                        <span><span class="text-ink-2">Amount (Cents):</span> {{ $entry->amount_cents }}</span>
                        <span><span class="text-ink-2">Description:</span> {{ $entry->description }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
