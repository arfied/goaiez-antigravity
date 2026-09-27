<div>
    <div class="roles-view p-4">
        <h2 class="text-lg font-bold text-ink">Roles</h2>
        
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <x-ui.toast kind="error" :message="$error" />
            <x-ui.toast kind="success" :message="$success" />
            <form wire:submit="createRole" class="flex flex-col gap-2">
                <input type="text" wire:model="name" placeholder="Role Name" class="border rounded p-2 text-ink flex-1 bg-surface">
                <input type="text" wire:model="description" placeholder="Description" class="border rounded p-2 text-ink flex-1 bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Create Role</button>
            </form>
        </div>

        @if($roles->isEmpty())
            <x-ui.empty-state heading="No roles yet.">A role is the set of things somebody on your crew is allowed to do.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($roles as $r)
                    <li class="py-2" wire:key="role-{{ $r->id }}">
                        <span class="font-semibold">{{ $r->name }}</span>
                        <span class="text-sm text-ink-2">{{ $r->description }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
