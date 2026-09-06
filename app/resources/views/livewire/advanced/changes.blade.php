<div class="space-y-6 sm:space-y-8">
    <div class="mb-8">
        <nav class="flex mb-2" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                <li class="text-ink-2">/</li>
                <li class="text-ink-2">SEO Changes</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-ink sm:text-3xl">Automated SEO & Schema Changes Log <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
        <p class="mt-1 text-sm text-ink-2">Live feed of automated speed optimizations, JSON-LD rich snippet schema injections, and metadata improvements with instant rollback.</p>
    </div>

    <!-- Changes Table -->
    <div class="bg-card border border-rule rounded-card shadow-card overflow-x-auto overflow-y-hidden">
        <table class="min-w-full divide-y divide-rule text-sm">
            <thead class="bg-paper border border-rule">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase">Change Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase">Affected URL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase">Optimization Detail</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase">Impact Metric</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase">Deployed At</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-2 uppercase">Control</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-rule">
                <tr>
                    <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-paper text-ink-2 border border-rule whitespace-nowrap">Schema JSON-LD</span></td>
                    <td class="px-6 py-4 font-mono text-xs text-ink-2">/services/consulting</td>
                    <td class="px-6 py-4 text-ink">Injected LocalBusiness & AggregateRating schema with review counts</td>
                    <td class="px-6 py-4 text-ink">Eligible for Rich Stars in SERP</td>
                    <td class="px-6 py-4 text-ink-2">2 hours ago</td>
                    <td class="px-6 py-4 text-right"><button class="p-2 min-h-[40px] inline-flex items-center text-xs text-alert hover:underline">Rollback</button></td>
                </tr>
                <tr>
                    <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-paper text-ink-2 border border-rule whitespace-nowrap">Core Web Vitals</span></td>
                    <td class="px-6 py-4 font-mono text-xs text-ink-2">/</td>
                    <td class="px-6 py-4 text-ink">Preloaded critical webfonts and deferred non-essential JavaScript</td>
                    <td class="px-6 py-4 text-ink">LCP reduced by 420ms</td>
                    <td class="px-6 py-4 text-ink-2">1 day ago</td>
                    <td class="px-6 py-4 text-right"><button class="p-2 min-h-[40px] inline-flex items-center text-xs text-alert hover:underline">Rollback</button></td>
                </tr>
                <tr>
                    <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-paper text-ink-2 border border-rule whitespace-nowrap">Meta Tags</span></td>
                    <td class="px-6 py-4 font-mono text-xs text-ink-2">/reviews</td>
                    <td class="px-6 py-4 text-ink">Updated OpenGraph titles & Twitter cards with dynamic 4.9 rating summary</td>
                    <td class="px-6 py-4 text-ink">+14% Social CTR</td>
                    <td class="px-6 py-4 text-ink-2">3 days ago</td>
                    <td class="px-6 py-4 text-right"><button class="p-2 min-h-[40px] inline-flex items-center text-xs text-alert hover:underline">Rollback</button></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>