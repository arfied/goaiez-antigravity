<div>
    <div class="routing-rules-view p-4">
        <h2 class="text-lg font-bold">Lead Routing Rules</h2>
        @if($rules->isEmpty())
            <p class="text-ink-2">No routing rules configured.</p>
        @else
            <ul>
                @foreach($rules as $r)
                    <li>#{{ $r->id }}: {{ $r->name }} [{{ $r->rule_type }}] (Priority: {{ $r->priority }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
