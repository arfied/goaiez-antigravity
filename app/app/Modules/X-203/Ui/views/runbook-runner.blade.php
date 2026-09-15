<div>
    <div class="runbook-runner-view p-4">
        <h2 class="text-lg font-bold text-ink">Runbooks</h2>
        @if($runbooks->isEmpty())
            <p class="text-ink-2">No runbooks defined.</p>
        @else
            <ul>
                @foreach($runbooks as $r)
                    <li>{{ $r->title }} — on {{ $r->trigger_event }} ({{ count($r->steps ?? []) }} steps)</li>
                @endforeach
            </ul>
        @endif
        <ul>
            @foreach($runs as $run)
                <li>{{ $run->started_at }} — runbook #{{ $run->runbook_id }} [{{ $run->status }}]</li>
            @endforeach
        </ul>
    </div>
</div>
