<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-400 dark:text-gray-400">Executive Reports</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Executive ROI & Reputation Reports <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-400">Comprehensive review volume breakdown, customer sentiment analysis, search attribution, and downloadable audit reports.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 gap-3">
            <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                Download PDF Report
            </button>
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                Export Raw CSV
            </button>
        </div>
    </div>

    <!-- Executive Summary Card -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700 mb-8">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Monthly Performance Summary (August 2026)</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="border-l-4 border-emerald-500 pl-4">
                <div class="text-xs text-gray-400 uppercase font-semibold">New Reviews Gained</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">+28 Reviews</div>
                <div class="text-xs text-emerald-400 mt-1">100% 5-Star Average</div>
            </div>
            <div class="border-l-4 border-indigo-500 pl-4">
                <div class="text-xs text-gray-400 uppercase font-semibold">Estimated Revenue Impact</div>
                <div class="text-2xl font-bold text-indigo-400 mt-1">$14,200</div>
                <div class="text-xs text-gray-400 mt-1">Based on local conversion uplift</div>
            </div>
            <div class="border-l-4 border-purple-500 pl-4">
                <div class="text-xs text-gray-400 uppercase font-semibold">AI Autopilot Replies</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">28 / 28</div>
                <div class="text-xs text-emerald-400 mt-1">100% Response Rate</div>
            </div>
            <div class="border-l-4 border-blue-500 pl-4">
                <div class="text-xs text-gray-400 uppercase font-semibold">Citations Consistent</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">94%</div>
                <div class="text-xs text-emerald-400 mt-1">Top local SEO health</div>
            </div>
        </div>
    </div>
</div>