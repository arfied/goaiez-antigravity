<div>
    <div class="retrieval-stats-view p-4">
        <h3 class="text-lg font-bold">Retrieval Latency & Empty-Rate Analytics</h3>

        <x-ui.toast kind="success" :message="$success" />
        <x-ui.toast kind="error" :message="$error" />

        <form wire:submit="indexChunk" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <h4 class="text-ink font-bold">Index Knowledge Chunk</h4>
            <input type="text" wire:model="chunkTitle" placeholder="Title" class="border rounded p-2 text-ink flex-1 bg-surface">
            <input type="text" wire:model="chunkText" placeholder="Text" class="border rounded p-2 text-ink flex-1 bg-surface">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Index Chunk</button>
        </form>

        <form wire:submit="search" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <h4 class="text-ink font-bold">Run Retrieval Search</h4>
            <input type="text" wire:model="searchQuery" placeholder="Query" class="border rounded p-2 text-ink flex-1 bg-surface">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Search (Calls embedding model)</button>
        </form>

        @if($entries->isEmpty())
            <p class="text-gray-500">No cached retrieval queries.</p>
        @else
            <ul>
                @foreach($entries as $e)
                    <li>#{{ $e->id }}: "{{ $e->query_text }}" (Chunks: {{ count($e->result_chunk_ids) }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
