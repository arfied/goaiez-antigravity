<div>
    <div class="marketplace-view p-4">
        <h3 class="text-lg font-bold">Extension & Module Marketplace</h3>

        @if($success)
            <div class="bg-surface p-4 border rounded mb-4 text-ink">{{ $success }}</div>
        @endif
        @if($error)
            <div class="bg-surface p-4 border rounded mb-4 text-ink">{{ $error }}</div>
        @endif

        <form wire:submit="publish" class="flex flex-col gap-4 bg-surface p-4 border rounded mb-6">
            <input type="text" wire:model="itemName" class="border rounded p-2 text-ink bg-surface" placeholder="Item Name">
            <input type="text" wire:model="itemSlug" class="border rounded p-2 text-ink bg-surface" placeholder="Item Slug">
            <input type="text" wire:model="version" class="border rounded p-2 text-ink bg-surface" placeholder="Version">
            <textarea wire:model="summary" class="border rounded p-2 text-ink bg-surface" placeholder="Summary"></textarea>
            <button type="submit" class="bg-surface text-ink border rounded p-2">Publish Item</button>
        </form>

        <div class="flex flex-col gap-2">
            @forelse($items as $item)
                <div class="bg-surface p-4 border rounded text-ink">
                    <div class="font-bold">{{ $item->item_name }} ({{ $item->item_slug }})</div>
                    <div>Version: {{ $item->version }}</div>
                </div>
            @empty
                <div class="bg-surface p-4 border rounded text-ink">
                    No items have been published to the marketplace yet.
                </div>
            @endforelse
        </div>
    </div>
</div>
