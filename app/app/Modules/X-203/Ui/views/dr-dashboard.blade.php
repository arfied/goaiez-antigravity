<div>
    <div class="dr-dashboard-view p-4">
        <h2 class="text-lg font-bold text-ink">Restore tests</h2>
        
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h3 class="font-semibold text-ink">Record Test</h3>
            @if($success)
                <div class="text-ink-2 bg-paper p-2 border rounded">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-ink-2 bg-paper p-2 border rounded">{{ $error }}</div>
            @endif
            <form wire:submit="recordTest" class="flex flex-col gap-2">
                <input type="text" wire:model="backupId" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Backup ID">
                <input type="text" wire:model="expectedChecksum" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Expected Checksum">
                <input type="text" wire:model="actualChecksum" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Actual Checksum">
                <input type="number" wire:model="expectedRowCount" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Expected Row Count">
                <input type="number" wire:model="restoredRowCount" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Restored Row Count">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

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
