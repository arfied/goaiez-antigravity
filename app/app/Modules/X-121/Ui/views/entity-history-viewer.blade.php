<div>
    <div class="entity-history-container p-4">
        <h2 class="text-lg font-bold text-ink">Change history</h2>
        @if($history->isEmpty())
            <p class="text-ink-2">No history records yet.</p>
        @else
            <ul class="divide-y divide-rule">
                @foreach($history as $item)
                    <li class="py-2">
                        <span class="font-mono text-sm">v{{ $item->version }}</span>
                        <span class="ml-2 text-sm text-ink">{{ $item->entity_type }} #{{ $item->entity_id }}</span>
                        <span class="text-xs text-ink-2">{{ $item->created_at }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
