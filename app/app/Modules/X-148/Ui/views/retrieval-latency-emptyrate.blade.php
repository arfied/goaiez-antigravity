<div>
    <div class="retrieval-stats-view p-4">
        <h3 class="text-lg font-bold">Retrieval Latency & Empty-Rate Analytics</h3>
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
