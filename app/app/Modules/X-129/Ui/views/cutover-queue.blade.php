<div>
    <x-surface.sample-state module="⭐⭐⭐ **R130, in the owner's words: "a tenant is never left on a new empty domain — if they have a site, it MOVES and everything it earned moves with it": rankings, links, history — not just files.** Crawl and import" screen="cutover_queue" />
    <div class="cutover-queue-view p-4">
        <h3 class="text-lg font-bold">Cutover Redirect Queue</h3>
        @if($redirects->isEmpty())
            <p class="text-gray-500">No redirects mapped.</p>
        @else
            <ul>
                @foreach($redirects as $r)
                    <li>{{ $r->source_url }} &rarr; {{ $r->destination_url }} [{{ $r->status_code }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
