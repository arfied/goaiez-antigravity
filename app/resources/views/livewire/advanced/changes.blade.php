<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <nav class="flex mb-2" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                <li class="text-gray-400">/</li>
                <li class="text-gray-400 dark:text-gray-400">SEO Changes</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Automated SEO & Schema Changes Log <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Preview — not live</span></h1>
        <p class="mt-1 text-sm text-gray-400 dark:text-gray-400">Live feed of automated speed optimizations, JSON-LD rich snippet schema injections, and metadata improvements with instant rollback.</p>
    </div>

    <!-- Changes Table -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase">Change Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase">Affected URL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase">Optimization Detail</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase">Impact Metric</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase">Deployed At</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 dark:text-gray-400 uppercase">Control</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr>
                    <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300">Schema JSON-LD</span></td>
                    <td class="px-6 py-4 font-mono text-xs text-gray-600 dark:text-gray-300">/services/consulting</td>
                    <td class="px-6 py-4 text-gray-900 dark:text-white">Injected LocalBusiness & AggregateRating schema with review counts</td>
                    <td class="px-6 py-4 text-emerald-400 font-semibold">Eligible for Rich Stars in SERP</td>
                    <td class="px-6 py-4 text-gray-400">2 hours ago</td>
                    <td class="px-6 py-4 text-right"><button class="text-xs text-rose-600 dark:text-rose-400 hover:underline">Rollback</button></td>
                </tr>
                <tr>
                    <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Core Web Vitals</span></td>
                    <td class="px-6 py-4 font-mono text-xs text-gray-600 dark:text-gray-300">/</td>
                    <td class="px-6 py-4 text-gray-900 dark:text-white">Preloaded critical webfonts and deferred non-essential JavaScript</td>
                    <td class="px-6 py-4 text-emerald-400 font-semibold">LCP reduced by 420ms</td>
                    <td class="px-6 py-4 text-gray-400">1 day ago</td>
                    <td class="px-6 py-4 text-right"><button class="text-xs text-rose-600 dark:text-rose-400 hover:underline">Rollback</button></td>
                </tr>
                <tr>
                    <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300">Meta Tags</span></td>
                    <td class="px-6 py-4 font-mono text-xs text-gray-600 dark:text-gray-300">/reviews</td>
                    <td class="px-6 py-4 text-gray-900 dark:text-white">Updated OpenGraph titles & Twitter cards with dynamic 4.9 rating summary</td>
                    <td class="px-6 py-4 text-emerald-400 font-semibold">+14% Social CTR</td>
                    <td class="px-6 py-4 text-gray-400">3 days ago</td>
                    <td class="px-6 py-4 text-right"><button class="text-xs text-rose-600 dark:text-rose-400 hover:underline">Rollback</button></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>