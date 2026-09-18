<div>
    <div class="staff-view p-4">
        <h2 class="text-lg font-bold text-ink">Staff</h2>
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
