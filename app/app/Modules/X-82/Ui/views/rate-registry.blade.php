<div>
    <h2 class="text-2xl font-bold mb-4 text-ink">Your rates</h2>

    <div class="bg-paper rounded-xl border border-rule p-6 mb-8">
        <h3 class="text-lg font-medium text-ink mb-4">Set a rate</h3>
        <form wire:submit="setRate" class="flex gap-4">
            <input type="text" wire:model="newRateCode" placeholder="Rate Code (e.g. PLATINUM)" class="border p-2 rounded">
            <input type="number" step="0.01" wire:model="newAmountDollars" placeholder="Amount" class="border p-2 rounded">
            <x-ui.submit target="setRate" busy="Setting...">Set rate</x-ui.submit>
        </form>
    </div>

    @if($rates->isEmpty())
        <x-ui.empty-state heading="No rates">
            No rates in the registry. Seed the two packages.
        </x-ui.empty-state>
    @else
        <div class="bg-paper rounded-xl border border-rule overflow-hidden mb-8">
            <table class="w-full text-left text-sm text-ink">
                <thead class="bg-surface border-b border-rule">
                    <tr>
                        <th class="px-6 py-4 font-medium">Code</th>
                        <th class="px-6 py-4 font-medium">Amount</th>
                        <th class="px-6 py-4 font-medium">Currency</th>
                        <th class="px-6 py-4 font-medium">Version</th>
                        <th class="px-6 py-4 font-medium">Status</th>
                        <th class="px-6 py-4 font-medium">Sample</th>
                        <th class="px-6 py-4 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-rule">
                    @foreach($rates as $rate)
                        <tr>
                            <td class="px-6 py-4 font-medium">{{ $rate->rate_code }}</td>
                            <td class="px-6 py-4">{{ $rate->formatted_amount }}</td>
                            <td class="px-6 py-4">{{ $rate->currency }}</td>
                            <td class="px-6 py-4">{{ $rate->current_version }}</td>
                            <td class="px-6 py-4">
                                <x-ui.status-pill :state="$rate->is_active ? 'ok' : 'alert'" :label="$rate->is_active ? 'Active' : 'Inactive'" />
                            </td>
                            <td class="px-6 py-4">
                                @if($rate->is_sample)
                                    <x-ui.status-pill state="attention" label="Sample" />
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <form wire:submit="setInlineRate({{ $rate->id }})" class="flex flex-col gap-1">
                                    <div class="flex gap-2 items-center">
                                        <input type="number" step="0.01" wire:model="amountInput.{{ $rate->id }}" placeholder="Amount" class="border p-1 w-24">
                                        <x-ui.submit target="setInlineRate({{ $rate->id }})" busy="Setting...">Set rate</x-ui.submit>
                                    </div>
                                    @error('amountInput.'.$rate->id) <span class="text-red-500 text-sm block">{{ $message }}</span> @enderror
                                </form>
                            </td>
                        </tr>
                        @if($rate->versions && $rate->versions->isNotEmpty())
                            <tr class="bg-surface/50">
                                <td colspan="7" class="px-6 py-3">
                                    <div class="text-xs text-ink-2 space-y-1">
                                        @foreach($rate->versions as $version)
                                            <div>Version {{ $version->version_number }} &middot; {{ $version->formatted_amount }} &middot; {{ $version->effective_from }}</div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
