<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Geo-Grid Rank Tracker</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Visual Geo-Grid Map Rank Tracker <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Pin-by-pin local Google Maps 3-Pack rank telemetry measured across your catchment area radius.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 gap-3">
            <button wire:click="runScan" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                <span wire:loading.remove>Run Geo-Grid Scan</span>
                <span wire:loading>Scanning Map Nodes...</span>
            </button>
        </div>
    </div>

    @if ($scanNotification)
        <div class="mb-6 p-4 rounded-md bg-ok-bg text-ok border border-ok/20 text-sm flex items-center justify-between">
            <span>{{ $scanNotification }}</span>
            <button wire:click="$set('scanNotification', null)" class="text-ink hover:underline text-xs">Dismiss</button>
        </div>
    @endif

    <!-- Metric Summary -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
        <div class="bg-card border border-rule rounded-card shadow-card p-5">
            <div class="text-xs font-medium text-ink-2 uppercase">Average Grid Position</div>
            <div class="mt-1 text-3xl font-bold text-ink">#{{ $avgRank }}</div>
            <div class="mt-1 text-xs text-ink-2">Across {{ count($gridData) }} geo-coordinates</div>
        </div>
        <div class="bg-card border border-rule rounded-card shadow-card p-5">
            <div class="text-xs font-medium text-ink-2 uppercase">3-Pack Dominance</div>
            <div class="mt-1 text-3xl font-bold text-ink">{{ $top3Dominance }}%</div>
            <div class="mt-1 text-xs text-ink font-medium">Top 3 rank on 8 of 9 nodes</div>
        </div>
        <div class="bg-card border border-rule rounded-card shadow-card p-5">
            <div class="text-xs font-medium text-ink-2 uppercase">Catchment Coverage</div>
            <div class="mt-1 text-3xl font-bold text-ink">{{ $radiusMiles }} mi</div>
            <div class="mt-1 text-xs text-ink-2">Service area radius monitored</div>
        </div>
    </div>

    <!-- Keyword & Grid Configuration Toolbar -->
    <div class="bg-card border border-rule rounded-card shadow-card p-5 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="target_keyword" class="block text-xs font-semibold text-ink-2 mb-1">Target Keyword</label>
                <select id="target_keyword" wire:model.live="selectedKeyword" class="w-full rounded-md bg-paper text-ink border-rule text-sm py-2 px-3 border shadow-sm">
                    @foreach ($keywords as $kw)
                        <option value="{{ $kw }}">{{ $kw }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="grid_resolution" class="block text-xs font-semibold text-ink-2 mb-1">Grid Resolution</label>
                <select id="grid_resolution" wire:model.live="gridSize" class="w-full rounded-md bg-paper text-ink border-rule text-sm py-2 px-3 border shadow-sm">
                    <option value="3">3 x 3 Node Matrix (9 points)</option>
                    <option value="5">5 x 5 Node Matrix (25 points)</option>
                </select>
            </div>
            <div>
                <label for="catchment_radius" class="block text-xs font-semibold text-ink-2 mb-1">Catchment Radius</label>
                <select id="catchment_radius" wire:model.live="radiusMiles" class="w-full rounded-md bg-paper text-ink border-rule text-sm py-2 px-3 border shadow-sm">
                    <option value="3">3 Miles Radius</option>
                    <option value="5">5 Miles Radius</option>
                    <option value="10">10 Miles Radius</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Visual Geo-Grid Map Simulation -->
    <div class="bg-card border border-rule rounded-card shadow-card p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-lg font-bold text-ink">Local Map Ranking Heatmap</h2>
                <p class="text-xs text-ink-2">Search Query: <strong>"{{ $selectedKeyword }}"</strong></p>
            </div>
            <div class="flex items-center gap-4 text-xs font-medium">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-emerald-500"></span> #1–3 (3-Pack)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-amber-500"></span> #4–10 (First Page)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-rose-500"></span> #11+ (Low Rank)</span>
            </div>
        </div>

        <!-- Simulated Map Canvas Container -->
        <div class="relative bg-paper rounded-xl p-8 border border-rule min-h-[420px] flex items-center justify-center overflow-x-auto overflow-y-hidden">
            <!-- Simulated Map Background Grid Lines -->
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(#6366f1_1px,transparent_1px)] [background-size:24px_24px]"></div>

            <!-- Central Business Marker -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="h-44 w-44 rounded-full border-2 border-dashed border-indigo-400/40 bg-indigo-500/5 animate-pulse"></div>
            </div>

            <!-- 3x3 Geo-Pins Grid -->
            <div class="relative z-10 grid grid-cols-3 gap-8 sm:gap-14 max-w-lg mx-auto">
                @foreach ($gridData as $point)
                    @php
                        $isTop3 = $point['rank'] <= 3;
                        $colorClass = $isTop3 ? 'bg-emerald-800 text-white' : ($point['rank'] <= 10 ? 'bg-amber-900 text-white' : 'bg-rose-800 text-white');
                    @endphp
                    <div class="flex flex-col items-center group cursor-pointer">
                        <div class="h-12 w-12 rounded-full {{ $colorClass }} font-display text-lg font-bold flex items-center justify-center shadow-lg transform transition group-hover:scale-110">
                            #{{ $point['rank'] }}
                        </div>
                        <div class="mt-2 text-[10px] font-mono text-ink-2 bg-card/80 px-2 py-0.5 rounded shadow-xs">
                            {{ $point['lat'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
