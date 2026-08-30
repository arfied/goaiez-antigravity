<div>
    <div class="dr-dashboard-view p-4">
        <h3 class="text-lg font-bold">Disaster Recovery & Restore Verification</h3>
        @if($tests->isEmpty())
            <p class="text-gray-500">No restore tests executed.</p>
        @else
            <ul>
                @foreach($tests as $t)
                    <li>#{{ $t->id }}: Backup {{ $t->backup_id }} [{{ $t->status }}] (Rows: {{ $t->restored_row_count }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
