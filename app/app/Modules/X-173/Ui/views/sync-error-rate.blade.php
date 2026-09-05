<div>
    <div class="sync-error-rate-view p-4">
        <h3 class="text-lg font-bold">Sync Error & Conflict Rate</h3>
        
        @foreach($runs as $run)
            <div class="mt-4 border p-4">
                <div>Run {{ $run->id }}</div>
                <div>Rate: {{ $rates[$run->id] !== null ? ($rates[$run->id] * 100) . '%' : 'N/A' }}</div>
                <button wire:click="showConflicts({{ $run->id }})" class="bg-blue-500 text-white px-4 py-1">show its conflicts</button>
                
                @if($showingConflictsFor === $run->id)
                    <div class="mt-2 pl-4 border-l">
                        @foreach($conflicts as $conflict)
                            <div>Conflict: {{ $conflict->transaction_ref }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
