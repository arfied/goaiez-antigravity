<x-surface.sample-state module="services with prices" screen="pricebook" />
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header & Stats Overview -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 backdrop-blur-sm shadow-xl space-y-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        Module X-163 · Dynamic Pricebook & Rate Matrix
                    </span>
                    <span class="text-xs text-slate-400">Tenant Business ID: <strong class="text-white">#{{ $businessId }}</strong></span>
                </div>
                <h1 class="text-2xl font-bold text-white mt-1">Dynamic Pricebook & AI Rate Matrix</h1>
                <p class="text-xs text-slate-400">Controls rates quoted by voice agent Ava, diagnostic callout fee deductions, and location book versions.</p>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-mono px-3 py-1.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-300">
                    Live Items: <strong class="text-white">{{ $items->count() }}</strong>
                </span>
            </div>
        </div>

        @if($actionNotice)
            <div class="p-3.5 rounded-xl text-xs flex items-center justify-between {{ $noticeType === 'warning' ? 'bg-amber-500/10 border border-amber-500/30 text-amber-300' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-300' }}">
                <span class="font-medium">{{ $actionNotice }}</span>
                <button wire:click="$set('actionNotice', null)" class="text-slate-400 hover:text-white font-bold ml-3">&times;</button>
            </div>
        @endif

        <!-- Diagnostic Callout Fee & Location Versioning Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Callout Fee Form -->
            <div class="lg:col-span-7 rounded-xl border border-slate-800 bg-slate-950/80 p-5 space-y-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Standard Diagnostic / Callout Fee Rules
                </h3>
                <p class="text-xs text-slate-400">
                    Quoted when a caller asks *"How much to come out?"*. Ava quotes this amount and cites deduction terms verbatim.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Fee Amount ($ USD)</label>
                        <input 
                            type="number" 
                            step="5" 
                            wire:model="calloutFeeDollars" 
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs text-white focus:border-amber-500 font-mono"
                        />
                    </div>
                    <div class="flex items-center pt-5">
                        <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                            <input 
                                type="checkbox" 
                                wire:model="deductedIfProceeding" 
                                class="rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-amber-400"
                            />
                            <span>Deduct fee from repair invoice</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">AI Verbal Explanation (Quoted to Callers)</label>
                    <textarea 
                        wire:model="calloutExplanation" 
                        rows="2" 
                        class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs text-white placeholder-slate-500 focus:border-amber-500"
                    ></textarea>
                </div>

                <div class="flex justify-end">
                    <button wire:click="saveCalloutFee" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-slate-950 font-bold text-xs transition">
                        Update Callout Fee Settings &rarr;
                    </button>
                </div>
            </div>

            <!-- Location Books & Versioning -->
            <div class="lg:col-span-5 rounded-xl border border-slate-800 bg-slate-950/80 p-5 space-y-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Location Books & Versioning
                </h3>
                <p class="text-xs text-slate-400">
                    Atomic versions per geographic market (bumping London does not bump Leeds).
                </p>

                <div class="space-y-2.5">
                    @foreach($locations as $loc)
                        <div class="p-3 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-between text-xs">
                            <div>
                                <div class="font-bold text-white">{{ $loc->location_name }}</div>
                                <div class="text-[11px] text-indigo-400 font-mono">Active Version: v{{ $loc->version }}</div>
                            </div>
                            <button 
                                wire:click="bumpVersion('{{ $loc->location_name }}')" 
                                class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-medium transition"
                            >
                                Bump Version &rarr;
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Main Catalog & AI Quoting Simulator -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Service Items Table & Add Form -->
        <div class="lg:col-span-8 space-y-4">
            <!-- Add Item Accordion Form -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 backdrop-blur-sm space-y-4">
                <h3 class="text-sm font-bold text-white">Add Service or Rate Item</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] text-slate-400 mb-1">Service / Task Name</label>
                        <input 
                            type="text" 
                            wire:model="newServiceName" 
                            placeholder="e.g. 50-Gallon Water Heater Replacement" 
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white placeholder-slate-600 focus:border-amber-500"
                        />
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Price ($ USD)</label>
                        <input 
                            type="number" 
                            step="5" 
                            wire:model="newPriceDollars" 
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white font-mono focus:border-amber-500"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                        <input 
                            type="checkbox" 
                            wire:model="newIsSample" 
                            class="rounded bg-slate-950 border-slate-800 text-amber-500"
                        />
                        <span>Mark as Draft / Sample (Requires confirmation before AI customer quoting)</span>
                    </label>
                    <button wire:click="addItem" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-slate-950 font-bold text-xs transition">
                        + Add to Pricebook
                    </button>
                </div>
            </div>

            <!-- Price Book Items List -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-xl">
                <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white">Active Service Items & Rates</h3>
                    <span class="text-xs text-slate-400">Total: {{ $items->count() }} items</span>
                </div>

                <div class="divide-y divide-slate-800/80">
                    @forelse($items as $item)
                        <div class="p-4 flex items-center justify-between gap-4 hover:bg-slate-900/40 transition">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-white">{{ $item->service_name }}</span>
                                    @if($item->is_sample)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                            SAMPLE / DRAFT
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            LIVE QUOTABLE
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    Tax: {{ $item->tax_rate_pct ?? 8.25 }}% · ID #{{ $item->id }}
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <div class="text-sm font-bold font-mono text-emerald-400">
                                        ${{ number_format($item->price_cents / 100, 2) }}
                                    </div>
                                    <div class="text-[10px] text-slate-500">Flat Rate</div>
                                </div>

                                @if($item->is_sample)
                                    <button 
                                        wire:click="confirmItem({{ $item->id }})" 
                                        class="px-2.5 py-1 rounded bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-600/40 text-xs font-medium transition"
                                    >
                                        Confirm
                                    </button>
                                @endif

                                <button 
                                    wire:click="deleteItem({{ $item->id }})" 
                                    class="text-slate-500 hover:text-rose-400 p-1 transition"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-slate-500">No items found in pricebook.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- AI Voice Quoting Simulator -->
        <div class="lg:col-span-4 space-y-4">
            <div class="rounded-2xl border border-indigo-500/30 bg-slate-900/80 p-5 shadow-xl space-y-4">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                    <h3 class="text-sm font-bold text-white">AI Quoting Engine Simulator</h3>
                </div>
                <p class="text-xs text-slate-400">
                    Test what Ava quotes when a caller asks for a rate. Enforces R246 & SAMPLE state refusal guardrails.
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Customer Query</label>
                        <input 
                            type="text" 
                            wire:model="testQuery" 
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Channel Persona</label>
                        <select wire:model="testChannel" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white">
                            <option value="customer">Customer Facing (Sample Guardrails Active)</option>
                            <option value="admin">Internal Admin (Unrestricted)</option>
                        </select>
                    </div>

                    <button 
                        wire:click="runTestQuote" 
                        class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition"
                    >
                        Test Voice AI Response &rarr;
                    </button>
                </div>

                @if($testQuoteResult)
                    <div class="mt-4 p-4 rounded-xl bg-slate-950 border {{ $testQuoteResult['status'] === 'refused' ? 'border-rose-500/40 text-rose-300' : 'border-emerald-500/40 text-emerald-300' }} space-y-2 text-xs">
                        <div class="flex items-center justify-between font-bold">
                            <span>Status: {{ strtoupper($testQuoteResult['status']) }}</span>
                            @if(isset($testQuoteResult['refusal_code']))
                                <span class="font-mono text-[10px] text-rose-400">[{{ $testQuoteResult['refusal_code'] }}]</span>
                            @endif
                        </div>

                        @if($testQuoteResult['status'] === 'refused')
                            <p class="text-[11px] text-slate-300">
                                🛡️ Refusal Guardrail: {{ $testQuoteResult['message'] ?? 'Sample price cannot be quoted to customer channel until confirmed by business owner.' }}
                            </p>
                        @else
                            <div class="space-y-1 text-slate-200">
                                <div>Service: <strong class="text-white">{{ $testQuoteResult['service_name'] ?? $testQuery }}</strong></div>
                                <div>Price: <strong class="text-emerald-400 font-mono">${{ number_format(($testQuoteResult['price_cents'] ?? 0) / 100, 2) }}</strong></div>
                                <div class="text-[11px] text-slate-400">Tax Rate: {{ $testQuoteResult['tax_rate_pct'] ?? 8.25 }}%</div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

