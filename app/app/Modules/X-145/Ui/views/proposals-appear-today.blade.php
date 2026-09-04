<div>
    <x-surface.sample-state module="candidate set = the action registry, filtered by the gate" screen="proposals_appear_today" />
    <div class="proposals-view p-4">
        <h3 class="text-lg font-bold">Action Proposals Today</h3>
        @if($proposals->isEmpty())
            <p class="text-gray-500">No active proposals today.</p>
        @else
            <ul>
                @foreach($proposals as $p)
                    <li>#{{ $p->id }}: [{{ $p->proposed_action }}] {{ $p->explanation }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
