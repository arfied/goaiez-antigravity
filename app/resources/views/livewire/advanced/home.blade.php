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
                {{ $consistentCount }} / 
                {{ $citationsCount }} Consistent Listings
            </div>
        </div>

        <a href="{{ route('advanced.posts') }}" class="block bg-card overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-rule hover:border-indigo-500 transition">
            <p class="text-sm font-medium text-ink-2 truncate">Published pages</p>
            <p class="mt-1 text-3xl font-semibold text-ink">
                {{ $pagesCount }}
            </p>
        </a>

        <a href="{{ route('advanced.voice') }}" class="block bg-card overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-rule hover:border-indigo-500 transition">
            <p class="text-sm font-medium text-ink-2 truncate">Calls, last 30 days</p>
            <p class="mt-1 text-3xl font-semibold text-ink">
                {{ $callsCount }}
            </p>
        </a>

        <a href="{{ route('advanced.broadcasts') }}" class="block bg-card overflow-x-auto overflow-y-hidden shadow rounded-lg p-5 border border-rule hover:border-indigo-500 transition">
            <p class="text-sm font-medium text-ink-2 truncate">Broadcasts</p>
            <p class="mt-1 text-3xl font-semibold text-ink">
                {{ $campaignsCount }}
            </p>
        </a>
    </div>

    <!-- Quick Navigation to Advanced Sections -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="{{ route('advanced.rank-tracker') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg">📍 Geo-Grid Rank Tracker</div>
        </a>

        <a href="{{ route('advanced.integrations') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg">⚡ POS & Invoicing Triggers</div>
        </a>

        <a href="{{ route('advanced.posts') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg">📸 GBP Posts & Photo Sweeps</div>
        </a>

        <a href="{{ route('advanced.voice') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg">🎙️ AI Voice Receptionist</div>
        </a>

        <a href="{{ route('x-103.pages') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg">🌐 Visual Website & Funnel Builder</div>
        </a>

        <a href="{{ route('advanced.citations') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg">📍 NAP Citations & Directories</div>
        </a>

        <a href="{{ route('advanced.broadcasts') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg">📢 Direct Broadcasts</div>
        </a>

        <a href="{{ route('advanced.competitors') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg">🎯 Competitor Radar</div>
        </a>

        <a href="{{ route('advanced.visibility') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg">🔍 Search Visibility & GSC</div>
        </a>

        <a href="{{ route('advanced.defense') }}" class="block p-6 bg-card border border-rule rounded-lg hover:border-indigo-500 transition">
            <div class="text-ink font-semibold text-lg">🛡️ Reputation Defense</div>
        </a>
    </div>
</div>
