<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-500 dark:text-gray-400">Geo-Grid Rank Tracker</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Visual Geo-Grid Map Rank Tracker <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pin-by-pin local Google Maps 3-Pack rank telemetry measured across your catchment area radius.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 gap-3">
            <button wire:click="runScan" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                <span wire:loading.remove>Run Geo-Grid Scan</span>
                <span wire:loading>Scanning Map Nodes...</span>
            </button>
        </div>
    </div>

    @if ($scanNotification)
        <div class="mb-6 p-4 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm flex items-center justify-between">
            <span>{{ $scanNotification }}</span>
            <button wire:click="$set('scanNotification', null)" class="text-emerald-600 hover:underline text-xs">Dismiss</button>
        </div>
    @endif

    <!-- Metric Summary -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Average Grid Position</div>
            <div class="mt-1 text-3xl font-bold text-emerald-600">#{{ $avgRank }}</div>
            <div class="mt-1 text-xs text-gray-500">Across {{ count($gridData) }} geo-coordinates</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">3-Pack Dominance</div>
            <div class="mt-1 text-3xl font-bold text-indigo-600">{{ $top3Dominance }}%</div>
            <div class="mt-1 text-xs text-emerald-600 font-medium">Top 3 rank on 8 of 9 nodes</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Catchment Coverage</div>
            <div class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ $radiusMiles }} mi</div>
            <div class="mt-1 text-xs text-gray-500">Service area radius monitored</div>
        </div>
    </div>

    <!-- Keyword & Grid Configuration Toolbar -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 border border-gray-200 dark:border-gray-700 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Target Keyword</label>
                <select wire:model.live="selectedKeyword" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm py-2 px-3 border shadow-sm">
                    @foreach ($keywords as $kw)
                        <option value="{{ $kw }}">{{ $kw }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Grid Resolution</label>
                <select wire:model.live="gridSize" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm py-2 px-3 border shadow-sm">
                    <option value="3">3 x 3 Node Matrix (9 points)</option>
                    <option value="5">5 x 5 Node Matrix (25 points)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Catchment Radius</label>
                <select wire:model.live="radiusMiles" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm py-2 px-3 border shadow-sm">
                    <option value="3">3 Miles Radius</option>
                    <option value="5">5 Miles Radius</option>
                    <option value="10">10 Miles Radius</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Visual Geo-Grid Map Simulation -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700 mb-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Local Map Ranking Heatmap</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Search Query: <strong>"{{ $selectedKeyword }}"</strong></p>
            </div>
            <div class="flex items-center gap-4 text-xs font-medium">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-emerald-500"></span> #1–3 (3-Pack)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-amber-500"></span> #4–10 (First Page)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-rose-500"></span> #11+ (Low Rank)</span>
            </div>
        </div>

        <!-- Simulated Map Canvas Container -->
        <div class="relative bg-slate-100 dark:bg-slate-900 rounded-xl p-8 border border-slate-200 dark:border-slate-800 min-h-[420px] flex items-center justify-center overflow-hidden">
            <!-- Simulated Map Background Grid Lines -->
            <div class="absolute inset-0 opacity-20 dark:opacity-10 bg-[radial-gradient(#6366f1_1px,transparent_1px)] [background-size:24px_24px]"></div>

            <!-- Central Business Marker -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="h-44 w-44 rounded-full border-2 border-dashed border-indigo-400/40 bg-indigo-500/5 animate-pulse"></div>
            </div>

            <!-- 3x3 Geo-Pins Grid -->
            <div class="relative z-10 grid grid-cols-3 gap-8 sm:gap-14 max-w-lg mx-auto">
                @foreach ($gridData as $point)
                    @php
                        $isTop3 = $point['rank'] <= 3;
                        $colorClass = $isTop3 ? 'bg-emerald-600 text-white ring-4 ring-emerald-100 dark:ring-emerald-950' : ($point['rank'] <= 10 ? 'bg-amber-500 text-white ring-4 ring-amber-100 dark:ring-amber-950' : 'bg-rose-600 text-white ring-4 ring-rose-100 dark:ring-rose-950');
                    @endphp
                    <div class="flex flex-col items-center group cursor-pointer">
                        <div class="h-12 w-12 rounded-full {{ $colorClass }} font-display text-lg font-bold flex items-center justify-center shadow-lg transform transition group-hover:scale-110">
                            #{{ $point['rank'] }}
                        </div>
                        <div class="mt-2 text-[10px] font-mono text-gray-500 dark:text-gray-400 bg-white/80 dark:bg-gray-800/80 px-2 py-0.5 rounded shadow-xs">
                            {{ $point['lat'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
