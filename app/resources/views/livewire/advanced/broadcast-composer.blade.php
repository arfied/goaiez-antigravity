<div class="space-y-6 sm:space-y-8" x-data="{ body: @entangle('body') }">
    <div class="mb-8">
        <nav class="flex mb-2" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                <li class="text-ink-2">/</li>
                <li><a href="{{ route('advanced.broadcasts') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Broadcasts</a></li>
                <li class="text-ink-2">/</li>
                <li class="text-ink-2">Composer</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-ink sm:text-3xl">Compose Customer Broadcast</h1>
        <p class="mt-1 text-sm text-ink-2">Draft targeted SMS or email messages with dynamic merge tags, compliance opt-out, and live credit forecasting.</p>
        @if (session()->has('status'))
            <div class="mt-4 text-green-600 text-sm font-medium">{{ session('status') }}</div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Form Column -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-card border border-rule shadow-card rounded-card p-6">
                <h2 class="text-lg font-semibold text-ink mb-4">Campaign Parameters</h2>
                
                <div class="space-y-4">
                    <div>
                        <label for="campaign_title" class="block text-sm font-medium text-ink-2 mb-1">Campaign Title</label>
                        <input id="campaign_title" type="text" wire:model="title" class="w-full rounded-md border-rule bg-paper text-ink shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border">
                        @error('title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <span class="block text-sm font-medium text-ink-2 mb-1">Channel</span>
                            <p class="text-sm text-ink">Broadcasts go out as text messages on your registered number</p>
                        </div>
                        <div>
                            <span class="block text-sm font-medium text-ink-2 mb-1">Target Audience</span>
                            <p class="text-sm text-ink">Dormant customers: {{ $dormantCount }}</p>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="message_content" class="block text-sm font-medium text-ink-2">Message Content</label>
                        </div>
                        <textarea id="message_content" wire:model="body" rows="4" class="w-full rounded-md border-rule bg-paper text-ink shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm p-3 border font-sans"></textarea>
                        @error('body') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <button type="button" @click="$wire.set('body', ($wire.body || '') + '{name}')" class="px-2 py-1 bg-paper text-ink-2 border border-rule rounded hover:bg-rule">+ Name</button>
                            <button type="button" @click="$wire.set('body', ($wire.body || '') + '{link}')" class="px-2 py-1 bg-paper text-ink-2 border border-rule rounded hover:bg-rule">+ Link</button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-rule flex flex-col sm:flex-row sm:justify-between items-start sm:items-center gap-4">
                    <div class="text-xs text-ink-2">
                        Saving creates a draft. This screen sends nothing.
                    </div>
                    <button type="button" wire:click="saveDraft" class="inline-flex items-center whitespace-nowrap px-5 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none">
                        Save draft
                    </button>
                </div>
            </div>
        </div>

        <!-- Live Phone Preview Column -->
        <div class="space-y-6">
            <div class="bg-card border border-rule shadow-card rounded-card p-6">
                <h3 class="text-sm font-semibold text-ink uppercase tracking-wider mb-4">Live Recipient Preview</h3>
                
                <!-- Phone Mockup Frame -->
                <div class="w-full max-w-[280px] mx-auto bg-gray-900 rounded-[2.5rem] p-3 shadow-xl border-4 border-gray-800">
                    <div class="bg-white rounded-[2rem] p-4 min-h-[380px] flex flex-col justify-between">
                        <!-- Phone Header -->
                        <div class="text-center pb-2 border-b border-gray-100">
                            <div class="text-xs font-semibold text-gray-800">Verified Business SMS</div>
                            <div class="text-[10px] text-gray-600">10DLC Shortcode</div>
                        </div>

                        <!-- Bubble -->
                        <div class="my-auto space-y-2">
                            <div class="bg-indigo-600 text-white rounded-2xl rounded-tr-sm p-3 text-xs shadow-sm leading-relaxed" x-text="body">
                            </div>
                        </div>

                        <!-- Phone Footer Input -->
                        <div class="pt-2 border-t border-gray-100 flex items-center gap-1">
                            <div class="h-6 flex-1 bg-gray-100 rounded-full px-2 text-[10px] text-gray-600 flex items-center">iMessage / SMS</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
