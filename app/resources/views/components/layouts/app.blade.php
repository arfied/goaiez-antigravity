<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased selection:bg-indigo-500 selection:text-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'GO AI EZ — Antigravity Platform' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Instrument Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    @livewireStyles
</head>
<body class="min-h-full flex flex-col bg-slate-950 font-sans text-slate-200">
    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 border-b border-slate-800/80 bg-slate-950/80 backdrop-blur-md">
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
                            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 font-semibold border border-indigo-500/30">Antigravity</span>
                        </div>
                        <p class="text-[11px] text-slate-400 hidden sm:block">Autonomous 124-Module DDD & CQRS</p>
                    </div>
                </a>

                <!-- Navigation Tabs -->
                <nav class="hidden md:flex items-center gap-1 text-xs font-medium">
                    <a href="/" class="px-3 py-1.5 rounded-lg {{ (request() && request()->is('/')) ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-900' }} transition">
                        Architecture Overview
                    </a>
                    <a href="/onboarding" class="px-3 py-1.5 rounded-lg {{ (request() && request()->is('onboarding*')) ? 'bg-indigo-600/30 text-indigo-300 border border-indigo-500/40' : 'text-slate-400 hover:text-white hover:bg-slate-900' }} transition">
                        Fast Onboarding (X-118)
                    </a>
                    <a href="/reviews" class="px-3 py-1.5 rounded-lg {{ (request() && request()->is('reviews*')) ? 'bg-purple-600/30 text-purple-300 border border-purple-500/40' : 'text-slate-400 hover:text-white hover:bg-slate-900' }} transition">
                        Review Hub (C-Reviews)
                    </a>
                    <a href="/portal" class="px-3 py-1.5 rounded-lg {{ (request() && request()->is('portal*')) ? 'bg-emerald-600/30 text-emerald-300 border border-emerald-500/40' : 'text-slate-400 hover:text-white hover:bg-slate-900' }} transition">
                        Customer Portal (X-172)
                    </a>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <a href="/up" class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-md bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    System Live (/up)
                </a>
                <span class="text-xs font-mono text-slate-400 px-2.5 py-1 rounded bg-slate-900 border border-slate-800 hidden sm:inline-block">
                    anti.goaiez.com
                </span>
            </div>
        </div>
    </header>

    <!-- Main Content Slot -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-950 py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>&copy; {{ date('Y') }} GO AI EZ Inc. Antigravity Platform Engine.</div>
            <div class="flex items-center gap-4">
                <a href="/onboarding" class="hover:text-slate-300 transition">Fast Onboarding</a>
                <span>·</span>
                <a href="/reviews" class="hover:text-slate-300 transition">Review Hub</a>
                <span>·</span>
                <a href="/portal" class="hover:text-slate-300 transition">Portal</a>
                <span>·</span>
                <a href="/up" class="hover:text-slate-300 transition">Health Status</a>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
