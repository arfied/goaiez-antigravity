<div>
    <div class="flex min-h-screen"
         x-data
         x-on:message.window="
            if ($event.data.source === 'studio-canvas') {
                $wire.selectBlock($event.data.index);
            }
         ">
        <!-- Left Rail: Pages -->
        <div class="w-48 shrink-0 border-r border-rule p-4 overflow-y-auto">
            <h2 class="text-sm font-semibold mb-2">Pages</h2>
            <ul class="space-y-1">
                @foreach($pages as $page)
                    <li>
                        <button wire:click="$set('pageId', {{ $page->id }})" 
                                class="w-full text-left px-2 py-1 text-sm rounded hover:bg-canvas {{ $pageId === $page->id ? 'font-bold bg-card' : '' }}">
                            {{ $page->title ?: 'Untitled' }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        <!-- Main Content -->
        <div class="flex-1 min-w-0 p-4">
            @if($pageId)
                <div class="flex gap-4">
                    <!-- Canvas -->
                    <div class="flex-1 min-w-0">
                        <iframe title="Site preview"
                                sandbox="allow-scripts"
                                srcdoc="{{ $previewHtml }}"
                                class="w-full min-h-screen border border-rule bg-canvas"
                                id="studio-canvas"></iframe>
                    </div>

                    <!-- Inspector -->
                    <div class="w-96 shrink-0 border border-rule p-4 rounded bg-paper flex flex-col">
                        <div class="mb-4">
                            @if(isset($selectedPage) && isset($selectedPage->draft_meta['pending_edit']))
                                <div class="mb-2 p-2 bg-attention-bg text-attention text-sm font-bold rounded" id="proposal-marker">
                                    Previewing AI proposal
                                </div>
                            @endif
                            @if($error)
                                <div class="mb-2 p-2 bg-alert-bg text-alert text-sm rounded">{{ $error }}</div>
                            @endif
                            @if($success)
                                <div class="mb-2 p-2 bg-ok-bg text-ok text-sm rounded">{{ $success }}</div>
                            @endif

                            @if(isset($selectedPage) && isset($selectedPage->draft_meta['pending_edit']))
                                <div class="flex items-center gap-2 mb-2">
                                    <button wire:click="applyProposal" class="flex-1 text-center px-4 py-2 bg-ink text-paper font-bold rounded">Apply</button>
                                    <button wire:click="discardProposal" class="text-sm underline text-ink-2">Discard</button>
                                </div>
                            @elseif(empty($selectedPage->draft_blocks))
                                <a href="{{ route('x-103.site-build') }}" class="block w-full text-center px-4 py-2 bg-ink text-paper font-bold rounded mb-2">Generate site</a>
                            @elseif($this->draftDiffersFromPublished($selectedPage))
                                <button wire:click="publish({{ $pageId }})" class="block w-full text-center px-4 py-2 bg-ink text-paper font-bold rounded mb-2">Publish draft</button>
                            @endif
                            
                            @if(isset($selectedPage) && !empty($selectedPage->draft_blocks))
                            <div class="mt-4">
                                <label for="ask-input" class="block text-sm font-bold text-ink mb-1">Ask AI to edit</label>
                                <textarea id="ask-input" wire:model="request" class="w-full border border-rule rounded p-2 text-sm bg-paper text-ink" placeholder="E.g. Make it sound more professional..."></textarea>
                                <button wire:click="ask" class="block w-full text-center px-4 py-2 bg-ink text-paper font-bold rounded mt-2">Ask</button>
                            </div>
                            @endif
                            
                            <div class="text-center mt-4">
                                <a href="{{ route('x-103.pages', ['edit' => $pageId]) }}" class="text-sm underline text-brand">Open in Pages</a>
                            </div>
                        </div>
                        
                        <h2 class="font-bold mb-2">Inspector</h2>
                        @if($selectedBlockIndex !== null)
                            <div class="text-sm">
                                <p><span class="font-semibold">Block Index:</span> {{ $selectedBlockIndex }}</p>
                                @if($selectedBlockType)
                                    <p><span class="font-semibold">Type:</span> {{ $selectedBlockType }}</p>
                                @endif
                            </div>
                            @if($selectedBlockType === 'hero')
                                <div class="mt-3">
                                    <label for="block-headline" class="block text-sm font-bold text-ink mb-1">Headline</label>
                                    <input id="block-headline" type="text" wire:model="blockHeadline"
                                           class="w-full border border-rule rounded p-2 text-sm bg-paper text-ink">
                                    <button wire:click="setBlockField" class="mt-2 w-full px-4 py-2 bg-ink text-paper font-bold rounded">Save headline</button>
                                </div>
                            @endif
                        @else
                            <p class="text-sm text-ink-2">Select a block on the canvas.</p>
                        @endif
                    </div>
                </div>
            @else
                <div class="flex items-center justify-center h-full">
                    <p class="text-ink-2">You have no pages yet. <a href="{{ route('x-103.site-build') }}" class="underline text-brand">Run 'Build my site'</a> first.</p>
                </div>
            @endif
        </div>
    </div>
</div>
