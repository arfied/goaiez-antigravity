<div>
    <div class="dr-dashboard-view p-4">
        <h2 class="text-lg font-bold text-ink">Restore tests</h2>
        @if($tests->isEmpty())
            <p class="text-ink-2">No restore tests executed.</p>
        @else
            <ul>
                @foreach($tests as $t)
                    <li>#{{ $t->id }}: Backup {{ $t->backup_id }} [{{ $t->status }}] (Rows: {{ $t->restored_row_count }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
