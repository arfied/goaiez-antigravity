<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-500 dark:text-gray-400">Competitors</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Local Competitor Intelligence Radar <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Track competitor review velocity, rating momentum, and search rankings in your catchment area.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                + Track New Competitor
            </button>
        </div>
    </div>

    <!-- Competitor Radar Table -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Business</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Google Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Reviews</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Monthly Velocity</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Catchment Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trajectory</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <tr class="bg-indigo-50/40 dark:bg-indigo-950/20 font-medium">
                    <td class="px-6 py-4 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                        <div class="text-gray-900 dark:text-white">Your Business (Rachel Taylor)</div>
                    </td>
                    <td class="px-6 py-4 text-emerald-600 font-bold">4.9 ★</td>
                    <td class="px-6 py-4 text-gray-900 dark:text-white">186</td>
                    <td class="px-6 py-4 text-emerald-600 font-semibold">+18 / mo</td>
                    <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300">#1 in Area</span></td>
                    <td class="px-6 py-4 text-emerald-600 font-semibold">▲ Accelerating</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 text-gray-900 dark:text-white">Apex Local Services</td>
                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">4.7 ★</td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">142</td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">+6 / mo</td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">#2 in Area</td>
                    <td class="px-6 py-4 text-gray-500">▶ Steady</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 text-gray-900 dark:text-white">Metro Pro Solutions</td>
                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">4.3 ★</td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">98</td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">+2 / mo</td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">#4 in Area</td>
                    <td class="px-6 py-4 text-rose-600">▼ Declining</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>