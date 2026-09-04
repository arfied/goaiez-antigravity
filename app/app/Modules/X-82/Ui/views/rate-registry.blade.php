<div>
    <h1 class="text-2xl font-bold mb-4">Rate Registry</h1>

    <div class="mb-8 p-4 bg-white shadow rounded">
        <h2 class="text-xl mb-4 font-semibold">Set a rate</h2>
        <form wire:submit="setRate" class="flex gap-4">
            <input type="text" wire:model="newRateCode" placeholder="Rate Code (e.g. PLATINUM)" class="border p-2 rounded">
            <input type="number" step="0.01" wire:model="newAmountDollars" placeholder="Amount ($)" class="border p-2 rounded">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Set rate</button>
        </form>
    </div>

    @if($rates->isEmpty())
        <x-ui.empty-state heading="No rates">
            No rates defined. Add one above.
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:header>
                <tr>
                    <th class="px-4 py-2 text-left">Code</th>
                    <th class="px-4 py-2 text-left">Amount</th>
                    <th class="px-4 py-2 text-left">Currency</th>
                    <th class="px-4 py-2 text-left">Version</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Sample</th>
                </tr>
            </x-slot:header>
            <x-slot:body>
                @foreach($rates as $rate)
                    <tr>
                        <td class="border px-4 py-2">{{ $rate->rate_code }}</td>
                        <td class="border px-4 py-2">{{ $rate->formatted_amount }}</td>
                        <td class="border px-4 py-2">{{ $rate->currency }}</td>
                        <td class="border px-4 py-2">{{ $rate->current_version }}</td>
                        <td class="border px-4 py-2">
                            <x-ui.status-pill :state="$rate->is_active ? 'ok' : 'alert'" :label="$rate->is_active ? 'Active' : 'Inactive'" />
                        </td>
                        <td class="border px-4 py-2">
                            @if($rate->is_sample)
                                <x-ui.status-pill state="attention" label="Sample" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        </x-ui.table>
    @endif
</div>
