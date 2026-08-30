<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased selection:bg-indigo-500 selection:text-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GO AI EZ — Autonomous AI Receptionist & Operations Engine for Contractors</title>

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
                    <a href="/onboarding" class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">
                        Voice (X-118)
                    </a>
                    <a href="/pricebook" class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">
                        Pricebook (X-163)
                    </a>
                    <a href="/reviews" class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">
                        Reviews (C-Reviews)
                    </a>
                    <a href="/portal" class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">
                        Portal (X-172)
                    </a>
                    <a href="/architecture" class="px-3 py-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-900 transition">
                        Architecture
                    </a>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <a href="/onboarding" class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 transition">
                    Start in 60s &rarr;
                </a>
                <a href="/up" class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-md bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Live (/up)
                </a>
            </div>
        </div>
    </header>

    <!-- Main Hero Section -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-16">
        <div class="relative rounded-3xl border border-slate-800 bg-gradient-to-b from-slate-900/90 via-slate-950 to-slate-950 p-8 sm:p-14 overflow-hidden shadow-2xl">
            <!-- Ambient Glow Elements -->
            <div class="absolute -top-32 -right-32 w-[32rem] h-[32rem] bg-indigo-500/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-32 -left-32 w-[32rem] h-[32rem] bg-purple-500/15 rounded-full blur-3xl pointer-events-none"></div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center relative z-10">
                <div class="lg:col-span-7 space-y-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-300 border border-indigo-500/30">
                        <svg class="w-3.5 h-3.5 text-indigo-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Autonomous AI Receptionist & Operations for Field Contractors
                    </div>

                    <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-white leading-[1.1]">
                        Never Miss Another <span class="bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 bg-clip-text text-transparent">Service Call.</span> Turn Inbound Rings Into Booked Jobs.
                    </h1>

                    <p class="text-base sm:text-lg text-slate-300 leading-relaxed max-w-2xl">
                        GO AI EZ answers every phone call 24/7/365 in natural voice, qualifies emergencies, schedules technician dispatches, delivers zero-credential customer payment portals, and collects 5-star Google reviews on autopilot.
                    </p>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
                        <a href="/onboarding" class="px-6 py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-xl shadow-indigo-600/30 transition text-center flex items-center justify-center gap-2">
                            Deploy Voice Agent in 60s &rarr;
                        </a>
                        <a href="/reviews" class="px-6 py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-200 border border-slate-700 font-semibold text-sm transition text-center flex items-center justify-center gap-2">
                            Explore Review Hub &rarr;
                        </a>
                        <a href="/portal" class="px-6 py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-200 border border-slate-700 font-semibold text-sm transition text-center flex items-center justify-center gap-2">
                            Customer Portal &rarr;
                        </a>
                    </div>

                    <!-- Micro Feature Tags -->
                    <div class="pt-4 flex flex-wrap gap-4 text-xs text-slate-400">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            2-Field Fast Signup (No Passwords)
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            FTC & Google P-110 Compliant
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Direct Stripe & WebRTC Voice
                        </span>
                    </div>
                </div>

                <!-- Live Voice Agent Simulation Card -->
                <div class="lg:col-span-5">
                    <div class="rounded-2xl border border-indigo-500/40 bg-slate-950/90 p-6 shadow-2xl backdrop-blur-md space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="relative flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                                </span>
                                <span class="text-xs font-bold text-white uppercase tracking-wider">Ava · Voice AI Live</span>
                            </div>
                            <span class="text-[11px] font-mono text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded border border-indigo-500/20">
                                LiveKit WebRTC
                            </span>
                        </div>

                        <!-- Audio Waveform Visualization Simulation -->
                        <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 flex items-center justify-between gap-1">
                            @for($i = 0; $i < 24; $i++)
                                <div class="w-1.5 bg-gradient-to-t from-indigo-500 to-purple-400 rounded-full animate-pulse" style="height: {{ [14, 28, 42, 18, 36, 48, 22, 38, 44, 16, 32, 40, 26, 46, 20, 34, 42, 18, 30, 44, 22, 38, 16, 28][$i] }}px; animation-delay: {{ $i * 70 }}ms;"></div>
                            @endfor
                        </div>

                        <!-- Live Conversation Excerpt -->
                        <div class="space-y-2.5 text-xs">
                            <div class="p-2.5 rounded-lg bg-indigo-950/40 border border-indigo-800/40 space-y-1">
                                <span class="font-bold text-indigo-400">Ava (AI Receptionist):</span>
                                <p class="text-slate-200">"Thank you for calling Master Plumbing! I can dispatch an emergency technician between 2:00 PM and 4:00 PM today. What is your service address?"</p>
                            </div>
                            <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 space-y-1">
                                <span class="font-bold text-slate-300">Customer:</span>
                                <p class="text-slate-300">"Yes please, 742 Evergreen Terrace. Main water valve is leaking."</p>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-800 flex items-center justify-between text-xs">
                            <span class="text-slate-400">Time to Live Number:</span>
                            <span class="font-bold font-mono text-emerald-400">&lt; 950 ms (Instant)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Proof Metrics Bar -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-5 rounded-2xl border border-slate-800/80 bg-slate-900/50 flex flex-col justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Voice Pickup Speed</span>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-white">&lt; 1 sec</span>
                    <span class="text-xs text-emerald-400 font-medium">Instant</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-1">WebRTC & SIP Trunks</span>
            </div>

            <div class="p-5 rounded-2xl border border-slate-800/80 bg-slate-900/50 flex flex-col justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Onboarding TTFM</span>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-indigo-400">60 sec</span>
                    <span class="text-xs text-indigo-300 font-medium">Zero-Friction</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-1">2 Fields · Zero Hard Stops</span>
            </div>

            <div class="p-5 rounded-2xl border border-slate-800/80 bg-slate-900/50 flex flex-col justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Compliance Lints</span>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-purple-400">100%</span>
                    <span class="text-xs text-purple-300 font-medium">P-110</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-1">FTC & Google Safe</span>
            </div>

            <div class="p-5 rounded-2xl border border-slate-800/80 bg-slate-900/50 flex flex-col justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">After-Hours Capture</span>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-pink-400">0</span>
                    <span class="text-xs text-pink-300 font-medium">Missed Calls</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-1">24/7 Autonomous Booking</span>
            </div>
        </div>

        <!-- The 4-Pillar Autonomous Flywheel -->
        <div class="space-y-8">
            <div class="text-center max-w-3xl mx-auto space-y-3">
                <span class="text-xs font-semibold px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    The Complete Autonomous Loop
                </span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white">How GO AI EZ Runs Your Trades Business</h2>
                <p class="text-sm text-slate-400">From the first customer ring to instant dispatch, digital signature, payment, and a 5-star Google review.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Pillar 1 -->
                <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4 hover:border-indigo-500/50 transition flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold">
                            1
                        </div>
                        <h3 class="text-lg font-bold text-white">24/7 Voice AI Answering</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Ava answers every call instantly. Identifies emergency repairs, quotes standard rates from your pricebook, and books the job into your calendar.
                        </p>
                    </div>
                    <a href="/onboarding" class="text-xs text-indigo-400 font-semibold hover:text-indigo-300 transition">Test Fast Signup &rarr;</a>
                </div>

                <!-- Pillar 2 -->
                <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4 hover:border-purple-500/50 transition flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center font-bold">
                            2
                        </div>
                        <h3 class="text-lg font-bold text-white">Smart Dispatch & Routing</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Automatically dispatches the nearest technician with the right skillset. Sends real-time GPS tracking and arrival SMS to the customer.
                        </p>
                    </div>
                    <a href="/architecture" class="text-xs text-purple-400 font-semibold hover:text-purple-300 transition">View X-162 Dispatch &rarr;</a>
                </div>

                <!-- Pillar 3 -->
                <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4 hover:border-emerald-500/50 transition flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">
                            3
                        </div>
                        <h3 class="text-lg font-bold text-white">Magic Portal & E-Sign</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Customers review transparent estimates, execute legal digital signatures, and pay invoices instantly via Stripe without login friction.
                        </p>
                    </div>
                    <a href="/portal" class="text-xs text-emerald-400 font-semibold hover:text-emerald-300 transition">Open Customer Portal &rarr;</a>
                </div>

                <!-- Pillar 4 -->
                <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4 hover:border-pink-500/50 transition flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-10 h-10 rounded-xl bg-pink-500/10 border border-pink-500/20 text-pink-400 flex items-center justify-center font-bold">
                            4
                        </div>
                        <h3 class="text-lg font-bold text-white">Reputation Flywheel</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Sends post-service review requests. Automatically responds to 5★ reviews on Google and diverts 1-3★ feedback to private QA tickets.
                        </p>
                    </div>
                    <a href="/reviews" class="text-xs text-pink-400 font-semibold hover:text-pink-300 transition">Open Review Hub &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Interactive Workspaces Launch Cards -->
        <div class="rounded-3xl border border-slate-800 bg-slate-900/40 p-8 sm:p-10 space-y-6">
            <div>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    Live Operational Suites
                </span>
                <h2 class="text-2xl font-bold text-white mt-2">Test Drive Interactive Subsystems Live</h2>
                <p class="text-xs text-slate-400">Directly experience the four core modules running live on https://anti.goaiez.com.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <a href="/onboarding" class="p-5 rounded-2xl border border-indigo-500/30 bg-gradient-to-b from-indigo-950/40 to-slate-950 hover:border-indigo-500/60 transition group space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-indigo-400 uppercase">Module X-118</span>
                        <span class="text-xs text-slate-400 group-hover:text-white transition">Launch &rarr;</span>
                    </div>
                    <h3 class="text-sm font-bold text-white group-hover:text-indigo-300 transition">Fast Onboarding</h3>
                    <p class="text-[11px] text-slate-300 leading-relaxed">2-field signup, dedicated voice number pool assignment, and First-Win call simulator.</p>
                </a>

                <a href="/pricebook" class="p-5 rounded-2xl border border-amber-500/30 bg-gradient-to-b from-amber-950/40 to-slate-950 hover:border-amber-500/60 transition group space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-400 uppercase">Module X-163</span>
                        <span class="text-xs text-slate-400 group-hover:text-white transition">Launch &rarr;</span>
                    </div>
                    <h3 class="text-sm font-bold text-white group-hover:text-amber-300 transition">Dynamic Pricebook</h3>
                    <p class="text-[11px] text-slate-300 leading-relaxed">Callout fee deductions, location versioning, and AI voice quoting test simulator.</p>
                </a>

                <a href="/reviews" class="p-5 rounded-2xl border border-purple-500/30 bg-gradient-to-b from-purple-950/40 to-slate-950 hover:border-purple-500/60 transition group space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-purple-400 uppercase">Module C-Reviews</span>
                        <span class="text-xs text-slate-400 group-hover:text-white transition">Launch &rarr;</span>
                    </div>
                    <h3 class="text-sm font-bold text-white group-hover:text-purple-300 transition">Reputation Hub</h3>
                    <p class="text-[11px] text-slate-300 leading-relaxed">P-110 prompt linter, multi-channel review feed, 5★ auto-replies, and 1-3★ QA tickets.</p>
                </a>

                <a href="/portal" class="p-5 rounded-2xl border border-emerald-500/30 bg-gradient-to-b from-emerald-950/40 to-slate-950 hover:border-emerald-500/60 transition group space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-400 uppercase">Module X-172</span>
                        <span class="text-xs text-slate-400 group-hover:text-white transition">Launch &rarr;</span>
                    </div>
                    <h3 class="text-sm font-bold text-white group-hover:text-emerald-300 transition">Customer Portal</h3>
                    <p class="text-[11px] text-slate-300 leading-relaxed">Zero-credential document review, E-Sign execution, Stripe payment, and dispatch tracker.</p>
                </a>
            </div>
        </div>

        <!-- Trades & Verticals Supported -->
        <div class="space-y-6">
            <div class="text-center max-w-2xl mx-auto space-y-2">
                <h2 class="text-2xl font-bold text-white">Built for High-Velocity Service Contractors</h2>
                <p class="text-xs text-slate-400">Pre-trained with deep vertical knowledge bases, emergency workflows, and industry pricebooks.</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 text-center space-y-1">
                    <div class="text-lg">🚰</div>
                    <div class="font-bold text-white">Plumbing & Drains</div>
                    <div class="text-[11px] text-slate-400">Leak triage & rooter dispatch</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 text-center space-y-1">
                    <div class="text-lg">❄️</div>
                    <div class="font-bold text-white">HVAC & Heating</div>
                    <div class="text-[11px] text-slate-400">AC outages & seasonal tuneups</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 text-center space-y-1">
                    <div class="text-lg">⚡</div>
                    <div class="font-bold text-white">Electrical</div>
                    <div class="text-[11px] text-slate-400">Breakers & generator installs</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 text-center space-y-1">
                    <div class="text-lg">🏠</div>
                    <div class="font-bold text-white">Roofing & Siding</div>
                    <div class="text-[11px] text-slate-400">Storm damage & inspections</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 text-center space-y-1">
                    <div class="text-lg">🚪</div>
                    <div class="font-bold text-white">Garage Doors</div>
                    <div class="text-[11px] text-slate-400">Broken springs & opener repair</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 text-center space-y-1">
                    <div class="text-lg">🌊</div>
                    <div class="font-bold text-white">Water Restoration</div>
                    <div class="text-[11px] text-slate-400">Emergency flood extraction</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 text-center space-y-1">
                    <div class="text-lg">🔑</div>
                    <div class="font-bold text-white">Locksmiths</div>
                    <div class="text-[11px] text-slate-400">Emergency 24/7 lockout response</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 text-center space-y-1">
                    <div class="text-lg">🏢</div>
                    <div class="font-bold text-white">Facilities & Commercial</div>
                    <div class="text-[11px] text-slate-400">Preventive maintenance SLAs</div>
                </div>
            </div>
        </div>

        <!-- Bottom CTA Banner -->
        <div class="rounded-3xl border border-indigo-500/30 bg-gradient-to-r from-indigo-950 via-purple-950 to-slate-950 p-8 sm:p-12 text-center space-y-6 shadow-2xl">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Ready to Put Your Field Business on Autopilot?</h2>
            <p class="text-sm text-slate-300 max-w-xl mx-auto">
                Get a dedicated AI voice number in 60 seconds. No credit card required. No complex setup.
            </p>
            <div class="flex justify-center">
                <a href="/onboarding" class="px-8 py-4 rounded-xl bg-white hover:bg-slate-100 text-slate-950 font-bold text-sm shadow-xl transition flex items-center gap-2">
                    Start Fast Onboarding Now &rarr;
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-950 py-8 text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>&copy; {{ date('Y') }} GO AI EZ Inc. Antigravity Autonomous Platform.</div>
            <div class="flex items-center gap-5">
                <a href="/onboarding" class="hover:text-slate-300 transition">Voice Answering</a>
                <a href="/reviews" class="hover:text-slate-300 transition">Review Hub</a>
                <a href="/portal" class="hover:text-slate-300 transition">Customer Portal</a>
                <a href="/architecture" class="hover:text-slate-300 transition">Architecture (124 Modules)</a>
                <a href="/up" class="hover:text-slate-300 transition">System Status</a>
            </div>
        </div>
    </footer>
</body>
</html>
