<div>
    <div class="impersonation-log-view p-4">
        @if($success)
            <div class="bg-surface text-ink border rounded p-2 mb-4">{{ $success }}</div>
        @endif
        @if($error)
            <div class="bg-surface text-ink border rounded p-2 mb-4">{{ $error }}</div>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h2 class="text-lg font-bold">Start Impersonation</h2>
            <form wire:submit="startImpersonation" class="flex flex-col gap-2">
                <input type="number" wire:model="agencyId" class="border rounded p-2 text-ink bg-surface" placeholder="Agency ID">
                <input type="number" wire:model="userId" class="border rounded p-2 text-ink bg-surface" placeholder="User ID">
                <input type="number" wire:model="targetClientBusinessId" class="border rounded p-2 text-ink bg-surface" placeholder="Target Client Business ID">
                <input type="text" wire:model="reason" class="border rounded p-2 text-ink bg-surface" placeholder="Reason">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Start Impersonation</button>
            </form>
        </div>

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
