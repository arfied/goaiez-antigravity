<x-surface.sample-state module="branching" screen="canvas" />
<div>
    <div class="flow-canvas-view p-4">
        <h3 class="text-lg font-bold">Workflow Canvas</h3>
        @if($flows->isEmpty())
            <p class="text-gray-500">No active flows configured.</p>
        @else
            <ul>
                @foreach($flows as $f)
                    <li>#{{ $f->id }}: {{ $f->name }} [{{ $f->status }}] (Trigger: {{ $f->trigger_event }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
