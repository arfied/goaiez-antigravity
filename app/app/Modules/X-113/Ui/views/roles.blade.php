<div>
    <div class="roles-view p-4">
        <h2 class="text-lg font-bold text-ink">Roles</h2>
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
