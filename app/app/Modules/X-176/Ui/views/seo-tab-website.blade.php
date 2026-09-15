<div>
    <div class="seo-tab-view p-4">
        <h2 class="text-lg font-bold text-ink">Schema status</h2>
        @if($schemas->isEmpty())
            <p class="text-ink-2">No schema snapshots published.</p>
        @else
            <ul>
                @foreach($schemas as $s)
                    <li>Page #{{ $s->page_id }}: [{{ $s->entity_type }}] (Commit: {{ $s->commit_id }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
