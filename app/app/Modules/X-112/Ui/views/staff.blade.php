<div>

    <div class="staff-view p-4">
        @if($success)
            <div class="bg-surface text-ink border rounded p-2 mb-4">{{ $success }}</div>
        @endif
        @if($error)
            <div class="bg-surface text-ink border rounded p-2 mb-4">{{ $error }}</div>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h2 class="text-lg font-bold">Invite Staff</h2>
            <form wire:submit="inviteStaff" class="flex flex-col gap-2">
                <input type="number" wire:model="agencyId" class="border rounded p-2 text-ink bg-surface" placeholder="Agency ID">
                <input type="number" wire:model="userId" class="border rounded p-2 text-ink bg-surface" placeholder="User ID">
                <input type="text" wire:model="role" class="border rounded p-2 text-ink bg-surface" placeholder="Role (e.g. account_manager)">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Invite Staff</button>
            </form>
        </div>

        <h2 class="text-lg font-bold">Agency Staff Management</h2>
        @if($staff->isEmpty())
            <p class="text-ink-2">No agency staff enrolled.</p>
        @else
            <ul>
                @foreach($staff as $s)
                    <li>User #{{ $s->user_id }} - {{ $s->role }} ({{ $s->is_active ? 'Active' : 'Revoked' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
