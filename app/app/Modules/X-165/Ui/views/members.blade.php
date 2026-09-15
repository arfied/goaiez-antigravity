<div>
    <h2 class="text-2xl font-bold mb-4 text-ink">Members</h2>

    @if($memberships->isEmpty())
        <x-ui.empty-state heading="No members">
            No members yet. Start one from a plan.
        </x-ui.empty-state>
    @else
        <div class="bg-paper rounded-xl border border-rule overflow-hidden mb-8">
            <table class="w-full text-left text-sm text-ink">
                <thead class="bg-surface border-b border-rule">
                    <tr>
                        <th class="px-6 py-4 font-medium">Person ID</th>
                        <th class="px-6 py-4 font-medium">Plan Name</th>
                        <th class="px-6 py-4 font-medium">Status</th>
                        <th class="px-6 py-4 font-medium">Renews At</th>
                        <th class="px-6 py-4 font-medium">Reminder Sent</th>
                        <th class="px-6 py-4 font-medium">Sample</th>
                        <th class="px-6 py-4 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-rule">
                    @foreach($memberships as $membership)
                        <tr>
                            <td class="px-6 py-4">{{ $membership->person_id }}</td>
                            <td class="px-6 py-4">{{ $planNames[$membership->plan_id] ?? '' }}</td>
                            <td class="px-6 py-4">
                                <x-ui.status-pill :state="$membership->status === 'active' ? 'ok' : 'attention'" :label="$membership->status" />
                            </td>
                            <td class="px-6 py-4">{{ $membership->renews_at?->format('Y-m-d') }}</td>
                            <td class="px-6 py-4">{{ $membership->renewal_reminder_sent_at?->format('Y-m-d') ?? 'No' }}</td>
                            <td class="px-6 py-4">
                                @if($membership->is_sample)
                                    <x-ui.status-pill state="attention" label="Sample" />
                                @endif
                            </td>
                            <td class="px-6 py-4 flex gap-2">
                                <x-ui.button size="default" wire:click="renew({{ $membership->id }})">Renew</x-ui.button>
                                <x-ui.button size="default" wire:click="remind({{ $membership->id }})">Send reminder</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
