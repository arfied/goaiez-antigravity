<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-400 dark:text-gray-400">Customer Segments</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Smart Customer Segments & Audiences <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-400">Dynamic groups computed from customer interactions, review status, feedback sentiment, and cadence recency.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                + Create Custom Segment
            </button>
        </div>
    </div>

    <!-- Segments Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">High Review Propensity</span>
                    <span class="text-xs text-gray-400">Dynamic</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Recent Satisfied Visitors</h3>
                <p class="text-xs text-gray-400 dark:text-gray-400 mt-1">Customers with completed service in the last 14 days with zero complaints.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">86 <span class="text-xs text-gray-400 font-normal">contacts</span></div>
                <a href="{{ route('advanced.broadcasts.compose') }}" class="text-xs font-semibold text-indigo-400 dark:text-indigo-400 hover:underline">Message Segment &rarr;</a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300">VIP Advocates</span>
                    <span class="text-xs text-gray-400">Dynamic</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">5-Star Google Reviewers</h3>
                <p class="text-xs text-gray-400 dark:text-gray-400 mt-1">Confirmed 5-star public reviewers eligible for loyalty rewards and referral programs.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">142 <span class="text-xs text-gray-400 font-normal">contacts</span></div>
                <a href="{{ route('advanced.broadcasts.compose') }}" class="text-xs font-semibold text-indigo-400 dark:text-indigo-400 hover:underline">Message Segment &rarr;</a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">Re-engagement</span>
                    <span class="text-xs text-gray-400">Dynamic</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Lapsed Customers (60+ Days)</h3>
                <p class="text-xs text-gray-400 dark:text-gray-400 mt-1">Prior clients who have not visited recently. Perfect for seasonal win-back offers.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">112 <span class="text-xs text-gray-400 font-normal">contacts</span></div>
                <a href="{{ route('advanced.broadcasts.compose') }}" class="text-xs font-semibold text-indigo-400 dark:text-indigo-400 hover:underline">Message Segment &rarr;</a>
            </div>
        </div>
    </div>
</div>