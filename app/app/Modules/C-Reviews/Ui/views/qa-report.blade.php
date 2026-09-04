<div class="max-w-6xl mx-auto space-y-6 pb-12 w-full p-4 relative" wire:loading.class="opacity-50 pointer-events-none">
    <div wire:loading class="absolute inset-0 z-50 flex items-center justify-center">
        <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
        <div>
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                QA Report
                @if($isSample)
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">SAMPLE</span>
                @endif
            </h1>
            <p class="text-sm text-slate-400">Triage split and action metrics.</p>
        </div>
        <div class="flex items-center gap-1.5 bg-slate-900 rounded-lg p-1 border border-slate-800">
            <button wire:click="setDays(7)" class="px-3 py-1 text-xs font-medium rounded-md {{ $days === 7 ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white' }}">7 Days</button>
            <button wire:click="setDays(30)" class="px-3 py-1 text-xs font-medium rounded-md {{ $days === 30 ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white' }}">30 Days</button>
            <button wire:click="setDays(90)" class="px-3 py-1 text-xs font-medium rounded-md {{ $days === 90 ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white' }}">90 Days</button>
        </div>
    </div>
    
    @if($actionNotice)
        <div class="p-4 rounded-xl text-sm font-medium border shadow-lg {{ $noticeType === 'error' ? 'bg-rose-950/50 border-rose-900 text-rose-300' : ($noticeType === 'warning' ? 'bg-amber-950/50 border-amber-900 text-amber-300' : 'bg-emerald-950/50 border-emerald-900 text-emerald-300') }}">
            {{ $actionNotice }}
            <button wire:click="$set('actionNotice', null)" class="float-right text-current opacity-70 hover:opacity-100">&times;</button>
        </div>
    @endif

    @if($isEmpty)
        <div class="p-12 rounded-2xl border border-dashed border-slate-800 text-center space-y-4">
            <p class="text-slate-400">Nothing to report yet — the first review request goes out when a job completes.</p>
            <div class="flex items-center justify-center gap-4">
                <a href="/reviews-qa-requests" class="text-sm font-semibold text-purple-400 hover:text-purple-300 transition">Go to Requests &rarr;</a>
                <button wire:click="toggleSample" class="text-sm font-semibold text-amber-400 hover:text-amber-300 transition">Show Sample Data</button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div wire:click="selectDrilldown('requests_sent')" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'requests_sent' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-slate-400 mb-1 group-hover:text-purple-300 transition">Requests Sent</div>
                <div class="text-3xl font-bold text-white">{{ $requestsSent }}</div>
            </div>
            
            <div wire:click="selectDrilldown('reviews_received')" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'reviews_received' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-slate-400 mb-1 group-hover:text-purple-300 transition">Reviews Received</div>
                <div class="text-3xl font-bold text-white">{{ $reviewsReceived }}</div>
            </div>
            
            <div wire:click="selectDrilldown('public_path')" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'public_path' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-emerald-500/80 mb-1 group-hover:text-emerald-400 transition">Public Path</div>
                <div class="text-3xl font-bold text-white">{{ $publicPath }}</div>
            </div>
            
            <div wire:click="selectDrilldown('internal_qa')" data-tile="internal_qa" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'internal_qa' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-rose-500/80 mb-1 group-hover:text-rose-400 transition">Triaged Internal</div>
                <div class="text-3xl font-bold text-white">{{ $internalQa }}</div>
            </div>
            
            <div wire:click="selectDrilldown('replies_published')" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'replies_published' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-slate-400 mb-1 group-hover:text-purple-300 transition">Replies Published</div>
                <div class="text-3xl font-bold text-white">{{ $repliesPublished }}</div>
            </div>
            
            <div wire:click="selectDrilldown('replies_drafted')" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'replies_drafted' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-amber-500/80 mb-1 group-hover:text-amber-400 transition">Drafted to Inbox</div>
                <div class="text-3xl font-bold text-white">{{ $repliesDrafted }}</div>
            </div>
            
            <div wire:click="selectDrilldown('open_tickets_sla')" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'open_tickets_sla' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-slate-400 mb-1 group-hover:text-purple-300 transition">Tickets in SLA</div>
                <div class="text-3xl font-bold text-white">{{ $openTicketsSla }}</div>
            </div>
            
            <div wire:click="selectDrilldown('breached_tickets')" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'breached_tickets' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-rose-500/80 mb-1 group-hover:text-rose-400 transition">Breached Tickets</div>
                <div class="text-3xl font-bold text-rose-500">{{ $breachedTickets }}</div>
            </div>
        </div>
        
        @if($isSample)
            <div class="text-center mt-4">
                <button wire:click="toggleSample" class="text-sm font-semibold text-amber-400 hover:text-amber-300 transition">Exit Sample Mode</button>
            </div>
        @endif

        @if($drilldown && !$isSample)
            <div class="mt-8 pt-6 border-t border-slate-800 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Drilldown Details</h3>
                    <button wire:click="selectDrilldown('')" class="text-xs text-slate-400 hover:text-white">Close</button>
                </div>
                
                <div class="space-y-2">
                    @forelse($drilldownRows as $row)
                        <div class="p-3 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs text-slate-300">
                                    @if(!empty($row->is_request))
                                        Request #{{ $row->id }} - {{ $row->platform }} ({{ $row->rating ?? 'no rating' }}) - {{ $row->status }}
                                    @elseif(!empty($row->is_reply))
                                        Reply #{{ $row->id }} for Request #{{ $row->review_request_id }} - {{ $row->status }}
                                    @elseif(!empty($row->is_ticket))
                                        Ticket #{{ $row->id }} - {{ $row->status }} (SLA: {{ $row->sla_due_at->diffForHumans() }})
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if(!empty($row->is_request))
                                    @if(!$row->ticket && $row->rating !== null && $row->rating < $threshold)
                                        <button wire:click="escalateToQa({{ $row->id }})" class="px-2 py-1 rounded bg-rose-600/20 text-rose-300 text-[10px] font-medium hover:bg-rose-600/30">Escalate to QA</button>
                                    @endif
                                    <a href="/reviews-qa-requests" class="px-2 py-1 rounded bg-slate-800 text-slate-300 text-[10px] font-medium hover:bg-slate-700">Open</a>
                                @endif
                                @if(!empty($row->is_ticket))
                                    @if($drilldown === 'breached_tickets' || $row->status === 'open')
                                        <button wire:click="resolveTicket({{ $row->id }})" class="px-2 py-1 rounded bg-emerald-600/20 text-emerald-300 text-[10px] font-medium hover:bg-emerald-600/30">Resolve</button>
                                    @endif
                                    <a href="/tickets/{{ $row->id }}" class="px-2 py-1 rounded bg-slate-800 text-slate-300 text-[10px] font-medium hover:bg-slate-700">Open</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-500 text-center py-4">No rows in this segment.</div>
                    @endforelse
                </div>
            </div>
        @endif
    @endif
</div>
