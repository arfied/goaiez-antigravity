<div>
    <h1 class="text-2xl font-bold mb-4">Membership Plans</h1>

    <div class="mb-8 p-4 bg-white shadow rounded">
        <h2 class="text-xl mb-4 font-semibold">Propose a plan</h2>
        <form wire:submit="proposePlan" class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
            <input type="text" wire:model="newName" placeholder="Plan Name" class="border p-2 rounded">
            <input type="number" step="0.01" wire:model="newPrice" placeholder="Price" class="border p-2 rounded">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded font-medium">Propose a plan</button>
        </form>
    </div>

    @if($plans->isEmpty())
        <x-ui.empty-state heading="No plans">
            No plans yet. Propose one from the jobs you already do.
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:header>
                <tr>
                    <th class="px-4 py-2 text-left">Plan Name</th>
                    <th class="px-4 py-2 text-left">Price</th>
                    <th class="px-4 py-2 text-left">Interval (mos)</th>
                    <th class="px-4 py-2 text-left">Reminder (days)</th>
                    <th class="px-4 py-2 text-left">Members</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Actions</th>
                </tr>
            </x-slot:header>
            <x-slot:body>
                @foreach($plans as $plan)
                    <tr>
                        <td class="border px-4 py-2">{{ $plan->name }}</td>
                        <td class="border px-4 py-2">{{ $plan->formatted_price }}</td>
                        <td class="border px-4 py-2">{{ $plan->billing_interval_months }}</td>
                        <td class="border px-4 py-2">{{ $plan->renewal_reminder_days }}</td>
                        <td class="border px-4 py-2">{{ $plan->member_count }}</td>
                        <td class="border px-4 py-2">
                            @if($plan->is_sample)
                                <x-ui.status-pill state="attention" label="Sample" />
                            @endif
                        </td>
                        <td class="border px-4 py-2">
                            <form wire:submit="startMembership({{ $plan->id }})" class="flex flex-col gap-1">
                                <div class="flex gap-2">
                                    <input type="number" wire:model="personInput.{{ $plan->id }}" placeholder="Person ID" class="border p-1 w-24">
                                    <button type="submit" class="bg-green-600 text-white px-2 py-1 rounded text-sm whitespace-nowrap">Start a membership</button>
                                </div>
                                @error('personInput.'.$plan->id) <span class="text-red-500 text-sm block">{{ $message }}</span> @enderror
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        </x-ui.table>
    @endif
</div>
