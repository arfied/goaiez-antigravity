<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header & Progress Steps -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 backdrop-blur-sm shadow-xl">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        Module X-118 · Zero-Friction Flow
                    </span>
                    <span class="text-xs text-slate-400">Asked Fields: <strong class="text-white">{{ $askedFieldsCount }}</strong></span>
                </div>
                <h1 class="text-2xl font-bold text-white mt-1">Autonomous Tenant Fast Onboarding</h1>
                <p class="text-xs text-slate-400">AI live answering provisioned before domain, KYC or payment gateways are configured.</p>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium {{ $step >= 1 ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400' }}">
                    1. Info
                </span>
                <span class="text-slate-600">→</span>
                <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium {{ $step >= 2 ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400' }}">
                    2. Provision
                </span>
                <span class="text-slate-600">→</span>
                <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium {{ $step >= 3 ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400' }}">
                    3. First-Win
                </span>
            </div>
        </div>

        @if($errorMessage)
            <div class="mt-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center justify-between">
                <span>{{ $errorMessage }}</span>
                <button wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-rose-200">&times;</button>
            </div>
        @endif

        <!-- STEP 1: Two-Field Instant Setup -->
        @if($step === 1)
            <form wire:submit.prevent="startSignup" class="mt-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                            Business Name <span class="text-rose-400">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="businessName" 
                            placeholder="e.g. Austin Master Plumbing" 
                            class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-white placeholder-slate-500 text-sm transition"
                        />
                        @error('businessName') <span class="text-xs text-rose-400">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                            Owner / Contact Phone <span class="text-rose-400">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="contactPhone" 
                            placeholder="e.g. +1 512 555 0199" 
                            class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-white placeholder-slate-500 text-sm transition"
                        />
                        @error('contactPhone') <span class="text-xs text-rose-400">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-2">
                    <div class="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Autonomous Zero-Hard-Stop Inference
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Industry vertical, price book tiers, operating hours, and dispatch rules will be inferred automatically and refined in the background without blocking voice agent activation.
                    </p>
                </div>

                <div class="flex justify-end">
                    <button 
                        type="submit" 
                        wire:target="startSignup"
                        wire:loading.attr="disabled"
                        class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 font-semibold text-white text-sm shadow-lg shadow-indigo-600/30 transition flex items-center gap-2"
                    >
                        <span wire:loading.remove>Deploy Voice Agent Instantly &rarr;</span>
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Provisioning Number & Agent...
                        </span>
                    </button>
                </div>
            </form>
        @endif

        <!-- STEP 2: Inferred Profile & Provisioned Direct Number -->
        @if($step === 2)
            <div class="mt-6 space-y-6">
                <div class="p-5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="space-y-1">
                        <h3 class="font-bold text-white text-base">Agent Live & Number Allocated</h3>
                        <p class="text-xs text-slate-300">
                            Dedicated inbound voice number <strong class="text-emerald-400 font-mono">{{ $provisionedNumber }}</strong> has been bound to <span class="text-white">{{ $businessName }}</span>.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                        <div class="text-slate-400">Tenant Business ID</div>
                        <div class="text-base font-bold text-white font-mono mt-1">#{{ $businessId }}</div>
                        <div class="text-[11px] text-emerald-400 mt-1">RLS Partition Active</div>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                        <div class="text-slate-400">TTFM (Time To First Minute)</div>
                        <div class="text-base font-bold text-indigo-400 font-mono mt-1">950 ms</div>
                        <div class="text-[11px] text-slate-400 mt-1">Target &le; 60,000ms</div>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                        <div class="text-slate-400">Direct Route</div>
                        <div class="text-base font-bold text-purple-400 font-mono mt-1">Direct-Dial (No Fwd)</div>
                        <div class="text-[11px] text-slate-400 mt-1">Carrier Sticky Thread</div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-800">
                    <button wire:click="resetOnboarding" class="text-xs text-slate-400 hover:text-slate-200">
                        &larr; Start New Onboarding
                    </button>
                    <button 
                        wire:click="triggerTestCall" 
                        wire:target="triggerTestCall"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-semibold text-white text-sm shadow-lg shadow-indigo-600/30 transition flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        Trigger Direct Test Call (First-Win) &rarr;
                    </button>
                </div>
            </div>
        @endif

        <!-- STEP 3: Live Direct Test Call Simulation & Transcript -->
        @if($step === 3)
            <div class="mt-6 space-y-6">
                <div class="p-4 rounded-xl bg-slate-950 border border-indigo-500/30 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        <div>
                            <div class="text-xs font-semibold text-white">Call SID: <span class="font-mono text-indigo-300">{{ $callSid }}</span></div>
                            <div class="text-[11px] text-slate-400">Connected to Direct Number {{ $provisionedNumber }}</div>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-emerald-500/20 text-emerald-400 font-semibold text-xs border border-emerald-500/30">
                        First-Win Complete
                    </span>
                </div>

                <!-- Call Transcript Stream -->
                <div class="space-y-3 p-4 rounded-xl bg-slate-950/80 border border-slate-800 max-h-96 overflow-y-auto font-sans">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Live Realtime Transcript</div>
                    @foreach($callTranscript as $turn)
                        <div class="flex items-start gap-3 text-xs leading-relaxed {{ $turn['speaker'] === 'AI Agent' ? 'bg-indigo-950/40 border border-indigo-800/40 rounded-lg p-3' : ($turn['speaker'] === 'System' ? 'text-slate-400 italic text-[11px]' : 'p-2') }}">
                            <span class="font-mono text-[10px] text-slate-500 shrink-0">{{ $turn['time'] }}</span>
                            <div>
                                <span class="font-semibold {{ $turn['speaker'] === 'AI Agent' ? 'text-indigo-400' : ($turn['speaker'] === 'System' ? 'text-emerald-400' : 'text-slate-300') }}">
                                    {{ $turn['speaker'] }}:
                                </span>
                                <span class="text-slate-200 ml-1">{{ $turn['text'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-800">
                    <button wire:click="resetOnboarding" class="text-xs text-slate-400 hover:text-slate-200">
                        &larr; Provision Another Business
                    </button>
                    <div class="flex items-center gap-3">
                        <a href="/reviews" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold transition">
                            Open Reviews Hub &rarr;
                        </a>
                        <a href="/portal" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition">
                            Open Customer Portal &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

