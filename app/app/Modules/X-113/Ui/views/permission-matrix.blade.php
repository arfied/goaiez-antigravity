<div>
    <div class="perm-matrix-view p-4">
        <h2 class="text-lg font-bold text-ink">RBAC Permission Matrix</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            @if($success)
                <div class="text-green-600 mb-2">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-red-600 mb-2">{{ $error }}</div>
            @endif
            <form wire:submit.prevent="grantPermission" class="flex flex-col gap-2">
                <select wire:model="roleId" class="border rounded p-2 text-ink flex-1 bg-surface">
                    <option value="0">Select Role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <select wire:model="permission" class="border rounded p-2 text-ink flex-1 bg-surface">
                    <option value="">Select Permission</option>
                    @foreach(\App\Modules\X113\Actions\RolePermissionGrantAction::PERMISSIONS as $perm)
                        <option value="{{ $perm }}">{{ $perm }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        @if($roles->isEmpty())
            <x-ui.empty-state heading="Roles are created on the Roles screen.">No roles exist yet in this account.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule mt-4">
                @foreach($roles as $role)
                    <li class="py-2 flex flex-col gap-1">
                        <span class="font-semibold text-ink">{{ $role->name }}</span>
                        @if(isset($grants[$role->id]) && $grants[$role->id]->isNotEmpty())
                            <ul class="list-disc list-inside">
                                @foreach($grants[$role->id] as $grant)
                                    <li class="text-sm text-ink-2">{{ $grant->permission }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-sm text-ink-3">No permissions granted.</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
