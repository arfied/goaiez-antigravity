<div>
    <div class="routing-rules-view p-4">
        <h2 class="text-lg font-bold mb-4">Lead Routing Rules</h2>
        
        <table class="w-full text-left border-collapse border">
            <thead>
                <tr>
                    <th class="p-2 border">Priority</th>
                    <th class="p-2 border">Rule</th>
                    <th class="p-2 border">Active</th>
                    <th class="p-2 border">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rules as $r)
                    <tr>
                        <td class="p-2 border">{{ $r->priority }}</td>
                        <td class="p-2 border">
                            {{ $r->name }}
                            @if($r->rule_type->value === 'default_staff')
                                <div class="mt-2">
                                    <select wire:change="setDefaultStaff($event.target.value)" class="border p-1">
                                        <option value="">-- Default to Owner --</option>
                                        @foreach($staffUsers as $user)
                                            <option value="{{ $user->id }}" {{ ($r->settings['staff_id'] ?? null) == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </td>
                        <td class="p-2 border">
                            <x-ui.button wire:click="toggle('{{ $r->rule_type->value }}')" size="default" variant="secondary">
                                {{ $r->is_active ? 'Disable' : 'Enable' }}
                            </x-ui.button>
                        </td>
                        <td class="p-2 border">
                            @if(!$loop->first)
                                <x-ui.button wire:click="moveUp('{{ $r->rule_type->value }}')" size="default" variant="secondary">Up</x-ui.button>
                            @endif
                            @if(!$loop->last)
                                <x-ui.button wire:click="moveDown('{{ $r->rule_type->value }}')" size="default" variant="secondary">Down</x-ui.button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
