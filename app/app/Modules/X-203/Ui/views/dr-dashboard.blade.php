<x-surface.sample-state module="**Backups, restores, point-in-time recovery, multi-region, cold-storage hashing and runbook automation — as an OPERATED SURFACE, not a cron nobody reads.** ⭐⭐⭐ **The module's whole premise: A BACKUP IS A HYPOTHESIS UNTIL IT IS RESTORED.** *Restoration runs on a schedule into an isolated environment and is **verified by row counts and a checksum**.* ⛔ **A failed restoration test is a P1, not a warning.**" screen="dr_dashboard" />
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
