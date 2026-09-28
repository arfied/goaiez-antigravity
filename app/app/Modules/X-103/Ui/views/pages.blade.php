<div>
    <x-ui.toast kind="error" :message="$error" />
    <x-ui.toast kind="success" :message="$success" />

    @if($editing)
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold">Editing {{ $editing->title }}</h2>
                <button wire:click="closeEditor" class="bg-paper border border-rule px-4 py-2">Close editor</button>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    @if(isset($editing->draft_meta['pending_edit']['thread']))
                        <div class="mb-4">
                            @foreach($editing->draft_meta['pending_edit']['thread'] as $t)
                                <div class="mb-2">
                                    <strong>You asked:</strong> {{ $t['request'] ?? '' }}
                                    <div class="text-ink-2">{{ $t['explanation'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="mb-4">
                        <input type="text" wire:model="editRequest.{{ $editing->id }}" placeholder="Say what to change" class="w-full bg-paper border border-rule text-ink p-2 mb-2">
                        <button wire:click="askEdit({{ $editing->id }})" class="bg-paper border border-rule px-4 py-2 mr-2">Ask</button>
                        @if(isset($editing->draft_meta['pending_edit']))
                            <button wire:click="applyEdit({{ $editing->id }})" class="bg-paper border border-rule px-4 py-2 mr-2">Apply to draft</button>
                            <button wire:click="discardEdit({{ $editing->id }})" class="bg-paper border border-rule px-4 py-2">Discard</button>
                        @endif
                    </div>
                    @if(!empty($editing->draft_meta['undo']))
                        <button wire:click="undoEdit({{ $editing->id }})" class="bg-paper border border-rule px-4 py-2 mb-4 block">Undo last change</button>
                    @endif
                    <div>
                        <button wire:click="publish({{ $editing->id }})" class="bg-paper border border-rule px-4 py-2 text-ink">Publish</button>
                    </div>
                </div>
                <div>
                    @if(isset($editing->draft_meta['pending_edit']))
                        <div class="mb-2 flex gap-2">
                            <button wire:click="showProposed(true)" class="bg-paper border border-rule px-4 py-2 {{ $previewProposed ? 'font-bold' : '' }}">Proposed</button>
                            <button wire:click="showProposed(false)" class="bg-paper border border-rule px-4 py-2 {{ !$previewProposed ? 'font-bold' : '' }}">Current draft</button>
                        </div>
                    @endif
                    <iframe title="Page preview" sandbox="" class="w-full h-[70vh] border border-rule rounded" srcdoc="{{ $previewHtml }}"></iframe>
                </div>
            </div>
        </div>
    @endif

    <details class="mb-4">
        <summary class="cursor-pointer">Questions customers asked ({{ count($questions) }})</summary>
        <div class="p-2 mt-2 bg-paper border border-rule">
            @if (count($questions) === 0)
                <p class="text-sm text-ink-2">Nothing waiting — every question typed into your site chat or your contact form in the last while has been answered on a page, or none has been asked yet.</p>
            @else
                <p class="text-sm text-ink-2 mb-2">Typed by visitors into your site chat or your contact form. Shown to you only — nothing here is on a page until you answer it and place the answer.</p>
                <ul class="list-disc pl-5">
                    @foreach ($questions as $q)
                        <li class="mb-1"><span class="font-medium">{{ $q['question'] }}</span> <span class="text-sm text-ink-2">— from your {{ $q['source'] === 'chat' ? 'site chat' : 'contact form' }}</span>
    <div class="mt-1">
        <select wire:model="answerPage.{{ $q['key'] }}" class="px-2 py-1 border border-rule">
            <option value="">Choose a page</option>
            @foreach($pages as $p)
                <option value="{{ $p->id }}">{{ $p->slug }}</option>
            @endforeach
        </select>
        <button wire:click="draftAnswer('{{ $q['key'] }}')" class="px-2 py-1 bg-surface-2 border border-rule hover:bg-surface-3">Draft an answer</button>
    </div>
</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </details>

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
                                    @if(!empty($hasChanges[$page->id]))
                                        <button wire:click="publish({{ $page->id }})" class="ml-2 bg-paper border border-rule px-2 py-1 text-ink">Publish changes</button>
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
                                <button wire:click="openEditor({{ $page->id }})" class="ml-2 bg-paper border border-rule px-2 py-1 text-ink">Open editor</button>
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
                                                <p>{{ $page->draft_meta['pending_edit']['explanation'] ?? '' }}</p>

                                                @if(isset($page->draft_meta['pending_edit']['style_refused']))
                                                    <p class="text-sm text-ink mt-2"><strong>Proposed style refused:</strong> {{ $page->draft_meta['pending_edit']['style_refused'] }}</p>
                                                @endif
                                                @if(isset($page->draft_meta['pending_edit']['style']))
                                                    <div class="mt-2">
                                                        <strong>Proposed style:</strong>
                                                        @if(isset($page->draft_meta['pending_edit']['style']['palette']))
                                                            @foreach($page->draft_meta['pending_edit']['style']['palette'] as $hex)
                                                                <span style="background: {{ $hex }}" class="inline-block w-4 h-4 rounded-full border border-rule mr-1"></span>
                                                            @endforeach
                                                        @endif
                                                        @if(isset($page->draft_meta['pending_edit']['style']['type_pairing']))
                                                            <span class="text-sm ml-2">{{ implode(' / ', array_values($page->draft_meta['pending_edit']['style']['type_pairing'])) }}</span>
                                                        @endif
                                                    </div>
                                                @endif
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
                                <details class="mb-2">
                                    <summary class="cursor-pointer">Ask the AI for questions and answers</summary>
                                    <div class="p-2 mt-2 bg-paper border border-rule">
                                        @if(!isset($page->draft_meta['pending_faq']))
                                            <p class="text-sm text-ink-2 mb-1">It writes plain questions a customer would ask, using only your confirmed prices and the reviews you show — nothing it cannot back up. You place them or discard them.</p>
                                            <button wire:click="draftFaq({{ $page->id }})" class="bg-paper border border-rule px-2 py-1 text-ink mb-2">Ask</button>
                                        @else
                                            <div class="mt-2 p-2 bg-paper border border-rule">
                                                <strong>Proposed questions</strong>
                                                <ul class="list-disc pl-5 my-2">
                                                    @foreach($page->draft_meta['pending_faq']['items'] ?? [] as $item)
                                                        <li><span class="font-medium">{{ $item['question'] ?? '' }}</span> — {{ $item['answer'] ?? '' }}</li>
                                                    @endforeach
                                                </ul>
                                                <span class="text-sm text-ink-2">written by {{ $page->draft_meta['pending_faq']['model'] ?? 'the AI' }}</span>
                                                <div class="mt-2">
                                                    <button wire:click="placeFaq({{ $page->id }})" class="bg-paper border border-rule px-2 py-1 text-ink mr-2">Place on this page</button>
                                                    <button wire:click="discardFaq({{ $page->id }})" class="bg-paper border border-rule px-2 py-1 text-ink">Discard</button>
                                                </div>
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
                                                        @if(isset($block['items']) && is_array($block['items']))
                                                            <div><strong>FAQ:</strong> {{ count($block['items']) }} {{ count($block['items']) === 1 ? 'question' : 'questions' }} — {{ $block['items'][0]['question'] ?? '' }}@if(count($block['items']) > 1) …@endif</div>
                                                        @else
                                                            <div><strong>FAQ:</strong> {{ $block['question'] ?? '' }} / {{ $block['answer'] ?? '' }}</div>
                                                        @endif
                                                    @elseif(($block['type'] ?? '') === 'video_embed')
                                                        <div><strong>Video:</strong> {{ $block['name'] ?? '' }} / {{ $block['contentUrl'] ?? '' }} / {{ $block['uploadDate'] ?? '' }}</div>
                                                    @else
                                                        <div><strong>{{ ucfirst($block['type'] ?? 'Block') }}:</strong> {{ $block['headline'] ?? $block['text'] ?? $block['label'] ?? '' }}</div>
                                                    @endif
                                                    @if(($block['source'] ?? '') === 'ai')
                                                        <span class="text-sm text-ink-2">— written by {{ $block['model'] ?? 'the AI' }} @if(!empty($block['peers'])), with {{ $block['peers'] }} nearby {{ $block['peers'] === 1 ? 'business' : 'businesses' }} as reference @endif</span>
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
