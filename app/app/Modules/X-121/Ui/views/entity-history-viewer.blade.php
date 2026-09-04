<x-surface.sample-state module="`Business" screen="entity_history_viewer" />
<div>
    <div class="entity-history-container p-4">
        <h3 class="text-lg font-bold">Entity History</h3>
        @if($history->isEmpty())
            <p class="text-gray-500">No history records found for this entity.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($history as $item)
                    <li class="py-2">
                        <span class="font-mono text-sm">v{{ $item->version }}</span>
                        <span class="text-xs text-gray-400">{{ $item->created_at }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
