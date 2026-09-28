<div>
    <div class="runbook-runner-view p-4">
        <h2 class="text-lg font-bold text-ink">Runbooks</h2>
        @if($runbooks->isEmpty())
            <x-ui.empty-state heading="No runbooks">Defining or running a runbook is not built here yet; this list fills only once a runbook exists.</x-ui.empty-state>
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
