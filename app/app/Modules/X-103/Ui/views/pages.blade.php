<div>
    <x-ui.toast kind="error" :message="$error" />
    <x-ui.toast kind="success" :message="$success" />

    @if($editing)
        @php
            $hasChanges = $editing->is_published && $this->draftDiffersFromPublished($editing);
        @endphp
        
        <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-4">
                <h2 class="font-display text-2xl font-semibold text-ink">Editing {{ $editing->title }}</h2>
                <span class="font-mono text-sm text-ink-2">{{ $editing->slug }}</span>
                
                @if($editing->is_published)
                    @if($hasChanges)
                        <x-ui.status-pill state="attention" label="Live · unpublished changes" />
                    @else
                        <x-ui.status-pill state="ok" label="Live" />
                    @endif
                @else
                    <x-ui.status-pill state="ok" label="Draft" />
                @endif

                @if($editing->is_published && isset($deployments[$editing->id]) && $deployments[$editing->id]->status === 'deployed')
                    <a href="{{ url('/sites/'.$businessId.'/'.$deployments[$editing->id]->deploy_hash) }}" class="text-sm text-ink underline ml-2" target="_blank">Live link</a>
                @endif
            </div>
            
            <div class="flex items-center gap-2">
                <x-ui.button variant="quiet" size="default" wire:click="toggleHistory({{ $editing->id }})">History</x-ui.button>
                @if($editing->is_published)
                    <x-ui.button variant="quiet" size="default" wire:click="unpublish({{ $editing->id }})">Unpublish</x-ui.button>
                @endif
                <x-ui.button variant="secondary" size="default" wire:click="duplicatePage({{ $editing->id }})">Duplicate</x-ui.button>
                <x-ui.button variant="secondary" size="default" wire:click="closeEditor">Close editor</x-ui.button>
                @if(! $editing->is_published)
                    <x-ui.button variant="primary" size="default" wire:click="publish({{ $editing->id }})">Publish</x-ui.button>
                @elseif($hasChanges)
                    <x-ui.button variant="primary" size="default" wire:click="publish({{ $editing->id }})">Publish changes</x-ui.button>
                @endif
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <div class="w-full lg:w-[360px] flex-shrink-0 space-y-6">
                
                <div class="bg-paper rounded-[--radius-card] border border-rule p-4">
                    <h3 class="font-semibold text-ink mb-2">Say what to change</h3>
                    
                    @if(isset($editing->draft_meta['pending_edit']))
                        <div class="space-y-4 mb-4">
                            @foreach($editing->draft_meta['pending_edit']['thread'] ?? [] as $t)
                                <div class="bg-card border border-rule rounded p-3">
                                    <div class="text-xs font-semibold text-ink-2 mb-1">You</div>
                                    <div class="text-sm text-ink">{{ $t['request'] ?? '' }}</div>
                                </div>
                                <div class="bg-surface border border-rule rounded p-3 ml-4">
                                    <div class="text-xs font-semibold text-ink-2 mb-1">Draft</div>
                                    <div class="text-sm text-ink">{{ $t['explanation'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="flex gap-2 mb-4">
                            <x-ui.button variant="primary" size="default" class="flex-1" wire:click="applyEdit({{ $editing->id }})">Apply to draft</x-ui.button>
                            <x-ui.button variant="secondary" size="default" class="flex-1" wire:click="discardEdit({{ $editing->id }})">Discard</x-ui.button>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-ink mb-1">Not quite?</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="editRequest.{{ $editing->id }}" class="flex-1 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-ink" placeholder="Follow-up input">
                                <x-ui.button variant="secondary" size="default" wire:click="askEdit({{ $editing->id }})">Ask</x-ui.button>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col gap-2">
                            <input type="text" wire:model="editRequest.{{ $editing->id }}" class="w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-ink" placeholder="e.g. make the intro shorter">
                            <x-ui.button variant="primary" size="default" wire:click="askEdit({{ $editing->id }})">Ask</x-ui.button>
                            
                            @if(!empty($editing->draft_meta['undo']))
                                <div class="mt-2 text-right">
                                    <button wire:click="undoEdit({{ $editing->id }})" class="text-sm text-ink underline">Undo last change</button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div>
                    <h3 class="font-semibold text-ink mb-3">On this page</h3>
                    <div class="space-y-2">
                        @foreach($editing->draft_blocks ?? [] as $idx => $block)
                            <div class="bg-card rounded-[--radius-card] border border-rule p-3">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="text-xs font-semibold text-ink-2 uppercase tracking-wide">{{ $block['type'] ?? 'Block' }}</div>
                                    <button wire:click="removeBlock({{ $editing->id }}, {{ $idx }})" class="text-xs text-ink-2 hover:text-ink">Remove</button>
                                </div>
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
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="font-semibold text-ink">Add</h3>
                    
                    <details class="group">
                        <summary class="cursor-pointer text-sm font-medium text-ink bg-card border border-rule rounded-[--radius-control] px-3 py-2">Ask the AI for questions and answers</summary>
                        <div class="p-3 mt-2 bg-card border border-rule rounded-[--radius-card]">
                            <form wire:submit="addFaq({{ $editing->id }})" class="space-y-2">
                                <input type="text" wire:model="faqQuestion" placeholder="Question" class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-sm text-ink">
                                <input type="text" wire:model="faqAnswer" placeholder="Answer" class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-sm text-ink">
                                <x-ui.button variant="secondary" size="default" type="submit">Add FAQ</x-ui.button>
                            </form>
                            <div class="mt-4 pt-4 border-t border-rule">
                                <p class="text-xs text-ink-2 mb-2">Or ask the AI to draft questions</p>
                                @if(!isset($editing->draft_meta['pending_faq']))
                                    <x-ui.button variant="secondary" size="default" wire:click="draftFaq({{ $editing->id }})">Draft FAQ</x-ui.button>
                                @else
                                    <strong>Proposed questions</strong>
                                    <ul class="list-disc pl-5 my-2">
                                        @foreach($editing->draft_meta['pending_faq']['items'] ?? [] as $item)
                                            <li><span class="font-medium">{{ $item['question'] ?? '' }}</span> — {{ $item['answer'] ?? '' }}</li>
                                        @endforeach
                                    </ul>
                                    <span class="text-sm text-ink-2">written by {{ $editing->draft_meta['pending_faq']['model'] ?? 'the AI' }}</span>
                                    <div class="space-y-2 mt-4">
                                        <x-ui.button variant="primary" size="default" class="w-full" wire:click="placeFaq({{ $editing->id }})">Place on this page</x-ui.button>
                                        <x-ui.button variant="secondary" size="default" class="w-full" wire:click="discardFaq({{ $editing->id }})">Discard</x-ui.button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </details>
                    
                    <details class="group">
                        <summary class="cursor-pointer text-sm font-medium text-ink bg-card border border-rule rounded-[--radius-control] px-3 py-2">Add video</summary>
                        <div class="p-3 mt-2 bg-card border border-rule rounded-[--radius-card]">
                            <form wire:submit="addVideo({{ $editing->id }})" class="space-y-2">
                                <input type="text" wire:model="videoName" placeholder="Name" class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-sm text-ink">
                                <input type="text" wire:model="videoUrl" placeholder="https://..." class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-sm text-ink">
                                <input type="text" wire:model="videoDate" placeholder="Date" class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-sm text-ink">
                                <x-ui.button variant="secondary" size="default" type="submit">Add video</x-ui.button>
                            </form>
                        </div>
                    </details>
                </div>

                <details class="group">
                    <summary class="cursor-pointer text-sm font-medium text-ink bg-card border border-rule rounded-[--radius-control] px-3 py-2">SEO settings</summary>
                    <div class="p-3 mt-2 bg-card border border-rule rounded-[--radius-card]">
                        <form wire:submit="saveSeo({{ $editing->id }})" class="space-y-2">
                            <input type="text" wire:model="seoTitle.{{ $editing->id }}" placeholder="Title" class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-sm text-ink">
                            <textarea wire:model="seoDescription.{{ $editing->id }}" placeholder="Description" class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-sm text-ink" rows="3"></textarea>
                            <x-ui.button variant="secondary" size="default" type="submit">Save SEO</x-ui.button>
                        </form>
                        <div class="mt-4 pt-4 border-t border-rule">
                            <x-ui.button variant="secondary" size="default" wire:click="draftSeo({{ $editing->id }})">Draft title & description</x-ui.button>
                        </div>
                    </div>
                </details>

                <details class="group">
                    <summary class="cursor-pointer text-sm font-medium text-ink bg-card border border-rule rounded-[--radius-control] px-3 py-2">Rename page</summary>
                    <div class="p-3 mt-2 bg-card border border-rule rounded-[--radius-card]">
                        <form wire:submit="rename({{ $editing->id }})" class="space-y-2">
                            <input type="text" wire:model="renameSlug.{{ $editing->id }}" placeholder="Slug" class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-sm text-ink">
                            <input type="text" wire:model="renameTitle.{{ $editing->id }}" placeholder="Title" class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-sm text-ink">
                            <x-ui.button variant="secondary" size="default" type="submit">Rename</x-ui.button>
                        </form>
                    </div>
                </details>

                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <h3 class="font-semibold text-ink">Questions customers asked ({{ count($questions) }})</h3>
                        </div>
                        <div class="space-y-3 bg-card border border-rule rounded-[--radius-card] p-3">
                        @if(count($questions) === 0)
                            <p class="text-sm text-ink-2">Nothing waiting — every question typed into your site chat or your contact form in the last while has been answered on a page, or none has been asked yet.</p>
                        @else
                            @foreach ($questions as $q)
                                <div class="text-sm">
                                    <div class="font-medium text-ink">{{ $q['question'] }}</div>
                                    <div class="text-xs text-ink-2 mt-1">From {{ $q['source'] === 'chat' ? 'site chat' : 'contact form' }}</div>
                                    <div class="mt-2 flex gap-2">
                                        <select wire:model="answerPage.{{ $q['key'] }}" class="flex-1 rounded-[--radius-control] border border-rule bg-paper px-2 py-1 text-xs">
                                            <option value="">Choose a page</option>
                                            @foreach($pages as $p)
                                                <option value="{{ $p->id }}">{{ $p->slug }}</option>
                                            @endforeach
                                        </select>
                                        <x-ui.button variant="secondary" size="default" class="!px-2 !py-1 !min-h-0 text-xs" wire:click="draftAnswer('{{ $q['key'] }}')">Draft an answer</x-ui.button>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                        </div>
                    </div>
                
                @if(!empty($showHistory[$editing->id]) && isset($versions[$editing->id]))
                    <div class="bg-card border border-rule rounded-[--radius-card] p-4 mt-6">
                        <h3 class="font-semibold text-ink mb-4">History</h3>
                        <div class="space-y-4">
                            @foreach($versions[$editing->id] as $version)
                                <div class="border-l-2 border-rule pl-4 pb-4">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-medium text-ink">{{ \Carbon\Carbon::parse($version->created_at)->diffForHumans() }}</span>
                                        @if($editing->current_version_id === $version->id)
                                            <x-ui.status-pill state="ok" label="Current version" />
                                        @endif
                                    </div>
                                    <div class="font-mono text-xs text-ink-2 mb-2">id: {{ $version->id }}</div>
                                    @if($editing->current_version_id !== $version->id)
                                        <x-ui.button variant="secondary" size="default" wire:click="restore({{ $editing->id }}, {{ $version->id }})">Restore</x-ui.button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex-1 flex flex-col items-center">
                <div class="w-full flex justify-between items-center mb-4">
                    <div>
                        @if(isset($editing->draft_meta['pending_edit']))
                            <div class="inline-flex rounded-[--radius-control] border border-rule bg-card p-1">
                                <button type="button" wire:click="showProposed(true)" class="px-3 py-1 text-sm font-medium rounded transition-colors {{ $previewProposed ? 'bg-paper text-ink shadow-sm' : 'text-ink-2 hover:text-ink' }}">Proposed</button>
                                <button type="button" wire:click="showProposed(false)" class="px-3 py-1 text-sm font-medium rounded transition-colors {{ !$previewProposed ? 'bg-paper text-ink shadow-sm' : 'text-ink-2 hover:text-ink' }}">Current draft</button>
                            </div>
                        @endif
                    </div>
                    <div>
                        <x-ui.segmented 
                            :options="['phone' => 'Phone', 'desktop' => 'Desktop']" 
                            :selected="$deviceWidth" 
                            action="setDeviceWidth" 
                        />
                    </div>
                </div>
                
                <div class="transition-all duration-300 ease-in-out {{ $deviceWidth === 'desktop' ? 'w-full' : 'w-[375px]' }}">
                    <div class="bg-card border border-rule rounded-[--radius-card] overflow-hidden">
                        <iframe title="Page preview" sandbox="" class="w-full min-h-[70vh] border-0" srcdoc="{{ $previewHtml }}"></iframe>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="max-w-3xl">
            <h2 class="font-display text-2xl font-semibold text-ink mb-6">Pick a page</h2>
            
            <div class="space-y-4 mb-12">
                @forelse($pages as $page)
                    @php
                        $hasChanges = $page->is_published && $this->draftDiffersFromPublished($page);
                    @endphp
                    <div class="bg-card border border-rule rounded-[--radius-card] p-5 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <h3 class="font-semibold text-lg text-ink">{{ $page->title }}</h3>
                                @if($page->is_published)
                                    @if($hasChanges)
                                        <x-ui.status-pill state="attention" label="Live · unpublished changes" />
                                    @else
                                        <x-ui.status-pill state="ok" label="Live" />
                                    @endif
                                @else
                                    <x-ui.status-pill state="ok" label="Draft" />
                                @endif
                            </div>
                            <div class="text-sm text-ink-2">
                                <span class="font-mono mr-3">/{{ $page->slug }}</span>
                                {{ count($page->draft_blocks ?? []) }} {{ count($page->draft_blocks ?? []) === 1 ? 'section' : 'sections' }}
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-3">
                            <x-ui.button variant="secondary" size="default" wire:click="duplicatePage({{ $page->id }})">Duplicate</x-ui.button>
                            <x-ui.button variant="secondary" size="default" wire:click="polish({{ $page->id }})">Polish copy</x-ui.button>
                            <x-ui.button variant="secondary" size="default" wire:click="restoreOriginal({{ $page->id }})">Restore original</x-ui.button>
                            @if(empty($hasVersions[$page->id]))
                                <x-ui.button variant="quiet" size="default" wire:click="deletePage({{ $page->id }})">Delete</x-ui.button>
                            @endif
                            <x-ui.button variant="quiet" size="default" wire:click="toggleHistory({{ $page->id }})">History</x-ui.button>
                            <x-ui.button variant="primary" size="default" wire:click="openEditor({{ $page->id }})">Open</x-ui.button>
                        </div>
                        
                        @if(!empty($showHistory[$page->id]) && isset($versions[$page->id]))
                            <div class="w-full mt-4 pt-4 border-t border-rule space-y-4">
                                @foreach($versions[$page->id] as $version)
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="font-medium text-ink">{{ \Carbon\Carbon::parse($version->created_at)->diffForHumans() }}</span>
                                                @if($page->current_version_id === $version->id)
                                                    <x-ui.status-pill state="ok" label="Current" />
                                                @endif
                                            </div>
                                            <div class="font-mono text-xs text-ink-2">id: {{ $version->id }}</div>
                                        </div>
                                        @if($page->current_version_id !== $version->id)
                                            <x-ui.button variant="secondary" size="default" wire:click="restore({{ $page->id }}, {{ $version->id }})">Restore</x-ui.button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-ink-2">No pages yet. Add one below.</div>
                @endforelse
            </div>
            
            <div class="bg-paper border border-rule rounded-[--radius-card] p-6 mb-4">
                <form wire:submit="makePage">
                    <h2 class="font-display text-xl font-semibold text-ink mb-2">Make me a page</h2>
                    <p class="text-ink-2 mb-4">Say what the page is for, in your own words. It lands as an unpublished draft you can publish or delete.</p>
                    <div class="flex gap-3">
                        <input type="text" wire:model="pageRequest" placeholder="e.g. a page about our emergency call-out service" class="flex-1 rounded-[--radius-control] border border-rule bg-card px-4 py-3 text-lg text-ink">
                        <x-ui.button variant="primary" size="giant" type="submit">Make it</x-ui.button>
                    </div>
                </form>
            </div>
            
            <details class="group">
                <summary class="cursor-pointer text-sm text-ink-2 hover:text-ink">Add a blank page instead</summary>
                <div class="mt-4 p-4 bg-card border border-rule rounded-[--radius-card]">
                    <form wire:submit="addPage" class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-ink mb-1">Slug</label>
                            <input type="text" wire:model="newSlug" class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-ink">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-ink mb-1">Title</label>
                            <input type="text" wire:model="newTitle" class="w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-ink">
                        </div>
                        <x-ui.button variant="secondary" size="default" type="submit">Add Page</x-ui.button>
                    </form>
                </div>
            </details>
        </div>
    @endif
</div>