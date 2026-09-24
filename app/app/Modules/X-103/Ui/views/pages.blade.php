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
                                    <button wire:click="polish({{ $page->id }})" class="ml-2 bg-paper border border-rule px-2 py-1 text-ink">Polish copy</button>
                                    <button wire:click="restoreOriginal({{ $page->id }})" class="ml-2 bg-paper border border-rule px-2 py-1 text-ink">Restore original</button>
                                    @if(empty($hasVersions[$page->id]))
                                        <button wire:click="deletePage({{ $page->id }})" class="ml-2 bg-paper border border-rule px-2 py-1 text-ink">Delete</button>
                                    @endif
                                @endif
                                <button wire:click="duplicatePage({{ $page->id }})" class="ml-2 bg-paper border border-rule px-2 py-1 text-ink">Duplicate</button>
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
                                <details class="mb-2">
                                    <summary class="cursor-pointer">Edit SEO</summary>
                                    <div class="p-2 mt-2 bg-paper border border-rule">
                                        <button wire:click="draftSeo({{ $page->id }})" class="bg-paper border border-rule px-2 py-1 mb-2 text-ink capitalize">draft title & description</button>
                                        <form wire:submit="saveSeo({{ $page->id }})">
                                            <input type="text" wire:model="seoTitle.{{ $page->id }}" placeholder="SEO Title" class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                            <input type="text" wire:model="seoDescription.{{ $page->id }}" placeholder="SEO Description" class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                            <button type="submit" class="bg-paper border border-rule px-2 py-1 text-ink">Save</button>
                                        </form>
                                    </div>
                                </details>
                                <details class="mb-2">
                                    <summary class="cursor-pointer">Ask the AI to change this page</summary>
                                    <div class="p-2 mt-2 bg-paper border border-rule">
                                        @if(!isset($page->draft_meta['pending_edit']))
                                            <input type="text" wire:model="editRequest.{{ $page->id }}" placeholder="Say what to change, e.g. add a page section about emergency call-outs" class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                            <button wire:click="askEdit({{ $page->id }})" class="bg-paper border border-rule px-2 py-1 text-ink mb-2">Ask</button>
                                        @else
                                            <div class="mt-2 p-2 bg-paper border border-rule">
                                                <strong>Proposed change</strong>
                                                <p>{{ $page->draft_meta['pending_edit']['explanation'] }}</p>
                                                
                                                <div class="grid grid-cols-2 gap-4 my-2">
                                                    <div>
                                                        <strong>Now</strong>
                                                        <ul class="list-disc ml-4">
                                                            @foreach($page->draft_blocks ?? [] as $b)
                                                                <li>{{ $b['type'] }}: {{ $b['headline'] ?? $b['text'] ?? $b['label'] ?? '' }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                    <div>
                                                        <strong>Proposed</strong>
                                                        <ul class="list-disc ml-4">
                                                            @php
                                                                $nowLines = collect($page->draft_blocks ?? [])->map(fn($b) => ($b['type'] ?? '') . ': ' . ($b['headline'] ?? $b['text'] ?? $b['label'] ?? ''))->toArray();
                                                            @endphp
                                                            @foreach($page->draft_meta['pending_edit']['blocks'] as $i => $b)
                                                                @php
                                                                    $line = ($b['type'] ?? '') . ': ' . ($b['headline'] ?? $b['text'] ?? $b['label'] ?? '');
                                                                    $leftLine = $nowLines[$i] ?? null;
                                                                    $isDiff = $line !== $leftLine;
                                                                @endphp
                                                                <li>@if($isDiff)→ @endif{{ $line }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </div>

                                                <div class="mt-4">
                                                    @foreach($page->draft_meta['pending_edit']['thread'] ?? [] as $t)
                                                        <div class="mb-1"><strong>You asked:</strong> {{ $t['request'] ?? '' }}</div>
                                                    @endforeach
                                                </div>

                                                <div class="mt-4 mb-4">
                                                    <label class="block mb-1">Not quite? Say what to change</label>
                                                    <input type="text" wire:model="editRequest.{{ $page->id }}" class="w-full bg-paper border border-rule text-ink p-1 mb-1">
                                                    <button wire:click="askEdit({{ $page->id }})" class="bg-paper border border-rule px-2 py-1 text-ink mb-2">Ask</button>
                                                </div>

                                                <button wire:click="applyEdit({{ $page->id }})" class="bg-paper border border-rule px-2 py-1 text-ink mr-2">Apply to draft</button>
                                                <button wire:click="discardEdit({{ $page->id }})" class="bg-paper border border-rule px-2 py-1 text-ink">Discard</button>
                                            </div>
                                        @endif
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
                                                    @else
                                                        <div><strong>{{ ucfirst($block['type'] ?? 'Block') }}:</strong> {{ $block['headline'] ?? $block['text'] ?? $block['label'] ?? '' }}</div>
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
                                <button wire:click="toggleHistory({{ $page->id }})" class="mt-2 text-ink underline cursor-pointer">History</button>
                                @if(!empty($showHistory[$page->id]) && isset($versions[$page->id]))
                                    <div class="p-2 mt-2 bg-paper border border-rule">
                                        @foreach($versions[$page->id] as $version)
                                            <div class="mb-2 border-b border-rule pb-2">
                                                <div>
                                                    <strong>{{ $version->commit_id }}</strong>
                                                    <span class="text-ink-2 text-sm ml-2">{{ $version->created_at }}</span>
                                                    @if($page->current_version_id === $version->id)
                                                        <span class="ml-2 text-ink">(Current)</span>
                                                    @endif
                                                </div>
                                                <div class="text-sm text-ink-2 mt-1">
                                                    @php
                                                        $authoredCount = collect($version->content_blocks)->filter(fn($b) => in_array($b['type'] ?? '', ['faq', 'video_embed']))->count();
                                                    @endphp
                                                    {{ $authoredCount }} authored block(s)
                                                </div>
                                                @if($page->current_version_id !== $version->id)
                                                    <button wire:click="restore({{ $page->id }}, {{ $version->id }})" class="mt-1 bg-paper border border-rule px-2 py-1 text-sm">Restore</button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="bg-paper border border-rule p-4 mb-4">
        <form wire:submit="makePage">
            <label class="block text-ink mb-1">Make me a page</label>
            <p class="text-ink-2 mb-2">Say what the page is for, in your own words. It lands as an unpublished draft you can publish or delete.</p>
            <input type="text" wire:model="pageRequest" placeholder="e.g. a page about our emergency call-out service" class="w-full bg-paper border border-rule text-ink p-2 mb-2">
            <button type="submit" class="bg-paper border border-rule text-ink px-4 py-2">Make it</button>
        </form>
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
