<div>
    <div class="manifest-queue-view p-4">
        <h3 class="text-lg font-bold">Manifest Review Queue</h3>
        <div class="flex flex-col gap-2 mt-4">
            @forelse($pending as $item)
                <div class="bg-surface p-4 border rounded text-ink">
                    <div class="font-bold">{{ $item->item_name }} ({{ $item->item_slug }})</div>
                    <div>Version: {{ $item->version }}</div>
                    @if(!$item->is_verified)
                        <div class="mt-2 text-ink">Status: Unverified</div>
                    @endif
                </div>
            @empty
                <div class="bg-surface p-4 border rounded text-ink">
                    No items are pending review in the queue.
                </div>
            @endforelse
        </div>
    </div>
</div>
