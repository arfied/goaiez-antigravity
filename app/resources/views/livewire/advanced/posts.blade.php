<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">GBP Posts & Photos</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Google Business Profile Posts & Job Photos <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Keep your Google profile active with weekly AI keyword posts and automated photo uploads.</p>
        </div>
    </div>

    <div class="bg-card border border-rule rounded-card shadow-card p-6 mb-8">
        <p class="text-sm text-ink-2">
            Posting to Google Business Profile waits on the GBP grant. Pages published here are live on your site.
        </p>
    </div>

    <div class="bg-card shadow-card rounded-card p-6 border border-rule">
        <h2 class="text-lg font-bold text-ink mb-4">Published Updates & Performance</h2>

        <div class="space-y-4">
            @if($rows->isEmpty())
                <p class="text-sm text-ink-2">No pages published yet.</p>
            @else
                @foreach ($rows as $page)
                    <div class="p-4 rounded-lg border border-rule bg-paper">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-ink">{{ $page->title }}</span>
                            <span class="text-[10px] text-ink-2">{{ $page->published_at?->format('M j, Y') ?? 'Not published' }}</span>
                        </div>
                        <p class="text-xs text-ink-2 mb-3">Status: {{ $page->status->value }}</p>
                        @if($page->status->value === 'published' && $websiteUrl)
                            <div class="flex items-center gap-4 text-[11px] font-medium text-ink-2">
                                <a href="{{ rtrim($websiteUrl, '/') . '/' . ltrim($page->slug, '/') }}" class="text-indigo-400 hover:underline break-all" target="_blank" rel="noopener noreferrer">
                                    {{ rtrim($websiteUrl, '/') . '/' . ltrim($page->slug, '/') }}
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
