<div>
    @if ($error)
        <div class="text-ink mb-4">{{ $error }}</div>
    @endif
    @if ($success)
        <div class="text-ink mb-4">{{ $success }}</div>
    @endif

    <div class="mb-8">
        @if($pages->isEmpty())
            <div class="text-ink-2">No pages yet. Add one below.</div>
        @else
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-rule">
                        <th class="py-2 text-ink">Slug</th>
                        <th class="py-2 text-ink">Title</th>
                        <th class="py-2 text-ink">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pages as $page)
                        <tr class="border-b border-rule">
                            <td class="py-2 text-ink">{{ $page->slug }}</td>
                            <td class="py-2 text-ink">{{ $page->title }}</td>
                            <td class="py-2 text-ink">
                                @if($page->is_published)
                                    <span class="bg-paper border border-rule px-2 py-1 text-ink">Published</span>
                                @else
                                    <span class="bg-paper border border-rule px-2 py-1 text-ink-2">Draft</span>
                                    <button wire:click="publish({{ $page->id }})" class="ml-2 bg-paper border border-rule px-2 py-1 text-ink">Publish</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="bg-paper border border-rule p-4">
        <form wire:submit="addPage">
            <div class="mb-4">
                <label class="block text-ink mb-1">Slug</label>
                <input type="text" wire:model="newSlug" class="w-full bg-paper border border-rule text-ink p-2">
            </div>
            <div class="mb-4">
                <label class="block text-ink mb-1">Title</label>
                <input type="text" wire:model="newTitle" class="w-full bg-paper border border-rule text-ink p-2">
            </div>
            <button type="submit" class="bg-paper border border-rule text-ink px-4 py-2">Add Page</button>
        </form>
    </div>
</div>
