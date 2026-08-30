<x-layouts.app>
    <x-slot:title>System Architecture & 124-Module Engine — GO AI EZ</x-slot:title>

    <div class="space-y-10">
        <!-- Architecture Hero Section -->
        <div class="relative rounded-2xl border border-slate-800 bg-gradient-to-b from-slate-900/90 to-slate-950 p-8 sm:p-12 overflow-hidden shadow-2xl">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 max-w-3xl space-y-4">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                    <svg class="w-3.5 h-3.5 text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Autopilot Wave Execution Complete · All 12 Journeys Green
                </div>
                <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-white">
                    GO AI EZ <span class="bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 bg-clip-text text-transparent">Enterprise Architecture</span>
                </h1>
                <p class="text-base sm:text-lg text-slate-300 leading-relaxed">
                    A fully modular Domain-Driven Design (DDD) & CQRS platform executing all business capabilities autonomously across AI intelligence, omnichannel transports, field operations, and real-time review ecosystems.
                </p>
                <div class="pt-2 flex flex-wrap gap-2 text-xs text-slate-400">
                    <span class="px-2.5 py-1 rounded bg-slate-800/80 border border-slate-700/50">Laravel 13.29</span>
                    <span class="px-2.5 py-1 rounded bg-slate-800/80 border border-slate-700/50">PHP 8.4 (ea-php84)</span>
                    <span class="px-2.5 py-1 rounded bg-slate-800/80 border border-slate-700/50">Postgres 16 (RLS Isolated)</span>
                    <span class="px-2.5 py-1 rounded bg-slate-800/80 border border-slate-700/50">Livewire 4</span>
                    <span class="px-2.5 py-1 rounded bg-slate-800/80 border border-slate-700/50">Tailwind CSS 4</span>
                </div>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-5 rounded-xl border border-slate-800/80 bg-slate-900/60 flex flex-col justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Modules</span>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-white">{{ $modulesCount }}</span>
                    <span class="text-xs text-emerald-400 font-medium">100% Complete</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-1">Modular DDD sub-domains</span>
            </div>

            <div class="p-5 rounded-xl border border-slate-800/80 bg-slate-900/60 flex flex-col justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Build Waves</span>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-indigo-400">{{ $wavesCount }}</span>
                    <span class="text-xs text-indigo-300 font-medium">Ordered</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-1">Dependency graph resolved</span>
            </div>

            <div class="p-5 rounded-xl border border-slate-800/80 bg-slate-900/60 flex flex-col justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Journeys</span>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-purple-400">{{ $journeysCount }}</span>
                    <span class="text-xs text-purple-300 font-medium">Verified</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-1">Real transports integration</span>
            </div>

            <div class="p-5 rounded-xl border border-slate-800/80 bg-slate-900/60 flex flex-col justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Capabilities</span>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-pink-400">{{ $capabilitiesCount }}</span>
                    <span class="text-xs text-pink-300 font-medium">Active</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-1">Contract-enforced matrix</span>
            </div>
        </div>

        <!-- Live Infrastructure & System Status -->
        <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                </svg>
                Live Runtime Environment
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div class="p-4 rounded-lg bg-slate-950/60 border border-slate-800/60 space-y-1">
                    <div class="text-slate-400">PostgreSQL 16 Database</div>
                    <div class="text-sm font-semibold text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ $dbStatus }}
                    </div>
                    <div class="text-[11px] text-slate-500">{{ $migrationsCount }} Migrations Ran · {{ $tablesCount }} Tables</div>
                </div>

                <div class="p-4 rounded-lg bg-slate-950/60 border border-slate-800/60 space-y-1">
                    <div class="text-slate-400">Queue & Horizon Workers</div>
                    <div class="text-sm font-semibold text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        Active & Processing
                    </div>
                    <div class="text-[11px] text-slate-500">Cron Scheduler & Background Workers</div>
                </div>

                <div class="p-4 rounded-lg bg-slate-950/60 border border-slate-800/60 space-y-1">
                    <div class="text-slate-400">Web Host & Domain</div>
                    <div class="text-sm font-semibold text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                        anti.goaiez.com
                    </div>
                    <div class="text-[11px] text-slate-500">Let's Encrypt SSL · PHP-FPM 8.4</div>
                </div>
            </div>
        </div>

        <!-- Architectural Modules Breakdown -->
        <div class="space-y-6">
            <div>
                <h2 class="text-xl font-bold text-white">Subsystem Categories</h2>
                <p class="text-sm text-slate-400">Organized by architectural bounded contexts and event-driven domains.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($categories as $cat)
                    <div class="rounded-xl border border-slate-800/80 bg-slate-900/50 p-5 flex flex-col justify-between hover:border-slate-700 transition">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full border {{ $cat['badge_bg'] }}">
                                    {{ $cat['badge'] }}
                                </span>
                                <span class="text-[11px] text-slate-500">{{ count($cat['modules']) }} modules</span>
                            </div>
                            <h3 class="font-bold text-white text-base">{{ $cat['name'] }}</h3>
                            <ul class="space-y-2 text-xs">
                                @foreach ($cat['modules'] as $mod)
                                    <li class="flex items-start gap-2 text-slate-300">
                                        <span class="font-mono text-indigo-400 font-semibold shrink-0">[{{ $mod['id'] }}]</span>
                                        <span class="text-slate-300">{{ $mod['name'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
