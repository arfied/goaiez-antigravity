<div>
    <div class="restorationtest-log-view p-4">
        <h2 class="text-lg font-bold text-ink">Restore test log</h2>
        @if($tests->isEmpty())
            <p class="text-ink-2">No restore tests logged.</p>
        @else
            <ul>
                @foreach($tests as $t)
                    <li>{{ $t->tested_at }} — Backup {{ $t->backup_id }} [{{ $t->status }}]{{ $t->failure_reason ? ': '.$t->failure_reason : '' }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
