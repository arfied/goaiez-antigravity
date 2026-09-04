<div class="max-w-6xl mx-auto space-y-4 pb-12 w-full p-4 relative" wire:loading.class="opacity-50 pointer-events-none">
    <div wire:loading class="absolute inset-0 z-50 flex items-center justify-center">
        <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Header & Action Notice -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
        <div>
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                Reviews & QA
                @if($isSample)
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">SAMPLE</span>
                @endif
            </h1>
            <p class="text-sm text-slate-400">Manage review requests, replies, and QA tickets.</p>
        </div>
        <div class="flex items-center gap-4 text-sm text-slate-400">
            <div class="flex flex-col text-right">
                <span>Public Threshold: <strong class="text-white">{{ $threshold }}★</strong></span>
                <span>QA Tickets to: <strong class="text-white">{{ $ticketRecipient }}</strong></span>
            </div>
        </div>
    </div>

    @if($actionNotice)
        <div class="p-4 rounded-xl text-sm font-medium border shadow-lg {{ $noticeType === 'error' ? 'bg-rose-950/50 border-rose-900 text-rose-300' : ($noticeType === 'warning' ? 'bg-amber-950/50 border-amber-900 text-amber-300' : 'bg-emerald-950/50 border-emerald-900 text-emerald-300') }}">
            {{ $actionNotice }}
            <button wire:click="$set('actionNotice', null)" class="float-right text-current opacity-70 hover:opacity-100">&times;</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Composer Panel -->
        <div class="lg:col-span-1 space-y-4">
            <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-4 shadow-xl">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"></div>
                    <h2 class="text-sm font-bold text-white">Send New Ask</h2>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Platform</label>
                    <select wire:model="platform" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                        <option value="google">Google</option>
                        <option value="yelp">Yelp</option>
                        <option value="facebook">Facebook</option>
                        <option value="bbb">BBB</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Prompt Template</label>
                    <textarea 
                        wire:model="promptTemplate" 
                        rows="3" 
                        class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500"
                    ></textarea>
                </div>

                <button 
                    wire:click="sendRequest" 
                    class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-semibold text-xs shadow-lg shadow-purple-600/30 transition flex items-center justify-center gap-2"
                >
                    Dispatch Request &rarr;
                </button>
            </div>
        </div>

        <!-- Ingested Reviews List & AI Response Panel -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Filter Bar -->
            <div class="flex items-center justify-between gap-2 overflow-x-auto pb-1">
                <div class="flex items-center gap-1.5">
                    <button wire:click="$set('filter', 'all')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === 'all' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        All ({{ $totalCount }})
                    </button>
                    <button wire:click="$set('filter', 'public')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === 'public' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        Public Path ({{ $publicCount }})
                    </button>
                    <button wire:click="$set('filter', 'internal')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === 'internal' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        Internal QA ({{ $internalCount }})
                    </button>
                    <button wire:click="$set('filter', 'google')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === 'google' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        Google
                    </button>
                    <button wire:click="$set('filter', 'yelp')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === 'yelp' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        Yelp
                    </button>
                    <button wire:click="$set('filter', 'facebook')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === 'facebook' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        Facebook
                    </button>
                    <button wire:click="$set('filter', 'bbb')" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $filter === 'bbb' ? 'bg-purple-600 text-white' : 'bg-slate-800/80 text-slate-400 hover:bg-slate-800' }}">
                        BBB
                    </button>
                </div>
                <div class="text-xs text-slate-400 font-medium whitespace-nowrap">
                    Avg Rating: <span class="text-white">{{ $avgRating }}</span>
                </div>
            </div>

            <!-- Reviews Feed -->
            <div class="space-y-3">
                @forelse($requests as $r)
                    <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-3 hover:border-slate-700 transition relative">
                        @if($isSample)
                            <div class="absolute top-2 right-2 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">SAMPLE</div>
                        @endif
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold font-mono uppercase {{ $r->platform === 'google' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'bg-purple-500/20 text-purple-400 border border-purple-500/30' }}">
                                    {{ $r->platform }}
                                </span>
                                @if($r->rating)
                                    <div class="text-amber-400 font-bold text-sm tracking-wider">
                                        {{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', max(0, 5 - $r->rating)) }}
                                    </div>
                                @else
                                    <div class="text-slate-500 font-bold text-sm">No rating yet</div>
                                @endif
                            </div>
                            
                            <span class="text-[11px] px-2.5 py-0.5 rounded-full font-semibold {{ $r->status === 'published_public' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : ($r->status === 'triaged_internal' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-slate-800 text-slate-400') }}">
                                {{ $r->status }}
                            </span>
                        </div>

                        <div class="text-xs text-slate-400">
                            Customer: <strong class="text-slate-200">{{ $r->customer_name ?? 'Unknown' }}</strong>
                        </div>

                        <p class="text-xs text-slate-200 leading-relaxed">
                            "{{ $r->review_text ?? 'Review requested — awaiting customer response.' }}"
                        </p>

                        <!-- Action Bar for this Review -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-800/80">
                            <span class="text-[10px] text-slate-500 font-mono">Review ID #{{ $r->id }}</span>
                            <div class="flex items-center gap-2">
                                @if($r->status === 'sent' && !$r->rating)
                                    <button wire:click="resendAsk({{ $r->id }})" class="px-2.5 py-1 rounded bg-slate-600/20 hover:bg-slate-600/30 text-slate-300 border border-slate-600/40 text-[11px] font-medium transition">
                                        Resend ask
                                    </button>
                                @endif

                                @if($r->rating !== null && $r->rating < $threshold)
                                    @if($r->ticket)
                                        <span class="text-[11px] text-blue-300">
                                            Ticket #{{ $r->ticket->id }} &middot; {{ $r->ticket->status }} &middot; due {{ $r->ticket->sla_due_at ? $r->ticket->sla_due_at->diffForHumans() : 'N/A' }}
                                        </span>
                                    @else
                                        <button wire:click="escalateToQa({{ $r->id }})" class="px-2.5 py-1 rounded bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-600/40 text-[11px] font-medium transition">
                                            Escalate to QA
                                        </button>
                                    @endif
                                @elseif($r->rating !== null && $r->rating >= $threshold)
                                    <button wire:click="selectReview({{ $r->id }})" class="px-2.5 py-1 rounded bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 border border-purple-600/40 text-[11px] font-medium transition">
                                        Reply
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Embedded Response Drafter if Selected -->
                        @if($selectedReviewId === $r->id)
                            <div class="mt-3 p-4 rounded-xl bg-slate-950 border border-purple-500/40 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-white">AI Response Generator</span>
                                    <button wire:click="unselectReview()" class="text-xs text-slate-400 hover:text-white">&times; Cancel</button>
                                </div>
                                <div class="text-xs text-slate-400 italic bg-slate-900 p-2 rounded">
                                    "{{ $r->review_text }}"
                                </div>
                                <textarea wire:model="replyDraft" rows="3" placeholder="Draft reply..." class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white"></textarea>
                                
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-xs text-slate-400">
                                        <input type="checkbox" wire:model="isSarcasticOrAmbiguous" class="rounded bg-slate-900 border-slate-700 text-purple-600" />
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
                    @if($isSample)
                        <div class="p-8 rounded-xl border border-dashed border-slate-800 text-center text-xs text-slate-500">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">SAMPLE</span>
                            No sample reviews generated yet.
                        </div>
                    @elseif($totalCount === 0)
                        <div class="p-8 rounded-xl border border-dashed border-slate-800 text-center text-xs text-slate-500">
                            No review requests yet. The first ask goes out when a job completes (autopilot), or send one now.
                            <br><br>
                            <button wire:click="toggleSample" class="text-purple-400 hover:underline">Show me what this looks like</button>
                        </div>
                    @else
                        <div class="p-8 rounded-xl border border-dashed border-slate-800 text-center text-xs text-slate-500">
                            No reviews found for this filter.
                        </div>
                    @endif
                @endforelse
            </div>
        </div>
    </div>
</div>
