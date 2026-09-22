<div>
    <div class="ladder-state-view p-4">
        <h2 class="text-lg font-bold text-ink">Autopilot ladder</h2>
        @if($ladders->isEmpty())
            <x-ui.empty-state heading="Nothing has earned autonomy yet.">Each action the assistant performs climbs its own ladder as it gets things right.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($ladders as $l)
                    <li class="py-2" wire:key="ladder-{{ $l->id }}">
                        <span class="font-semibold">{{ $l->action_name }}</span>
                        <span class="text-sm text-ink-2 tabular-nums">level {{ $l->current_level }}</span>
                        <span class="text-sm text-ink-2 tabular-nums">{{ $l->success_count }} successes</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            @if($success)
                <div class="text-ink font-bold">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-ink-3 font-bold">{{ $error }}</div>
            @endif
            <form wire:submit="approveAction" class="flex flex-col gap-2">
                <input type="text" wire:model="actionName" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Action name">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Approve</button>
            </form>
        </div>
    </div>
</div>
