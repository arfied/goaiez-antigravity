<div>
    <div class="staff-view p-4">
        <h2 class="text-lg font-bold text-ink">Staff</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h3 class="text-md font-bold text-ink">Invite crew member</h3>
            <x-ui.toast kind="success" :message="$success" />
            <x-ui.toast kind="error" :message="$error" />
            <form wire:submit="invite" class="flex flex-col gap-2">
                <input type="text" wire:model="name" placeholder="Name" class="border rounded p-2 text-ink flex-1 bg-surface">
                <input type="email" wire:model="email" placeholder="Email" class="border rounded p-2 text-ink flex-1 bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h3 class="text-md font-bold text-ink">Assign a role</h3>
            @if($assignSuccess) <div class="text-sm bg-paper p-2 text-ink">{{ $assignSuccess }}</div> @endif
            @if($assignError) <div class="text-sm text-ink-2 bg-paper p-2 border">{{ $assignError }}</div> @endif
            <form wire:submit="assignRole" class="flex flex-col gap-2">
                <select wire:model="assignStaffId" class="border rounded p-2 text-ink flex-1 bg-surface">
                    <option value="">Choose a crew member</option>
                    @foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
                <select wire:model="assignRoleId" class="border rounded p-2 text-ink flex-1 bg-surface">
                    <option value="">Choose a role</option>
                    @foreach($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                </select>
                <button type="submit" class="bg-surface text-ink border rounded p-2">Assign</button>
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
                        <span class="text-sm text-ink-2">{{ $roles->firstWhere('id', $s->role_id)?->name ?? 'No role' }}</span>
                        <span class="text-sm text-ink-2">{{ $s->is_active ? 'Active' : 'Deactivated' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
