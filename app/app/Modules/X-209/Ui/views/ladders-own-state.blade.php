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
    </div>
</div>
