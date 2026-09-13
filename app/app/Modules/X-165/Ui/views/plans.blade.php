<div>
    <h2 class="text-2xl font-bold mb-4 text-ink">Membership Plans</h2>

    <div class="bg-paper rounded-xl border border-rule p-6 mb-8">
        <h2 class="text-lg font-medium text-ink mb-4">Propose a plan</h2>
        <form wire:submit="proposePlan" class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
            <input type="text" wire:model="newName" placeholder="Plan Name" class="border p-2 rounded">
            <select wire:model="newItemId" class="border p-2 rounded">
                <option value="">Price from your pricebook</option>
                @foreach($priceOptions as $option)
                    <option value="{{ $option['id'] }}">{{ $option['service_name'] }} (${{ number_format($option['price_cents'] / 100, 2) }})</option>
                @endforeach
            </select>
            <x-ui.submit target="proposePlan" busy="Proposing...">Propose a plan</x-ui.submit>
        </form>
        @error('newItemId') <span class="text-red-500 text-sm block mt-2">{{ $message }}</span> @enderror
    </div>

    @if($plans->isEmpty())
        <x-ui.empty-state heading="No plans">
            No plans yet. Propose one from the jobs you already do.
        </x-ui.empty-state>
    @else
        <div class="bg-paper rounded-xl border border-rule overflow-hidden mb-8">
            <table class="w-full text-left text-sm text-ink">
                <thead class="bg-surface border-b border-rule">
                    <tr>
                        <th class="px-6 py-4 font-medium">Plan Name</th>
                        <th class="px-6 py-4 font-medium">Price</th>
                        <th class="px-6 py-4 font-medium">Interval (mos)</th>
                        <th class="px-6 py-4 font-medium">Reminder (days)</th>
                        <th class="px-6 py-4 font-medium">Members</th>
                        <th class="px-6 py-4 font-medium">Status</th>
                        <th class="px-6 py-4 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-rule">
                    @foreach($plans as $plan)
                        <tr>
                            <td class="px-6 py-4">{{ $plan->name }}</td>
                            <td class="px-6 py-4">{{ $plan->formatted_price }}</td>
                            <td class="px-6 py-4">{{ $plan->billing_interval_months }}</td>
                            <td class="px-6 py-4">{{ $plan->renewal_reminder_days }}</td>
                            <td class="px-6 py-4">{{ $plan->member_count }}</td>
                            <td class="px-6 py-4">
                                @if($plan->is_sample)
                                    <x-ui.status-pill state="attention" label="Sample" />
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <form wire:submit="startMembership({{ $plan->id }})" class="flex flex-col gap-1">
                                    <div class="flex gap-2">
                                        <input type="number" wire:model="personInput.{{ $plan->id }}" placeholder="Person ID" class="border p-1 w-24">
                                        <x-ui.submit target="startMembership({{ $plan->id }})" busy="Starting...">Start a membership</x-ui.submit>
                                    </div>
                                    @error('personInput.'.$plan->id) <span class="text-red-500 text-sm block">{{ $message }}</span> @enderror
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
