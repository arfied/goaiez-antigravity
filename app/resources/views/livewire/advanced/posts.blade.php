<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-ink-2 hover:text-ink hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">GBP Posts & Photos</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Google Business Profile Posts & Job Photos <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Keep your Google profile active with weekly AI keyword posts and automated photo uploads.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button wire:click="publishPost" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                <span wire:loading.remove>Publish to Google Now</span>
                <span wire:loading>Publishing Post...</span>
            </button>
        </div>
    </div>

    @if ($publishNotification)
        <div class="mb-6 p-4 rounded-md bg-ok-bg border border-ok text-ok text-sm flex items-center justify-between">
            <span>{{ $publishNotification }}</span>
            <button wire:click="$set('publishNotification', null)" class="text-ok hover:underline text-xs">Dismiss</button>
        </div>
    @endif

    <!-- Text-to-Post Feature Banner -->
    <div class="bg-card border border-rule rounded-card shadow-card p-6 mb-8 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="h-12 w-12 rounded-full bg-paper border border-rule text-ink flex items-center justify-center text-xl shrink-0">
                📸
            </div>
            <div>
                <h2 class="text-base font-bold text-ink">Text-to-Post Job Photos (Zero-App Workflow)</h2>
                <p class="text-xs text-ink-2 mt-1 max-w-2xl">
                    Technicians in the field can text completed project photos directly to your dedicated number. The AI formats them with local SEO keywords and schedules the GBP update automatically.
                </p>
            </div>
        </div>
        <div class="text-right shrink-0">
            <div class="text-[10px] uppercase font-bold text-ink-2">Dedicated MMS Inbound Line</div>
            <div class="font-mono text-base font-black text-ink mt-0.5">{{ $mmsNumber }}</div>
        </div>
    </div>

    <!-- Main Grid: Composer & History -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Post Composer Card -->
        <div class="bg-card shadow-card rounded-card p-6 border border-rule">
            <h2 class="text-lg font-bold text-ink mb-4">Draft New GBP Update</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-ink-2 mb-1">Post Objective / Topic</label>
                    <select aria-label="Post Topic" wire:model.live="postTopic" class="w-full text-xs p-2.5 rounded-md border border-rule bg-paper text-ink">
                        <option value="project_showcase">Project Showcase / Completed Job</option>
                        <option value="seasonal_promo">Seasonal Service Special</option>
                        <option value="expert_tip">Expert Local Maintenance Tip</option>
                        <option value="client_spotlight">Client Testimonial Highlight</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-ink-2 mb-1">Post Copy & Local Keywords</label>
                    <textarea aria-label="Post Content" wire:model="postContent" rows="5" class="w-full text-xs p-3 rounded-md border border-rule bg-paper text-ink font-sans"></textarea>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-ink-2">
                        <input type="checkbox" wire:model="autoSchedule" class="rounded border-rule text-ink">
                        <span>Include "Book Online" Call-to-Action Link</span>
                    </label>
                    <button wire:click="publishPost" class="px-4 py-2 bg-indigo-600 text-white rounded text-xs font-semibold hover:bg-indigo-700">
                        Schedule Post
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Posts History Card -->
        <div class="bg-card shadow-card rounded-card p-6 border border-rule">
            <h2 class="text-lg font-bold text-ink mb-4">Published Updates & Performance</h2>

            <div class="space-y-4">
                @foreach ($recentPosts as $post)
                    <div class="p-4 rounded-lg border border-rule bg-paper">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-ink">{{ $post['type'] }}</span>
                            <span class="text-[10px] text-ink-2">{{ $post['date'] }}</span>
                        </div>
                        <p class="text-xs text-ink-2 mb-3">{{ $post['summary'] }}</p>
                        <div class="flex items-center gap-4 text-[11px] font-medium text-ink-2">
                            <span>👁️ {{ $post['views'] }} views on Google Maps</span>
                            <span>👆 {{ $post['clicks'] }} clicks</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
