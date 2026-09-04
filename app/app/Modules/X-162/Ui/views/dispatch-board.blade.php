<x-surface.sample-state module="the kanban and predictability board" screen="dispatch_board" />
<div>
    <div class="dispatch-board-view p-4">
        <h3 class="text-lg font-bold">Field Dispatch Board</h3>
        @if($assignments->isEmpty())
            <p class="text-gray-500">No active dispatch assignments.</p>
        @else
            <ul>
                @foreach($assignments as $a)
                    <li>#{{ $a->id }}: Job #{{ $a->job_id }} -> Tech #{{ $a->tech_id }} [{{ $a->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
