<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sample-bg text-sample">
                    Advanced Mode
                </span>
                <h1 class="font-display text-2xl font-bold leading-7 text-ink sm:text-3xl">
                    Power Control Center
                </h1>
            </div>
            <p class="mt-1 text-sm text-ink-2">
                Detailed controls, direct broadcast access, NAP citation manager, geo-grid rank maps, and AI voice operations.
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 gap-3">
            <a href="{{ route('advanced.rank-tracker') }}" class="inline-flex items-center px-4 py-2 border border-rule rounded-md shadow-sm text-sm font-medium text-ink bg-card hover:bg-paper">
                Geo-Grid Heatmap
            </a>
            <a href="{{ route('advanced.broadcasts.compose') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                New Broadcast
            </a>
        </div>
    </div>

    <!-- Power Stats Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <div class="bg-card overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-rule">
            <p class="text-sm font-medium text-ink-2 truncate">Citation Health</p>
            <p class="mt-1 text-3xl font-semibold text-ink">
                {{ $citationsCount > 0 ? round(($consistentCount / $citationsCount) * 100) : 0 }}%
            </p>
            <div class="mt-2 text-xs text-ink-2">
                {{ $consistentCount }} / {{ $citationsCount }} Consistent Listings
            </div>
        </div>

        <div class="bg-card overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-rule">
            <p class="text-sm font-medium text-ink-2 truncate">Local Map 3-Pack</p>
            <p class="mt-1 text-3xl font-semibold text-ink">
                #1.6 Avg
            </p>
            <div class="mt-2 text-xs text-ink-2">
                89% top 3 dominance across 5mi catchment
            </div>
        </div>

        <div class="bg-card overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-rule">
            <p class="text-sm font-medium text-ink-2 truncate">AI Voice Receptionist</p>
            <p class="mt-1 text-3xl font-semibold text-ink">Active</p>
            <div class="mt-2 text-xs text-ok font-medium">24/7 Autonomous Triage</div>
        </div>

        <div class="bg-card overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-rule">
            <p class="text-sm font-medium text-ink-2 truncate">POS Review Webhooks</p>
            <p class="mt-1 text-3xl font-semibold text-ink">Connected</p>
            <div class="mt-2 text-xs text-ink-2">Stripe & Invoicing Active</div>
        </div>
    </div>

    <!-- Quick Navigation to Advanced Sections -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="{{ route('advanced.rank-tracker') }}" class="block p-6 bg-card border-2 border-rule rounded-lg hover:border-indigo-500 transition shadow-xs">
            <div class="text-ink font-semibold text-lg mb-2 flex items-center justify-between">
                <span>📍 Geo-Grid Rank Tracker</span>
                <span class="text-[10px] uppercase font-bold bg-sample-bg text-sample px-2 py-0.5 rounded-full">New</span>
            </div>
            <p class="text-sm text-ink-2">Interactive 3x3 / 5x5 pin heatmap showing exact Google Maps 3-Pack rankings across your catchment area radius.</p>
        </a>

        <a href="{{ route('advanced.integrations') }}" class="block p-6 bg-card border-2 border-rule rounded-lg hover:border-indigo-500 transition shadow-xs">
            <div class="text-ink font-semibold text-lg mb-2 flex items-center justify-between">
                <span>⚡ POS & Invoicing Triggers</span>
                <span class="text-[10px] uppercase font-bold bg-sample-bg text-sample px-2 py-0.5 rounded-full">New</span>
            </div>
            <p class="text-sm text-ink-2">Connect Stripe, QuickBooks, and Square to automatically dispatch review requests within minutes of customer invoice settlement.</p>
        </a>

        <a href="{{ route('advanced.posts') }}" class="block p-6 bg-card border-2 border-rule rounded-lg hover:border-indigo-500 transition shadow-xs">
            <div class="text-ink font-semibold text-lg mb-2 flex items-center justify-between">
                <span>📸 GBP Posts & Photo Sweeps</span>
                <span class="text-[10px] uppercase font-bold bg-sample-bg text-sample px-2 py-0.5 rounded-full">New</span>
            </div>
            <p class="text-sm text-ink-2">Weekly automated GBP updates with local keywords, and zero-app MMS text-to-post photo uploading from the job site.</p>
        </a>

        <a href="{{ route('advanced.voice') }}" class="block p-6 bg-card border-2 border-rule rounded-lg hover:border-indigo-500 transition shadow-xs">
            <div class="text-ink font-semibold text-lg mb-2 flex items-center justify-between">
                <span>🎙️ AI Voice Receptionist</span>
                <span class="text-[10px] uppercase font-bold bg-sample-bg text-sample px-2 py-0.5 rounded-full">New</span>
            </div>
            <p class="text-sm text-ink-2">24/7 conversational voice call answering, emergency forwarding to owner mobile, and automated appointment calendar links.</p>
        </a>

        <a href="{{ route('x-103.pages') }}" class="block p-6 bg-card border-2 border-rule rounded-lg hover:border-indigo-500 transition shadow-xs">
            <div class="text-ink font-semibold text-lg mb-2 flex items-center justify-between">
                <span>🌐 Visual Website & Funnel Builder</span>
                <span class="text-[10px] uppercase font-bold bg-sample-bg text-sample px-2 py-0.5 rounded-full">New</span>
            </div>
            <p class="text-sm text-ink-2">Design and launch high-converting local service landing pages with auto-injected schema SEO, live reviews, and mobile preview.</p>
        </a>

        <a href="{{ route('advanced.citations') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg mb-2">📍 NAP Citations & Directories</div>
            <p class="text-sm text-ink-2">Track and fix business name, address, and phone number consistency across Google, Apple Maps, Bing, Yelp, and BBB.</p>
        </a>

        <a href="{{ route('advanced.broadcasts') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg mb-2">📢 Direct Broadcasts</div>
            <p class="text-sm text-ink-2">Compose targeted SMS/email blasts to verified opt-in customer segments with character and credit forecasting.</p>
        </a>

        <a href="{{ route('advanced.competitors') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg mb-2">🎯 Competitor Radar</div>
            <p class="text-sm text-ink-2">Monitor nearby rivals, compare review velocity, rating trajectories, and Google search ranking changes.</p>
        </a>

        <a href="{{ route('advanced.visibility') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg mb-2">🔍 Search Visibility & GSC</div>
            <p class="text-sm text-ink-2">Deep Google Search Console query metrics, average position changes, and click-through breakdowns.</p>
        </a>

        <a href="{{ route('advanced.defense') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg mb-2">🛡️ Reputation Defense</div>
            <p class="text-sm text-ink-2">Configure auto-escalation thresholds for negative feedback, instant alerts, and win-back sequences.</p>
        </a>
    </div>
</div>