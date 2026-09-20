<div>
    <div class="impersonation-log-view p-4">
        <h3 class="text-lg font-bold">Impersonation Audit Trail</h3>
        @if($logs->isEmpty())
            <p class="text-ink-2">No impersonation records found.</p>
        @else
            <ul>
                @foreach($logs as $log)
                    <li>{{ $log->reason }} ({{ $log->started_at->toIso8601String() }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
