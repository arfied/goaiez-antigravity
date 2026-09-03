<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-ink-2 hover:text-ink hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Broadcasts</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Marketing & Re-engagement Broadcasts <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Send compliant, 10DLC-registered SMS and email announcements to segmented customer lists.</p>
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
        <div class="bg-card p-5 rounded-card border border-rule shadow-card">
            <div class="text-sm font-medium text-ink-2">Total Sent</div>
            <div class="mt-1 text-3xl font-bold text-ink">1,248</div>
            <div class="mt-1 text-xs text-ok font-medium">99.2% Delivery Rate</div>
        </div>
        <div class="bg-card p-5 rounded-card border border-rule shadow-card">
            <div class="text-sm font-medium text-ink-2">Avg Click-Through</div>
            <div class="mt-1 text-3xl font-bold text-ink">38.4%</div>
            <div class="mt-1 text-xs text-ink-2">+12% vs industry avg</div>
        </div>
        <div class="bg-card p-5 rounded-card border border-rule shadow-card">
            <div class="text-sm font-medium text-ink-2">Reviews Generated</div>
            <div class="mt-1 text-3xl font-bold text-ink">+84</div>
            <div class="mt-1 text-xs text-ink-2">Direct from broadcast links</div>
        </div>
        <div class="bg-card p-5 rounded-card border border-rule shadow-card">
            <div class="text-sm font-medium text-ink-2">Carrier Reputation</div>
            <div class="mt-1 text-3xl font-bold text-ink">High</div>
            <div class="mt-1 text-xs text-ok font-medium">10DLC Campaign Active</div>
        </div>
    </div>

    <!-- Campaigns List -->
    <div class="bg-card shadow-card rounded-card border border-rule overflow-x-auto overflow-y-hidden">
        <div class="px-6 py-4 border-b border-rule flex justify-between items-center">
            <h2 class="text-lg font-semibold text-ink">Recent Broadcasts</h2>
            <span class="text-xs text-ink-2">Updated automatically</span>
        </div>
        <div class="overflow-x-auto" tabindex="0" aria-label="Recent broadcasts table">
        <table class="min-w-full divide-y divide-rule">
            <thead class="bg-paper">
                <tr>
                    <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Campaign Name</th>
                    <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Channel</th>
                    <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Audience</th>
                    <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Delivery</th>
                    <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Date Sent</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-rule text-sm">
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap font-medium text-ink">Spring Review & Feedback Drive</td>
                    <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-paper text-ink-2 border border-rule">SMS</span></td>
                    <td class="px-6 py-4 whitespace-nowrap text-ink-2">All Recent Customers (300)</td>
                    <td class="px-6 py-4 whitespace-nowrap text-ink-2">298 / 300 (99.3%)</td>
                    <td class="px-6 py-4 whitespace-nowrap"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-ok-bg text-ok">Completed</span></td>
                    <td class="px-6 py-4 whitespace-nowrap text-ink-2">3 days ago</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap font-medium text-ink">Loyalty VIP Special Offer</td>
                    <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-paper text-ink-2 border border-rule">SMS + Email</span></td>
                    <td class="px-6 py-4 whitespace-nowrap text-ink-2">5-Star Reviewers (142)</td>
                    <td class="px-6 py-4 whitespace-nowrap text-ink-2">142 / 142 (100%)</td>
                    <td class="px-6 py-4 whitespace-nowrap"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-ok-bg text-ok">Completed</span></td>
                    <td class="px-6 py-4 whitespace-nowrap text-ink-2">1 week ago</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap font-medium text-ink">Service Check-in & Autopilot Ask</td>
                    <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-paper text-ink-2 border border-rule">SMS</span></td>
                    <td class="px-6 py-4 whitespace-nowrap text-ink-2">Post-Visit Cadence (88)</td>
                    <td class="px-6 py-4 whitespace-nowrap text-ink-2">88 / 88 (100%)</td>
                    <td class="px-6 py-4 whitespace-nowrap"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-ok-bg text-ok">Completed</span></td>
                    <td class="px-6 py-4 whitespace-nowrap text-ink-2">2 weeks ago</td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
</div>