<div>
    <div class="flex h-full min-h-[80vh]"
         x-data
         x-on:message.window="
            if ($event.data.source === 'studio-canvas') {
                $wire.selectBlock($event.data.index);
            }
         ">
        <!-- Left Rail: Pages -->
        <div class="w-64 border-r border-rule p-4 overflow-y-auto">
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
        <div class="flex-1 p-4">
            @if($pageId)
                <div class="flex gap-4 h-full">
                    <!-- Canvas -->
                    <div class="flex-1">
                        <iframe title="Site preview"
                                sandbox="allow-scripts"
                                srcdoc="{{ $previewHtml }}"
                                class="w-full min-h-[70vh] border border-rule "
                                id="studio-canvas"></iframe>
                    </div>

                    <!-- Inspector -->
                    <div class="w-64 border border-rule p-4 rounded bg-paper flex flex-col">
                        <div class="mb-4">
                            @if(isset($selectedPage) && isset($selectedPage->draft_meta['pending_edit']))
                                <div class="mb-2 p-2 bg-yellow-100 text-yellow-800 text-sm font-bold rounded">
                                    Previewing AI proposal
                                </div>
                            @endif
                            @if($error)
                                <div class="mb-2 p-2 bg-red-100 text-red-800 text-sm rounded">{{ $error }}</div>
                            @endif
                            @if($success)
                                <div class="mb-2 p-2 bg-green-100 text-green-800 text-sm rounded">{{ $success }}</div>
                            @endif

                            @if(empty($selectedPage->draft_blocks))
                                <a href="{{ route('x-103.site-build') }}" class="block w-full text-center px-4 py-2 bg-brand text-white font-bold rounded mb-2">Build my site</a>
                            @else
                                <button wire:click="publish({{ $pageId }})" class="block w-full text-center px-4 py-2 bg-brand text-white font-bold rounded mb-2">Publish draft</button>
                            @endif
                            <div class="text-center">
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
