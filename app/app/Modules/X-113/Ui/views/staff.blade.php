<div>
    <div class="staff-view p-4">
        <h2 class="text-lg font-bold text-ink">Staff</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h3 class="text-md font-bold text-ink">Invite crew member</h3>
            @if($success)
                <div class="text-sm bg-paper p-2 text-ink">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-sm text-ink-2 bg-paper p-2 border">{{ $error }}</div>
            @endif
            <form wire:submit="invite" class="flex flex-col gap-2">
                <input type="text" wire:model="name" placeholder="Name" class="border rounded p-2 text-ink flex-1 bg-surface">
                <input type="email" wire:model="email" placeholder="Email" class="border rounded p-2 text-ink flex-1 bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        @if($staff->isEmpty())
            <x-ui.empty-state heading="Nobody on the crew yet.">The people who work with you appear here, with the role each one holds.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($staff as $s)
                    <li class="py-2" wire:key="staff-{{ $s->id }}">
                        <span class="font-semibold">{{ $s->name }}</span>
                        <span class="text-sm text-ink-2">{{ $s->email }}</span>
                        <span class="text-sm text-ink-2">{{ $s->is_active ? 'Active' : 'Deactivated' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
