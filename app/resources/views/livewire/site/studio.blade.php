<div>
    <div class="flex min-h-screen"
         x-data
         x-on:message.window="
            if ($event.source === document.getElementById('studio-canvas')?.contentWindow && $event.data && $event.data.source === 'studio-canvas') {
                if ($event.data.kind === 'edit') {
                    $wire.editInline($event.data.index, $event.data.field, $event.data.value);
                } else {
                    $wire.selectBlock($event.data.index);
                }
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
                                    @if(isset($selectedPage->draft_meta['pending_edit']['layout']))
                                        Previewing layout: {{ \App\Modules\X103\Domain\PageLayouts::LAYOUTS[$selectedPage->draft_meta['pending_edit']['layout']]['label'] ?? $selectedPage->draft_meta['pending_edit']['layout'] }}
                                    @else
                                        Previewing AI proposal
                                    @endif
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
                                <button wire:click="askDesign" class="block w-full text-center px-4 py-2 border border-rule text-ink font-bold rounded mt-2">Make it look great</button>
                            </div>
                            @endif
                            @if(isset($selectedPage))
                            <div class="mt-4 border-t border-rule pt-3">
                                <p class="text-sm font-bold text-ink mb-1">Theme</p>
                                <p class="text-xs text-ink-2 mb-2">A complete look for your whole site. Your words and sections stay the same; Undo brings the old look back.</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach(\App\Modules\X103\Domain\SiteThemes::THEMES as $themeKey => $themeInfo)
                                        <button wire:click="applyTheme('{{ $themeKey }}')" title="Best for: {{ $themeInfo['for'] }}" class="px-3 py-1 text-sm border border-rule rounded text-ink{{ $currentTheme === $themeKey ? ' font-bold' : '' }}">{{ $themeInfo['label'] }}</button>
                                    @endforeach
                                </div>
                                <p class="text-xs text-ink-2 mt-3 mb-1">Corners</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach(['square' => 'Square', 'soft' => 'Soft', 'round' => 'Round'] as $cornerKey => $cornerLabel)
                                        <button wire:click="setCorners('{{ $cornerKey }}')" class="px-3 py-1 text-sm border border-rule rounded text-ink{{ $currentCorners === $cornerKey ? ' font-bold' : '' }}">{{ $cornerLabel }}</button>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            @if(isset($selectedPage))
                            @php $designs = $selectedPage->draft_meta['designs'] ?? []; @endphp
                            <div class="mt-4 border-t border-rule pt-3">
                                <p class="text-sm font-bold text-ink mb-1">AI designer (preview)</p>
                                <p class="text-xs text-ink-2 mb-2">An AI designs this whole page — picks a theme, its colours and fonts, the sections and their words, and a picture — from your content and what top local businesses cover. Try each AI and compare; nothing changes until you use a design and press Apply.</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach(\App\Modules\X103\Domain\SiteDesignEngines::ENGINES as $engineKey => $engineInfo)
                                        <button wire:click="designWithAi('{{ $engineKey }}')" class="px-3 py-1 text-sm border border-rule rounded text-ink">{{ $engineInfo['label'] }}</button>
                                    @endforeach
                                    <button wire:click="designWithAll" class="px-3 py-1 text-sm border border-rule rounded text-ink font-bold">All four</button>
                                </div>
                                <button wire:click="buildWholeSite" class="mt-2 block w-full text-center px-4 py-2 border border-rule text-ink font-bold rounded">Build the whole site with AI</button>
                                @php $anyRunning = collect($designs)->contains(fn ($d) => ($d['status'] ?? null) === 'running'); @endphp
                                <ul class="mt-2 text-xs text-ink-2"@if($anyRunning) wire:poll.5s @endif>
                                    @foreach(\App\Modules\X103\Domain\SiteDesignEngines::ENGINES as $engineKey => $engineInfo)
                                        @php $d = $designs[$engineKey] ?? null; @endphp
                                        @if(is_array($d))
                                            <li class="mt-1">
                                                {{ $engineInfo['label'] }}:
                                                @if(($d['status'] ?? null) === 'running')
                                                    designing…
                                                @elseif(($d['status'] ?? null) === 'ready')
                                                    <button wire:click="$set('showDesign', '{{ $engineKey }}')" class="underline text-ink{{ $showDesign === $engineKey ? ' font-bold' : '' }}">show</button>
                                                    · <button wire:click="useDesign('{{ $engineKey }}')" class="underline text-ink">use this design</button>
                                                @else
                                                    did not finish ({{ $d['reason'] ?? 'unknown' }})
                                                @endif
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                                @if($showDesign !== null)
                                    <button wire:click="$set('showDesign', null)" class="mt-2 px-3 py-1 text-sm border border-rule rounded text-ink">Show my current page</button>
                                @endif
                            </div>
                            @endif

                            @if(isset($selectedPage) && !empty($selectedPage->draft_blocks) && !isset($selectedPage->draft_meta['pending_edit']))
                            <div class="mt-4">
                                <p class="text-sm font-bold text-ink mb-1">Try a layout</p>
                                <p class="text-xs text-ink-2 mb-2">Rearranges the sections this page already has. Nothing is added or rewritten.</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach(\App\Modules\X103\Domain\PageLayouts::LAYOUTS as $layoutId => $layout)
                                        <button wire:click="proposeLayout('{{ $layoutId }}')" title="{{ $layout['explanation'] }}" class="px-3 py-1 text-sm border border-rule rounded text-ink">{{ $layout['label'] }}</button>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            @if(isset($selectedPage))
                            <div class="mt-4">
                                <p class="text-sm font-bold text-ink mb-1">Add a section {{ $selectedBlockIndex !== null ? 'below the selected one' : 'at the end' }}</p>
                                <label for="new-about" class="block text-xs text-ink-2 mb-1">About — a paragraph in your words</label>
                                <textarea id="new-about" wire:model="newAboutText" class="w-full border border-rule rounded p-2 text-sm bg-paper text-ink"></textarea>
                                <button wire:click="addSection('about')" class="mt-1 px-3 py-1 text-sm border border-rule rounded text-ink">Add About</button>
                                <label for="new-faq-q" class="block text-xs text-ink-2 mt-3 mb-1">FAQ — a question and its answer</label>
                                <input id="new-faq-q" type="text" wire:model="newFaqQuestion" class="w-full border border-rule rounded p-2 text-sm bg-paper text-ink">
                                <textarea wire:model="newFaqAnswer" aria-label="FAQ answer" class="mt-1 w-full border border-rule rounded p-2 text-sm bg-paper text-ink"></textarea>
                                <button wire:click="addSection('faq')" class="mt-1 px-3 py-1 text-sm border border-rule rounded text-ink">Add FAQ</button>
                            </div>
                            @endif

                            @if(isset($selectedPage) && !empty($selectedPage->draft_meta['undo']) && !isset($selectedPage->draft_meta['pending_edit']))
                            <div class="mt-4 text-right">
                                <button wire:click="undo" class="text-sm text-ink underline">Undo last change</button>
                            </div>
                            @endif
                            <div class="text-center mt-4">
                                <a href="{{ route('x-103.pages', ['edit' => $pageId]) }}" class="text-sm underline text-brand">Open in Pages</a>
                            </div>
                        </div>
                        
                        <h2 class="font-bold mb-2">Inspector</h2>
                        @if($selectedBlockIndex !== null)
                            <p class="text-sm"><span class="font-semibold">Selected:</span> {{ \App\Modules\X103\Domain\SectionNames::label($selectedBlockType) }}</p>
                            <p class="mt-2 text-xs text-ink-2">Click any heading or text in the selected section to type over it. Enter saves, Esc cancels.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button wire:click="arrangeSection('up')" class="px-3 py-1 text-sm border border-rule rounded text-ink">Move up</button>
                                <button wire:click="arrangeSection('down')" class="px-3 py-1 text-sm border border-rule rounded text-ink">Move down</button>
                                <button wire:click="arrangeSection('remove')" wire:confirm="Remove this section? Undo last change brings it back." class="px-3 py-1 text-sm border border-rule rounded text-ink">Remove section</button>
                            </div>
                            <div class="mt-3">
                                <p class="text-sm font-bold text-ink mb-1">AI help for this section</p>
                                <div class="flex flex-wrap gap-2">
                                    <button wire:click="askSection('shorter')" class="px-3 py-1 text-sm border border-rule rounded text-ink">Shorter</button>
                                    <button wire:click="askSection('friendlier')" class="px-3 py-1 text-sm border border-rule rounded text-ink">Friendlier</button>
                                    <button wire:click="askSection('professional')" class="px-3 py-1 text-sm border border-rule rounded text-ink">More professional</button>
                                    <button wire:click="askSection('spelling')" class="px-3 py-1 text-sm border border-rule rounded text-ink">Fix spelling</button>
                                </div>
                                <label for="section-ask" class="block text-xs text-ink-2 mt-2 mb-1">Or say what to change here</label>
                                <input id="section-ask" type="text" wire:model="sectionRequest" class="w-full border border-rule rounded p-2 text-sm bg-paper text-ink" placeholder="e.g. mention we work weekends">
                                <button wire:click="askSection('custom')" class="mt-2 w-full px-4 py-2 bg-ink text-paper font-bold rounded">Ask about this section</button>
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
                    <div class="max-w-md text-center">
                        <p class="text-ink-2">You have no pages yet.</p>
                        <button wire:click="buildWholeSite" class="mt-3 px-4 py-2 bg-ink text-paper font-bold rounded">Build my whole site with AI</button>
                        <p class="mt-2 text-xs text-ink-2">The AI writes Home, Services, About and Contact from your business details and what top local businesses cover — no website needed. Or <a href="{{ route('x-103.site-build') }}" class="underline text-brand">build from your current website</a>.</p>
                        @if($error)
                            <p class="mt-2 text-sm text-attention">{{ $error }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
