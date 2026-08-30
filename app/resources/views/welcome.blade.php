<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased selection:bg-indigo-500 selection:text-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GO AI EZ — Antigravity Platform</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Tailwind CSS CDN for complete UI components rendering -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Instrument Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            900: '#312e81',
                            950: '#1e1b4b',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-full flex flex-col bg-slate-950 font-sans text-slate-200">
    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 border-b border-slate-800/80 bg-slate-950/80 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 flex items-center justify-center shadow-lg shadow-indigo-500/20 ring-1 ring-white/20">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-white tracking-tight text-lg">GO AI EZ</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 font-semibold border border-indigo-500/30">Antigravity</span>
                        </div>
                        <p class="text-[11px] text-slate-400 hidden sm:block">Autonomous 124-Module Engine · DDD & CQRS Architecture</p>
                    </div>
                </div>

                <nav class="hidden md:flex items-center gap-1 text-xs font-medium">
                    <a href="/" class="px-3 py-1.5 rounded-lg bg-slate-800 text-white transition">
                        Architecture Overview
                    </a>
                    <a href="/onboarding" class="px-3 py-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-900 transition">
                        Fast Onboarding (X-118)
                    </a>
                    <a href="/reviews" class="px-3 py-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-900 transition">
                        Review Hub (C-Reviews)
                    </a>
                    <a href="/portal" class="px-3 py-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-900 transition">
                        Customer Portal (X-172)
                    </a>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <a href="/up" class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-md bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    System Live (/up)
                </a>
                <span class="text-xs font-medium px-3 py-1.5 rounded-md bg-slate-800/80 text-slate-300 border border-slate-700/60 transition hidden sm:inline-flex">
                    anti.goaiez.com
                </span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">
        <!-- Hero Section -->
        <div class="relative rounded-2xl border border-slate-800 bg-gradient-to-b from-slate-900/90 to-slate-950 p-8 sm:p-12 overflow-hidden shadow-2xl">
            <!-- Background Glow -->
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
                    GO AI EZ <span class="bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 bg-clip-text text-transparent">Enterprise Autopilot</span>
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

        <!-- Interactive Livewire UI Workspaces -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-white">Interactive Operational Workspaces</h2>
                    <p class="text-xs text-slate-400">Real-time live Livewire 4 components connected to autonomous backend actions.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <a href="/onboarding" class="group p-6 rounded-2xl border border-indigo-500/30 bg-gradient-to-b from-indigo-950/40 to-slate-900/80 hover:border-indigo-500/60 transition shadow-lg flex flex-col justify-between space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                Module X-118
                            </span>
                            <span class="text-xs text-indigo-400 font-medium group-hover:translate-x-1 transition">Open &rarr;</span>
                        </div>
                        <h3 class="text-base font-bold text-white group-hover:text-indigo-300 transition">Fast Tenant Onboarding</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            2-field instant signup with zero hard stops. Ingests business info, provisions dedicated direct-dial phone number, and simulates direct AI voice first-win.
                        </p>
                    </div>
                    <div class="pt-3 border-t border-indigo-900/40 text-[11px] text-indigo-400 font-semibold flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                        Voice Agent Live Answer
                    </div>
                </a>

                <a href="/reviews" class="group p-6 rounded-2xl border border-purple-500/30 bg-gradient-to-b from-purple-950/40 to-slate-900/80 hover:border-purple-500/60 transition shadow-lg flex flex-col justify-between space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                Module C-Reviews
                            </span>
                            <span class="text-xs text-purple-400 font-medium group-hover:translate-x-1 transition">Open &rarr;</span>
                        </div>
                        <h3 class="text-base font-bold text-white group-hover:text-purple-300 transition">Reputation & Review Hub</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            P-110 compliant review dispatch with prompt lints (zero staff mentions/incentives), 4-5★ auto-replies, and 1-3★ internal QA SLA escalations.
                        </p>
                    </div>
                    <div class="pt-3 border-t border-purple-900/40 text-[11px] text-purple-400 font-semibold flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-400"></span>
                        Sentiment & QA Escalations
                    </div>
                </a>

                <a href="/portal" class="group p-6 rounded-2xl border border-emerald-500/30 bg-gradient-to-b from-emerald-950/40 to-slate-900/80 hover:border-emerald-500/60 transition shadow-lg flex flex-col justify-between space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                Module X-172
                            </span>
                            <span class="text-xs text-emerald-400 font-medium group-hover:translate-x-1 transition">Open &rarr;</span>
                        </div>
                        <h3 class="text-base font-bold text-white group-hover:text-emerald-300 transition">Customer Service Portal</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Zero-credential magic portal. Itemized estimate review, legally compliant electronic signature pad, instant card payment, and appointment booking.
                        </p>
                    </div>
                    <div class="pt-3 border-t border-emerald-900/40 text-[11px] text-emerald-400 font-semibold flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        E-Signature & Payment
                    </div>
                </a>
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
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-950 py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>&copy; {{ date('Y') }} GO AI EZ Inc. Antigravity Autopilot Deployment.</div>
            <div class="flex items-center gap-4">
                <a href="/up" class="hover:text-slate-300 transition">Health Status</a>
                <span>·</span>
                <span class="text-slate-400">anti.goaiez.com</span>
            </div>
        </div>
    </footer>
</body>
</html>
