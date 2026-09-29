<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-paper text-ink antialiased selection:bg-indigo-500 selection:text-white dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'GO AI EZ — Antigravity Platform' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full flex flex-col bg-paper font-sans text-ink">
    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 border-b border-rule/80 bg-paper/80 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="/" class="flex items-center gap-3 group">
                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 flex items-center justify-center shadow-lg shadow-indigo-500/20 ring-1 ring-white/20 group-hover:scale-105 transition">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-white tracking-tight text-lg">GO AI EZ</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 font-semibold border border-indigo-500/30">Autonomous</span>
                        </div>
                        <p class="text-[11px] text-slate-400 hidden sm:block">AI Receptionist & Operations for Contractors</p>
                    </div>
                </a>

                <!-- Navigation Tabs -->
                                <nav class="hidden md:flex items-center gap-1 text-xs font-medium">
                    @foreach (config('surfaces.generated.tenant', []) as $group => $items)
                        <div class="relative group/dropdown">
                            <button class="px-3 py-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-900 transition flex items-center gap-1">
                                {{ $group }}
                                <svg class="w-3 h-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            <div class="absolute left-0 top-full mt-1 hidden group-hover/dropdown:block w-48 bg-slate-900 border border-slate-800 rounded-lg shadow-xl overflow-hidden py-1 z-50">
                                @foreach ($items as $item)
                                    <a href="{{ route($item['route']) }}" class="block px-4 py-2 text-sm text-slate-400 hover:text-white hover:bg-slate-800 {{ request()->routeIs($item['route']) ? 'bg-slate-800 text-white font-medium' : '' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <a href="/onboarding" class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 transition">
                    Start in 60s &rarr;
                </a>
                <a href="/up" class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-md bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Live (/up)
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Slot -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="border-t border-rule/80 bg-paper py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>&copy; {{ date('Y') }} GO AI EZ Inc. Antigravity Platform Engine.</div>
            <div class="flex items-center gap-4">
                <a href="/" class="hover:text-slate-300 transition">Home</a>
                <span>·</span>
                <a href="/onboarding" class="hover:text-slate-300 transition">Voice Answering</a>
                <span>·</span>
                <a href="/pricebook" class="hover:text-slate-300 transition">Pricebook</a>
                <span>·</span>
                <a href="/reviews" class="hover:text-slate-300 transition">Review Hub</a>
                <span>·</span>
                <a href="/portal" class="hover:text-slate-300 transition">Customer Portal</a>
                <span>·</span>
                <a href="/architecture" class="hover:text-slate-300 transition">Architecture</a>
                <span>·</span>
                <a href="/up" class="hover:text-slate-300 transition">Health Status</a>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
