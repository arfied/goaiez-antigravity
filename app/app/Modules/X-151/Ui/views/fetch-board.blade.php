<div>
    <x-surface.sample-state module="deep scraping and bulk extraction" screen="fetch_board" />
    <div class="fetch-board-view p-4">
        <h3 class="text-lg font-bold">Web Scraper & Fetch Board</h3>
        @if($fetches->isEmpty())
            <p class="text-gray-500">No web crawl jobs executed.</p>
        @else
            <ul>
                @foreach($fetches as $f)
                    <li>#{{ $f->id }}: {{ $f->url }} [{{ $f->status }}] (Stale: {{ $f->is_stale ? 'Yes' : 'No' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
