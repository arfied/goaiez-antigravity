<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header & Stats Overview -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 backdrop-blur-sm shadow-xl space-y-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                        Module C-Reviews · P-110 Compliant Engine
                    </span>
                    <span class="text-xs text-slate-400">Tenant Business ID: <strong class="text-white">#{{ $businessId }}</strong></span>
                </div>
                <h1 class="text-2xl font-bold text-white mt-1">Reputation Engine & Review Hub</h1>
                <p class="text-xs text-slate-400">Autonomous review ingestion, strict non-incentivized request linting, 4-5★ auto-replies, and 1-3★ QA ticket protection.</p>
            </div>

            <!-- Quick Add Simulation Review -->
            <div class="flex items-center gap-2">
                <button wire:click="addSampleReview('google', 5, 'Fastest dispatch in town! Arrived in under 30 mins and fixed the pipe.')" class="px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-medium transition">
                    + Ingest 5★ (Google)
                </button>
                <button wire:click="addSampleReview('yelp', 2, 'Good technical work, but scheduling was delayed by an hour.')" class="px-3 py-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 text-xs font-medium transition">
                    + Ingest 2★ (Yelp)
                </button>
            </div>
        </div>

        @if($actionNotice)
            <div class="p-3.5 rounded-xl text-xs flex items-center justify-between {{ $noticeType === 'error' ? 'bg-rose-500/10 border border-rose-500/30 text-rose-300' : ($noticeType === 'warning' ? 'bg-amber-500/10 border border-amber-500/30 text-amber-300' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-300') }}">
                <span class="font-medium">{{ $actionNotice }}</span>
                <button wire:click="$set('actionNotice', null)" class="text-slate-400 hover:text-white font-bold ml-3">&times;</button>
            </div>
        @endif

        <!-- Metrics Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                <div class="text-xs text-slate-400">Total Ingested</div>
                <div class="text-2xl font-bold text-white font-mono mt-1">{{ $totalCount }}</div>
                <div class="text-[11px] text-purple-400 mt-1">Multi-Channel Sync</div>
            </div>
            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                <div class="text-xs text-slate-400">Average Rating</div>
                <div class="text-2xl font-bold text-amber-400 font-mono mt-1">{{ $avgRating }} ★</div>
                <div class="text-[11px] text-slate-400 mt-1">Google & Yelp Aggregated</div>
            </div>
            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                <div class="text-xs text-slate-400">5-Star Reviews</div>
                <div class="text-2xl font-bold text-emerald-400 font-mono mt-1">{{ $fiveStarCount }}</div>
                <div class="text-[11px] text-emerald-400 mt-1">Auto-Replied</div>
            </div>
            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                <div class="text-xs text-slate-400">Triaged QA Tickets</div>
                <div class="text-2xl font-bold text-rose-400 font-mono mt-1">{{ $qaCount }}</div>
                <div class="text-[11px] text-rose-400 mt-1">24h SLA Active</div>
            </div>
        </div>
    </div>

    <!-- Review Dispatch Form (P-110 Lint Enforced) & Ingestion Table -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Send Review Request -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 backdrop-blur-sm space-y-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
                Send Review Request
            </h2>
            <p class="text-xs text-slate-400 leading-relaxed">
                Linted for §37.3 compliance: <strong>no staff mentions ("mention Dave")</strong> and <strong>no incentive gating ("10% off for review")</strong>.
            </p>

            <div class="space-y-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Target Platform</label>
                    <select wire:model="platform" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white">
                        <option value="google">Google Business Profile</option>
                        <option value="yelp">Yelp</option>
                        <option value="facebook">Facebook Reviews</option>
                        <option value="bbb">Better Business Bureau</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Prompt Template</label>
                    <textarea 
                        wire:model.defer="promptTemplate" 
                        rows="3" 
                        class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500"
                    ></textarea>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <button wire:click="$set('promptTemplate', 'Please leave a review and mention Dave for 10% off!')" class="text-[10px] text-rose-400 hover:underline">
                        Test Banned Prompt
                    </button>
                    <span class="text-slate-600">·</span>
                    <button wire:click="$set('promptTemplate', 'How did the repair go? We would love your feedback.')" class="text-[10px] text-emerald-400 hover:underline">
                        Valid Compliant Prompt
                    </button>
                </div>

                <button 
                    wire:click="sendRequest" 
                    class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-semibold text-xs shadow-lg shadow-purple-600/30 transition flex items-center justify-center gap-2"
                >
                    Dispatch Request &rarr;
                </button>
            </div>
        </div>

        <!-- Ingested Reviews List & AI Reply Panel -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Filter Bar -->
            <div class="flex items-center justify-between gap-2 overflow-x-auto pb-1">
                <div class="flex items-center gap-1.5">
                    <button wire:click="$set('filter', 'all')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === 'all' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        All ({{ $totalCount }})
                    </button>
                    <button wire:click="$set('filter', '5star')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === '5star' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        5-Star Only
                    </button>
                    <button wire:click="$set('filter', '1to3star')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === '1to3star' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        1-3★ QA Escalations
                    </button>
                    <button wire:click="$set('filter', 'google')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === 'google' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        Google
                    </button>
                </div>
            </div>

            <!-- Reviews Feed -->
            <div class="space-y-3">
                @forelse($requests as $r)
                    <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-3 hover:border-slate-700 transition">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold font-mono uppercase {{ $r->platform === 'google' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30' }}">
                                    {{ $r->platform }}
                                </span>
                                <div class="text-amber-400 font-bold text-sm tracking-wider">
                                    {{ str_repeat('★', $r->rating ?? 5) }}{{ str_repeat('☆', max(0, 5 - ($r->rating ?? 5))) }}
                                </div>
                            </div>
                            
                            <span class="text-[11px] px-2.5 py-0.5 rounded-full font-semibold {{ $r->status === 'published_public' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : ($r->status === 'triaged_internal' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-slate-800 text-slate-400') }}">
                                {{ $r->status }}
                            </span>
                        </div>

                        <p class="text-xs text-slate-200 leading-relaxed">
                            "{{ $r->review_text ?? 'Review requested — awaiting customer response.' }}"
                        </p>

                        <!-- Action Bar for this Review -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-800/80">
                            <span class="text-[10px] text-slate-500 font-mono">Review ID #{{ $r->id }}</span>
                            <div class="flex items-center gap-2">
                                @if($r->rating && $r->rating <= 3)
                                    <button wire:click="escalateToQa({{ $r->id }})" class="px-2.5 py-1 rounded bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-600/40 text-[11px] font-medium transition">
                                        Escalate QA SLA
                                    </button>
                                @endif
                                <button wire:click="selectReview({{ $r->id }})" class="px-2.5 py-1 rounded bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 border border-purple-600/40 text-[11px] font-medium transition">
                                    Draft AI Response
                                </button>
                            </div>
                        </div>

                        <!-- Embedded Response Drafter if Selected -->
                        @if($selectedReviewId === $r->id)
                            <div class="mt-3 p-4 rounded-xl bg-slate-950 border border-purple-500/40 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-white">AI Response Generator</span>
                                    <button wire:click="unselectReview()" class="text-xs text-slate-400 hover:text-white">&times; Cancel</button>
                                </div>
                                <textarea wire:model.defer="replyDraft" rows="3" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white"></textarea>
                                
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-xs text-slate-400">
                                        <input type="checkbox" wire:model.defer="isSarcasticOrAmbiguous" class="rounded bg-slate-900 border-slate-700 text-purple-600" />
                                        Flag as Sarcastic / Ambiguous (Draft to Inbox)
                                    </label>
                                    <button wire:click="publishReply" class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-semibold text-xs transition">
                                        Execute Action &rarr;
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-8 rounded-xl border border-dashed border-slate-800 text-center text-xs text-slate-500">
                        No reviews found for this filter.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

