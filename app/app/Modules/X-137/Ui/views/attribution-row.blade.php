<div>
    <div class="attribution-row-view p-4">
        <h2 class="text-lg font-bold text-ink">Call attribution</h2>
        @if($tokens->isEmpty())
            <p class="text-ink-2">No active DNI tokens allocated.</p>
        @else
            <ul>
                @foreach($tokens as $t)
                    <li>#{{ $t->id }}: {{ $t->allocated_number }} -> {{ $t->campaign_source }} [{{ $t->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
