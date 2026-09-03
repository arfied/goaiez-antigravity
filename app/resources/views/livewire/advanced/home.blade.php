<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300">
                    Advanced Mode
                </span>
                <h1 class="font-display text-2xl font-bold leading-7 text-ink sm:text-3xl">
                    Power Control Center
                </h1>
            </div>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-400">
                Detailed controls, direct broadcast access, NAP citation manager, geo-grid rank maps, and AI voice operations.
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 gap-3">
            <a href="{{ route('advanced.rank-tracker') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                Geo-Grid Heatmap
            </a>
            <a href="{{ route('advanced.broadcasts.compose') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                New Broadcast
            </a>
        </div>
    </div>

    <!-- Power Stats Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <div class="bg-white dark:bg-gray-800 overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-gray-200 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-400 dark:text-gray-400 truncate">Citation Health</p>
            <p class="mt-1 text-3xl font-semibold text-ink">
                {{ $citationsCount > 0 ? round(($consistentCount / $citationsCount) * 100) : 0 }}%
            </p>
            <div class="mt-2 text-xs text-gray-400 dark:text-gray-400">
                {{ $consistentCount }} / {{ $citationsCount }} Consistent Listings
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-gray-200 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-400 dark:text-gray-400 truncate">Local Map 3-Pack</p>
            <p class="mt-1 text-3xl font-semibold text-emerald-400">
                #1.6 Avg
            </p>
            <div class="mt-2 text-xs text-gray-400 dark:text-gray-400">
                89% top 3 dominance across 5mi catchment
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-gray-200 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-400 dark:text-gray-400 truncate">AI Voice Receptionist</p>
            <p class="mt-1 text-3xl font-semibold text-indigo-400">Active</p>
            <div class="mt-2 text-xs text-emerald-400 font-medium">24/7 Autonomous Triage</div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-gray-200 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-400 dark:text-gray-400 truncate">POS Review Webhooks</p>
            <p class="mt-1 text-3xl font-semibold text-ink">Connected</p>
            <div class="mt-2 text-xs text-gray-400 dark:text-gray-400">Stripe & Invoicing Active</div>
        </div>
    </div>

    <!-- Quick Navigation to Advanced Sections -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="{{ route('advanced.rank-tracker') }}" class="block p-6 bg-white dark:bg-gray-800 border-2 border-indigo-200 dark:border-indigo-800 rounded-lg hover:border-indigo-500 transition shadow-xs">
            <div class="text-indigo-400 font-semibold text-lg mb-2 flex items-center justify-between">
                <span>📍 Geo-Grid Rank Tracker</span>
                <span class="text-[10px] uppercase font-bold bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 px-2 py-0.5 rounded-full">New</span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Interactive 3x3 / 5x5 pin heatmap showing exact Google Maps 3-Pack rankings across your catchment area radius.</p>
        </a>

        <a href="{{ route('advanced.integrations') }}" class="block p-6 bg-white dark:bg-gray-800 border-2 border-indigo-200 dark:border-indigo-800 rounded-lg hover:border-indigo-500 transition shadow-xs">
            <div class="text-indigo-400 font-semibold text-lg mb-2 flex items-center justify-between">
                <span>⚡ POS & Invoicing Triggers</span>
                <span class="text-[10px] uppercase font-bold bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 px-2 py-0.5 rounded-full">New</span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Connect Stripe, QuickBooks, and Square to automatically dispatch review requests within minutes of customer invoice settlement.</p>
        </a>

        <a href="{{ route('advanced.posts') }}" class="block p-6 bg-white dark:bg-gray-800 border-2 border-indigo-200 dark:border-indigo-800 rounded-lg hover:border-indigo-500 transition shadow-xs">
            <div class="text-indigo-400 font-semibold text-lg mb-2 flex items-center justify-between">
                <span>📸 GBP Posts & Photo Sweeps</span>
                <span class="text-[10px] uppercase font-bold bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 px-2 py-0.5 rounded-full">New</span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Weekly automated GBP updates with local keywords, and zero-app MMS text-to-post photo uploading from the job site.</p>
        </a>

        <a href="{{ route('advanced.voice') }}" class="block p-6 bg-white dark:bg-gray-800 border-2 border-indigo-200 dark:border-indigo-800 rounded-lg hover:border-indigo-500 transition shadow-xs">
            <div class="text-indigo-400 font-semibold text-lg mb-2 flex items-center justify-between">
                <span>🎙️ AI Voice Receptionist</span>
                <span class="text-[10px] uppercase font-bold bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 px-2 py-0.5 rounded-full">New</span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300">24/7 conversational voice call answering, emergency forwarding to owner mobile, and automated appointment calendar links.</p>
        </a>

        <a href="{{ route('advanced.website-builder') }}" class="block p-6 bg-white dark:bg-gray-800 border-2 border-indigo-200 dark:border-indigo-800 rounded-lg hover:border-indigo-500 transition shadow-xs">
            <div class="text-indigo-400 font-semibold text-lg mb-2 flex items-center justify-between">
                <span>🌐 Visual Website & Funnel Builder</span>
                <span class="text-[10px] uppercase font-bold bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 px-2 py-0.5 rounded-full">New</span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Design and launch high-converting local service landing pages with auto-injected schema SEO, live reviews, and mobile preview.</p>
        </a>

        <a href="{{ route('advanced.citations') }}" class="block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 transition">
            <div class="text-indigo-400 font-semibold text-lg mb-2">📍 NAP Citations & Directories</div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Track and fix business name, address, and phone number consistency across Google, Apple Maps, Bing, Yelp, and BBB.</p>
        </a>

        <a href="{{ route('advanced.broadcasts') }}" class="block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 transition">
            <div class="text-indigo-400 font-semibold text-lg mb-2">📢 Direct Broadcasts</div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Compose targeted SMS/email blasts to verified opt-in customer segments with character and credit forecasting.</p>
        </a>

        <a href="{{ route('advanced.competitors') }}" class="block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 transition">
            <div class="text-indigo-400 font-semibold text-lg mb-2">🎯 Competitor Radar</div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Monitor nearby rivals, compare review velocity, rating trajectories, and Google search ranking changes.</p>
        </a>

        <a href="{{ route('advanced.visibility') }}" class="block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 transition">
            <div class="text-indigo-400 font-semibold text-lg mb-2">🔍 Search Visibility & GSC</div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Deep Google Search Console query metrics, average position changes, and click-through breakdowns.</p>
        </a>

        <a href="{{ route('advanced.defense') }}" class="block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 transition">
            <div class="text-indigo-400 font-semibold text-lg mb-2">🛡️ Reputation Defense</div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Configure auto-escalation thresholds for negative feedback, instant alerts, and win-back sequences.</p>
        </a>
    </div>
</div>