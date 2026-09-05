<div>
    <x-surface.sample-state module="explicit allow for AI crawlers *(GPTBot" screen="seo_tab_website" />
    <div class="seo-tab-view p-4">
        <h3 class="text-lg font-bold">SEO & Schema.org Status</h3>
        @if($schemas->isEmpty())
            <p class="text-gray-500">No schema snapshots published.</p>
        @else
            <ul>
                @foreach($schemas as $s)
                    <li>Page #{{ $s->page_id }}: [{{ $s->entity_type }}] (Commit: {{ $s->commit_id }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
