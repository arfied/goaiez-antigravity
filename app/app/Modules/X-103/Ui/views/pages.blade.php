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
                        <th class="py-2 text-ink">Content</th>
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
                                    @if(isset($deployments[$page->id]) && $deployments[$page->id]->status === 'deployed')
                                        <a href="{{ url('/sites/'.$businessId.'/'.$deployments[$page->id]->deploy_hash) }}" class="ml-2 text-ink underline">Live link</a>
                                    @endif
                                    <button wire:click="unpublish({{ $page->id }})" class="ml-2 bg-paper border border-rule px-2 py-1 text-ink">Unpublish</button>
                                @else
                                    <span class="bg-paper border border-rule px-2 py-1 text-ink-2">Draft</span>
                                    <button wire:click="publish({{ $page->id }})" class="ml-2 bg-paper border border-rule px-2 py-1 text-ink">Publish</button>
                                @endif
                            </td>
                            <td class="py-2 text-ink">
                                <details class="mb-2">
                                    <summary class="cursor-pointer">Edit name</summary>
                                    <div class="p-2 mt-2 bg-paper border border-rule">
                                        <form wire:submit="rename({{ $page->id }})">
                                            <input type="text" wire:model="renameSlug.{{ $page->id }}" placeholder="Slug" class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                            <input type="text" wire:model="renameTitle.{{ $page->id }}" placeholder="Title" class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                            <button type="submit" class="bg-paper border border-rule px-2 py-1">Rename</button>
                                        </form>
                                    </div>
                                </details>
                                <details>
                                    <summary class="cursor-pointer">Edit content</summary>
                                    <div class="p-2 mt-2 bg-paper border border-rule">
                                        @if($page->draft_blocks)
                                            @foreach($page->draft_blocks as $idx => $block)
                                                <div class="mb-2 border-b border-rule pb-2">
                                                    @if(($block['type'] ?? '') === 'faq')
                                                        <div><strong>FAQ:</strong> {{ $block['question'] ?? '' }} / {{ $block['answer'] ?? '' }}</div>
                                                    @elseif(($block['type'] ?? '') === 'video_embed')
                                                        <div><strong>Video:</strong> {{ $block['name'] ?? '' }} / {{ $block['contentUrl'] ?? '' }} / {{ $block['uploadDate'] ?? '' }}</div>
                                                    @endif
                                                    <button wire:click="removeBlock({{ $page->id }}, {{ $idx }})" class="text-ink underline text-sm mt-1">Remove</button>
                                                </div>
                                            @endforeach
                                        @endif
                                        
                                        <form wire:submit="addFaq({{ $page->id }})" class="mt-4">
                                            <div class="font-bold mb-1">Add FAQ</div>
                                            <input type="text" wire:model="faqQuestion" placeholder="Question" class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                            <input type="text" wire:model="faqAnswer" placeholder="Answer" class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                            <button type="submit" class="bg-paper border border-rule px-2 py-1">Add FAQ</button>
                                        </form>

                                        <form wire:submit="addVideo({{ $page->id }})" class="mt-4 border-t border-rule pt-2">
                                            <div class="font-bold mb-1">Add video</div>
                                            <input type="text" wire:model="videoName" placeholder="Name" class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                            <input type="text" wire:model="videoUrl" placeholder="https://..." class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                            <input type="text" wire:model="videoDate" placeholder="Date" class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                            <button type="submit" class="bg-paper border border-rule px-2 py-1">Add video</button>
                                        </form>
                                    </div>
                                </details>
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
