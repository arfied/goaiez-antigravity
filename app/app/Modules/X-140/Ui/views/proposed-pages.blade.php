<div>
    <div class="proposed-pages-view p-4">
        <h2 class="text-lg font-bold text-ink">Proposed pages</h2>
        @if($topics->isEmpty())
            <x-ui.empty-state heading="No proposed pages yet.">A customer question that recurs becomes a page proposal here, with the conversations that raised it.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($topics as $topic)
                    <li class="py-2">
                        <span class="font-semibold">{{ $topic->topic_title }}</span>
                        <span class="text-ink-2">{{ $topic->cluster_key }}</span>
                        <span class="text-sm text-ink-2">{{ $topic->sources->count() }} {{ $topic->sources->count() === 1 ? 'source' : 'sources' }}</span>
                        <span class="text-sm text-ink-2">{{ $topic->is_published ? 'published' : 'draft' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
