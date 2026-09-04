<x-surface.sample-state module="dynamic number insertion" screen="attribution_row" />
<div>
    <div class="attribution-row-view p-4">
        <h3 class="text-lg font-bold">Call Attribution Feed</h3>
        @if($tokens->isEmpty())
            <p class="text-gray-500">No active DNI tokens allocated.</p>
        @else
            <ul>
                @foreach($tokens as $t)
                    <li>#{{ $t->id }}: {{ $t->allocated_number }} -> {{ $t->campaign_source }} [{{ $t->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
