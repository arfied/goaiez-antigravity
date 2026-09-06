<div class="max-w-6xl mx-auto space-y-4 pb-12 w-full p-4 relative" wire:loading.class="opacity-50 pointer-events-none">
    <div wire:loading><x-ui.skeleton label="Checking your reviews…" /></div>

    <!-- Header & Action Notice -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
        <div>
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                Reviews & QA
                @if($isSample)
                    <x-ui.status-pill state="attention" label="SAMPLE" />
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
        @if($noticeType === 'error')
            <x-ui.error-panel heading="Error">{{ $actionNotice }}</x-ui.error-panel>
        @else
            <x-ui.attention-card :state="$noticeType === 'warning' ? 'attention' : 'ok'" heading="Notice">{{ $actionNotice }}</x-ui.attention-card>
        @endif
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Composer Panel -->
        <div class="lg:col-span-1 space-y-4">
            <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-4 shadow-xl">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"></div>
                    <h2 class="text-sm font-bold text-white">Send New Ask</h2>
                </div>

                <form wire:submit="sendRequest" class="space-y-4">
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

                    <x-ui.submit target="sendRequest" busy="Sending…">Send the ask</x-ui.submit>
                </form>
            </div>
        </div>

        <!-- Ingested Reviews List & AI Response Panel -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Filter Bar -->
            <div class="flex items-center justify-between gap-2 overflow-x-auto pb-1">
                <div class="flex items-center gap-1.5">
                    <x-ui.button size="default" :variant="$filter === 'all' ? 'primary' : 'quiet'" wire:click="$set('filter', 'all')">All ({{ $totalCount }})</x-ui.button>
                    <x-ui.button size="default" :variant="$filter === 'public' ? 'primary' : 'quiet'" wire:click="$set('filter', 'public')">Public Path ({{ $publicCount }})</x-ui.button>
                    <x-ui.button size="default" :variant="$filter === 'internal' ? 'primary' : 'quiet'" wire:click="$set('filter', 'internal')">Internal QA ({{ $internalCount }})</x-ui.button>
                    <x-ui.button size="default" :variant="$filter === 'google' ? 'primary' : 'quiet'" wire:click="$set('filter', 'google')">Google</x-ui.button>
                    <x-ui.button size="default" :variant="$filter === 'yelp' ? 'primary' : 'quiet'" wire:click="$set('filter', 'yelp')">Yelp</x-ui.button>
                    <x-ui.button size="default" :variant="$filter === 'facebook' ? 'primary' : 'quiet'" wire:click="$set('filter', 'facebook')">Facebook</x-ui.button>
                    <x-ui.button size="default" :variant="$filter === 'bbb' ? 'primary' : 'quiet'" wire:click="$set('filter', 'bbb')">BBB</x-ui.button>
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
                            <div class="absolute top-2 right-2"><x-ui.status-pill state="attention" label="SAMPLE" /></div>
                        @endif
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <x-ui.status-pill state="unknown" :label="$r->platform" />
                                @if($r->rating)
                                    <div class="text-amber-400 font-bold text-sm tracking-wider">
                                        {{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', max(0, 5 - $r->rating)) }}
                                    </div>
                                @else
                                    <div class="text-slate-500 font-bold text-sm">No rating yet</div>
                                @endif
                            </div>
                            
                            @php
                                $statusState = 'unknown';
                                $statusLabel = $r->status;
                                if ($r->status === 'published_public') {
                                    $statusState = 'ok';
                                    $statusLabel = 'Public';
                                } elseif ($r->status === 'triaged_internal') {
                                    $statusState = 'alert';
                                    $statusLabel = 'Internal QA';
                                }
                            @endphp
                            <x-ui.status-pill :state="$statusState" :label="$statusLabel" />
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
                                    <x-ui.button size="default" wire:click="resendAsk({{ $r->id }})">Resend ask</x-ui.button>
                                @endif

                                @if($r->rating !== null && $r->rating < $threshold)
                                    @if($r->ticket)
                                        <span class="text-[11px] text-blue-300">
                                            Ticket #{{ $r->ticket->id }} &middot; {{ $r->ticket->status }} &middot; due {{ $r->ticket->sla_due_at ? $r->ticket->sla_due_at->diffForHumans() : 'N/A' }}
                                        </span>
                                    @else
                                        <x-ui.button size="default" wire:click="escalateToQa({{ $r->id }})">Escalate to QA</x-ui.button>
                                    @endif
                                @elseif($r->rating !== null && $r->rating >= $threshold)
                                    <x-ui.button size="default" wire:click="selectReview({{ $r->id }})">Reply</x-ui.button>
                                @endif
                            </div>
                        </div>

                        <!-- Embedded Response Drafter if Selected -->
                        @if($selectedReviewId === $r->id)
                            <div class="mt-3 p-4 rounded-xl bg-slate-950 border border-purple-500/40 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-white">AI Response Generator</span>
                                    <x-ui.button size="default" variant="quiet" wire:click="unselectReview()">Cancel</x-ui.button>
                                </div>
                                <div class="text-xs text-slate-400 italic bg-slate-900 p-2 rounded">
                                    "{{ $r->review_text }}"
                                </div>
                                <form wire:submit="publishReply" class="space-y-3">
                                    <textarea wire:model="replyDraft" rows="3" placeholder="Draft reply..." class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white"></textarea>
                                    
                                    <div class="flex items-center justify-between">
                                        <label class="flex items-center gap-2 text-xs text-slate-400">
                                            <input type="checkbox" wire:model="isSarcasticOrAmbiguous" class="rounded bg-slate-900 border-slate-700 text-purple-600" />
                                            Flag as Sarcastic / Ambiguous (Draft to Inbox)
                                        </label>
                                        <x-ui.submit target="publishReply" busy="Publishing…">Publish reply</x-ui.submit>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    @if($totalCount === 0)
                        <x-ui.empty-state heading="No review requests yet" action="Show a sample" target="toggleSample">The first ask goes out when a job completes, or send one now.</x-ui.empty-state>
                    @else
                        <x-ui.empty-state heading="Nothing under this filter">Every request is in another lane — pick another filter.</x-ui.empty-state>
                    @endif
                @endforelse
            </div>
        </div>
    </div>
</div>
