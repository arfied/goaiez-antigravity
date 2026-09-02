<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-400 dark:text-gray-400">Broadcasts</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Marketing & Re-engagement Broadcasts <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-400">Send compliant, 10DLC-registered SMS and email announcements to segmented customer lists.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="{{ route('advanced.broadcasts.compose') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Broadcast
            </a>
        </div>
    </div>

    <!-- Metrics Bar -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-4 mb-8">
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-400 dark:text-gray-400">Total Sent</div>
            <div class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">1,248</div>
            <div class="mt-1 text-xs text-emerald-400 font-medium">99.2% Delivery Rate</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-400 dark:text-gray-400">Avg Click-Through</div>
            <div class="mt-1 text-3xl font-bold text-indigo-400">38.4%</div>
            <div class="mt-1 text-xs text-gray-400 dark:text-gray-400">+12% vs industry avg</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-400 dark:text-gray-400">Reviews Generated</div>
            <div class="mt-1 text-3xl font-bold text-emerald-400">+84</div>
            <div class="mt-1 text-xs text-gray-400 dark:text-gray-400">Direct from broadcast links</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-400 dark:text-gray-400">Carrier Reputation</div>
            <div class="mt-1 text-3xl font-bold text-emerald-400">High</div>
            <div class="mt-1 text-xs text-emerald-400 font-medium">10DLC Campaign Active</div>
        </div>
    </div>

    <!-- Campaigns List -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Broadcasts</h2>
            <span class="text-xs text-gray-400 dark:text-gray-400">Updated automatically</span>
        </div>
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase tracking-wider">Campaign Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase tracking-wider">Channel</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase tracking-wider">Audience</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase tracking-wider">Delivery</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-gray-400 uppercase tracking-wider">Date Sent</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <tr>
                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">Spring Review & Feedback Drive</td>
                    <td class="px-6 py-4"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300">SMS</span></td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">All Recent Customers (300)</td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">298 / 300 (99.3%)</td>
                    <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Completed</span></td>
                    <td class="px-6 py-4 text-gray-400 dark:text-gray-400">3 days ago</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">Loyalty VIP Special Offer</td>
                    <td class="px-6 py-4"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300">SMS + Email</span></td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">5-Star Reviewers (142)</td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">142 / 142 (100%)</td>
                    <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Completed</span></td>
                    <td class="px-6 py-4 text-gray-400 dark:text-gray-400">1 week ago</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">Service Check-in & Autopilot Ask</td>
                    <td class="px-6 py-4"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300">SMS</span></td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">Post-Visit Cadence (88)</td>
                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">88 / 88 (100%)</td>
                    <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Completed</span></td>
                    <td class="px-6 py-4 text-gray-400 dark:text-gray-400">2 weeks ago</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>