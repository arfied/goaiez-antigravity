<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="mb-8 flex justify-between items-center">
            <h2 class="text-xl font-semibold leading-6 text-ink">Credits</h2>
            <x-ui.button size="default" wire:click="topup" wire:loading.attr="disabled" wire:target="topup">Top up</x-ui.button>
        </div>

        <div wire:loading>
            <x-ui.skeleton label="Reading the credits…" lines="3" />
        </div>

        @if($error)
            <x-ui.error-panel heading="That didn't go through">{{ $error }}</x-ui.error-panel>
        @endif

                <x-ui.toast kind="success" :message="$success" />

        @if($dunning && $dunning->status !== 'active')
            <div class="mb-8 p-4 rounded-[--radius-card] bg-surface border border-attention">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-ink">
                        Your subscription payment is overdue.
                    </p>
                    <x-ui.button size="sm" variant="primary" href="/account/billing/payment-methods">Update Payment Method</x-ui.button>
                </div>
            </div>
        @endif

        <div wire:loading.remove class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-8">
            <div class="bg-card overflow-hidden shadow rounded-[--radius-card] border border-rule">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-ink-2 truncate">Top-up ledger balance</dt>
                    <dd class="mt-1 text-3xl font-semibold text-ink tabular-nums">
                        {{ number_format($aiBalance / 10000, 4) }}
                    </dd>
                </div>
            </div>
            
            @forelse($meters as $type => $meter)
                <div class="bg-card overflow-hidden shadow rounded-[--radius-card] border border-rule">
                    <div class="px-4 py-5 sm:p-6">
                        <dt class="text-sm font-medium text-ink-2 truncate">{{ $meterLabels[$type] ?? $type }}</dt>
                        <dd class="mt-1 text-2xl font-semibold text-ink tabular-nums">
                            {{ number_format($meter->units_used) }}
                        </dd>
                        <dd class="text-xs text-ink-2 mt-2">
                            @if($type === 'sms_segments' || $type === 'sms')
                                SMS stops sending when the ledger is empty.
                            @elseif($type === 'voice_minutes' || $type === 'voice')
                                Calls go to voicemail when the ledger is empty.
                            @elseif($type === 'ai_seconds' || $type === 'ai')
                                The AI stops answering when the ledger is empty.
                            @elseif($type === 'email')
                                Emails stop sending when the ledger is empty.
                            @else
                                Service stops when the ledger is empty.
                            @endif
                        </dd>
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2">
                    <x-ui.empty-state heading="No usage on this list yet.">
                        This list reads its own usage meters, and nothing in this checkout writes to them yet. Calls and messages are recorded elsewhere in this app, and this list does not read that record.
                    </x-ui.empty-state>
                </div>
            @endforelse
        </div>

        <div wire:loading.remove class="mt-8 flow-root">
            <h3 class="text-lg font-semibold leading-6 text-ink mb-4">Ledger</h3>
            @if($entries->isEmpty())
                <x-ui.empty-state heading="No ledger entries yet." action="Top up" target="topup">A top-up from this screen writes a row here. Usage charges and plan credits are recorded on a separate credit ledger elsewhere in this app, and this list does not read it.</x-ui.empty-state>
            @else
                <div class="overflow-hidden shadow ring-1 ring-rule sm:rounded-lg">
                    <table class="min-w-full divide-y divide-rule">
                        <thead class="bg-surface">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-ink sm:pl-6">Type</th>
                                <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-ink">Amount</th>
                                <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-ink">Balance After</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rule bg-card">
                            @foreach($entries as $entry)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-ink sm:pl-6">
                                        <x-ui.status-pill :state="$ledgerEntryPillStates[$entry->entry_type] ?? 'unknown'" :label="$entry->entry_type" />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2 text-right tabular-nums">
                                        {{ number_format($entry->amount_hundredths_cents / 10000, 4) }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2 text-right tabular-nums">
                                        {{ number_format($entry->balance_after_hundredths_cents / 10000, 4) }}
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <x-ui.button size="default" variant="quiet" wire:click="explain({{ $entry->id }})" wire:loading.attr="disabled" wire:target="explain({{ $entry->id }})">Explain</x-ui.button>
                                    </td>
                                </tr>
                                @if($explainedEntryId === $entry->id && $explanation)
                                    <tr class="bg-surface">
                                        <td colspan="4" class="px-6 py-4">
                                            <p class="text-sm text-ink font-medium mb-1">Explanation</p>
                                            <p class="text-sm text-ink-2">{{ $explanation['description'] }}</p>
                                            <p class="text-xs text-ink-2 mt-2">Ref: {{ $explanation['reference_id'] ?? 'none' }}</p>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
