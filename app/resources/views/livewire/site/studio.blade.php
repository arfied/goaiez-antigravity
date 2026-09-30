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
                                class="w-full text-left px-2 py-1 text-sm rounded hover:bg-paper-2 {{ $pageId === $page->id ? 'font-bold bg-paper-2' : '' }}">
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
                    <!-- Chat Placeholder -->
                    <div class="w-64 border border-rule p-4 rounded bg-paper">
                        <h2 class="font-bold mb-2">Chat</h2>
                        <p class="text-sm text-ink-2">AI chat will live here in a later wave.</p>
                    </div>

                    <!-- Canvas -->
                    <div class="flex-1">
                        <iframe title="Site preview"
                                sandbox="allow-scripts"
                                srcdoc="{{ $previewHtml }}"
                                class="w-full min-h-[70vh] border border-rule "
                                id="studio-canvas"></iframe>
                    </div>

                    <!-- Inspector -->
                    <div class="w-64 border border-rule p-4 rounded bg-paper">
                        <h2 class="font-bold mb-2">Inspector</h2>
                        @if($selectedBlockIndex !== null)
                            <div class="text-sm">
                                <p><span class="font-semibold">Block Index:</span> {{ $selectedBlockIndex }}</p>
                                @php
                                    $selectedBlockType = null;
                                    if ($selectedPage && is_array($selectedPage->draft_blocks)) {
                                        // The blocks array might be sequential or keyed, 
                                        // so we should look for the block at the given index.
                                        // Usually draft_blocks is a sequential array.
                                        if (isset($selectedPage->draft_blocks[$selectedBlockIndex])) {
                                            $selectedBlockType = $selectedPage->draft_blocks[$selectedBlockIndex]['type'] ?? 'unknown';
                                        }
                                    }
                                @endphp
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
                    <p class="text-ink-2">Select a page to edit.</p>
                </div>
            @endif
        </div>
    </div>
</div>
