<div>
    <h1 class="text-2xl font-bold mb-4">Members</h1>

    @if($memberships->isEmpty())
        <x-ui.empty-state heading="No members">
            No members yet. Start one from a plan.
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:header>
                <tr>
                    <th class="px-4 py-2 text-left">Person ID</th>
                    <th class="px-4 py-2 text-left">Plan Name</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Renews At</th>
                    <th class="px-4 py-2 text-left">Reminder Sent</th>
                    <th class="px-4 py-2 text-left">Sample</th>
                    <th class="px-4 py-2 text-left">Actions</th>
                </tr>
            </x-slot:header>
            <x-slot:body>
                @foreach($memberships as $membership)
                    <tr>
                        <td class="border px-4 py-2">{{ $membership->person_id }}</td>
                        <td class="border px-4 py-2">{{ \App\Modules\X165\Models\MembershipPlan::find($membership->plan_id)?->name }}</td>
                        <td class="border px-4 py-2">
                            <x-ui.status-pill :state="$membership->status === 'active' ? 'ok' : 'attention'" :label="$membership->status" />
                        </td>
                        <td class="border px-4 py-2">{{ $membership->renews_at?->format('Y-m-d') }}</td>
                        <td class="border px-4 py-2">{{ $membership->renewal_reminder_sent_at?->format('Y-m-d') ?? 'No' }}</td>
                        <td class="border px-4 py-2">
                            @if($membership->is_sample)
                                <x-ui.status-pill state="attention" label="Sample" />
                            @endif
                        </td>
                        <td class="border px-4 py-2">
                            <button wire:click="renew({{ $membership->id }})" class="bg-blue-600 text-white px-2 py-1 rounded text-sm mb-1 w-full">Renew</button>
                            <button wire:click="remind({{ $membership->id }})" class="bg-yellow-600 text-white px-2 py-1 rounded text-sm w-full">Send reminder</button>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        </x-ui.table>
    @endif
</div>
