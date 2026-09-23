<div>
    <div class="proposed-pages-view p-4">
        <h2 class="text-lg font-bold text-ink">Proposed pages</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <form wire:submit="submit" class="flex flex-col gap-2">
                @if($success) <div class="text-ink font-bold">{{ $success }}</div> @endif
                @if($error) <div class="text-ink font-bold">{{ $error }}</div> @endif
                <input type="text" wire:model="topicTitle" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Topic title">
                <input type="text" wire:model="clusterKey" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Cluster key">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h3 class="font-bold text-ink">Draft from conversation</h3>
            <form wire:submit="draftFromConversation" class="flex flex-col gap-2">
                <select wire:model="draftTopicId" class="border rounded p-2 text-ink flex-1 bg-surface">
                    <option value="0">Select a topic...</option>
                    @foreach($topics as $t)
                        <option value="{{ $t->id }}">{{ $t->topic_title }}</option>
                    @endforeach
                </select>
                <input type="text" wire:model="rawContent" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Raw conversation content">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Draft Content</button>
            </form>
        </div>

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
