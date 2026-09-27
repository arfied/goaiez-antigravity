<div class="max-w-6xl mx-auto space-y-6 pb-12 w-full p-4 relative" wire:loading.class="opacity-50 pointer-events-none">
    <div wire:loading class="absolute inset-0 z-50 flex items-center justify-center">
        <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between pb-2 border-b border-rule">
        <div>
            <h2 class="text-lg font-bold text-ink flex items-center gap-2">
                QA report
                @if($isSample)
                    <x-ui.status-pill state="attention" label="SAMPLE" />
                @endif
            </h2>
            <p class="text-sm text-ink-2">Triage split and action metrics.</p>
        </div>
        <div class="flex items-center gap-1.5 bg-surface rounded-lg p-1 border border-rule">
            <x-ui.button size="default" :variant="$days === 7 ? 'primary' : 'quiet'" wire:click="setDays(7)">7 Days</x-ui.button>
            <x-ui.button size="default" :variant="$days === 30 ? 'primary' : 'quiet'" wire:click="setDays(30)">30 Days</x-ui.button>
            <x-ui.button size="default" :variant="$days === 90 ? 'primary' : 'quiet'" wire:click="setDays(90)">90 Days</x-ui.button>
        </div>
    </div>
    
    @if($actionNotice)
        @if($noticeType === 'error')
            <x-ui.error-panel heading="Error">{{ $actionNotice }}</x-ui.error-panel>
        @else
            <x-ui.attention-card :state="$noticeType === 'warning' ? 'attention' : 'ok'" heading="Notice">{{ $actionNotice }}</x-ui.attention-card>
        @endif
    @endif

    @if($isEmpty)
        <x-ui.empty-state heading="Nothing to report yet" action="Show a sample" target="toggleSample">The first review request goes out when a job completes.</x-ui.empty-state>
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div wire:click="selectDrilldown('requests_sent')" data-tile="requests_sent" data-value="{{ $requestsSent }}" class="p-5 rounded-2xl bg-surface border border-rule cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'requests_sent' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-ink-2 mb-1 group-hover:text-purple-300 transition">Requests Sent</div>
                <div class="text-3xl font-bold text-ink">{{ $requestsSent }}</div>
            </div>
            
            <div wire:click="selectDrilldown('reviews_received')" data-tile="reviews_received" data-value="{{ $reviewsReceived }}" class="p-5 rounded-2xl bg-surface border border-rule cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'reviews_received' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-ink-2 mb-1 group-hover:text-purple-300 transition">Reviews Received</div>
                <div class="text-3xl font-bold text-ink">{{ $reviewsReceived }}</div>
            </div>
            
            <div wire:click="selectDrilldown('public_path')" data-tile="public_path" data-value="{{ $publicPath }}" class="p-5 rounded-2xl bg-surface border border-rule cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'public_path' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-emerald-500/80 mb-1 group-hover:text-emerald-400 transition">Public Path</div>
                <div class="text-3xl font-bold text-ink">{{ $publicPath }}</div>
            </div>
            
            <div wire:click="selectDrilldown('internal_qa')" data-tile="internal_qa" data-value="{{ $internalQa }}" class="p-5 rounded-2xl bg-surface border border-rule cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'internal_qa' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-rose-500/80 mb-1 group-hover:text-rose-400 transition">Triaged Internal</div>
                <div class="text-3xl font-bold text-ink">{{ $internalQa }}</div>
            </div>
            
            <div wire:click="selectDrilldown('replies_published')" data-tile="replies_published" data-value="{{ $repliesPublished }}" class="p-5 rounded-2xl bg-surface border border-rule cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'replies_published' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-ink-2 mb-1 group-hover:text-purple-300 transition">Replies Published</div>
                <div class="text-3xl font-bold text-ink">{{ $repliesPublished }}</div>
            </div>
            
            <div wire:click="selectDrilldown('replies_drafted')" data-tile="replies_drafted" data-value="{{ $repliesDrafted }}" class="p-5 rounded-2xl bg-surface border border-rule cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'replies_drafted' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-amber-500/80 mb-1 group-hover:text-amber-400 transition">Drafted to Inbox</div>
                <div class="text-3xl font-bold text-ink">{{ $repliesDrafted }}</div>
            </div>
            
            <div wire:click="selectDrilldown('open_tickets_sla')" data-tile="open_tickets_sla" data-value="{{ $openTicketsSla }}" class="p-5 rounded-2xl bg-surface border border-rule cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'open_tickets_sla' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-ink-2 mb-1 group-hover:text-purple-300 transition">Tickets in SLA</div>
                <div class="text-3xl font-bold text-ink">{{ $openTicketsSla }}</div>
            </div>
            
            <div wire:click="selectDrilldown('breached_tickets')" data-tile="breached_tickets" data-value="{{ $breachedTickets }}" class="p-5 rounded-2xl bg-surface border border-rule cursor-pointer hover:border-purple-500 transition group {{ $drilldown === 'breached_tickets' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-xs font-medium text-rose-500/80 mb-1 group-hover:text-rose-400 transition">Breached Tickets</div>
                <div class="text-3xl font-bold text-rose-500">{{ $breachedTickets }}</div>
            </div>
        </div>
        
        @if($isSample)
            <div class="text-center mt-4">
                <x-ui.button size="default" variant="secondary" wire:click="toggleSample">Exit sample</x-ui.button>
            </div>
        @endif

        @if($drilldown && !$isSample)
            <div class="mt-8 pt-6 border-t border-rule space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-ink uppercase tracking-wider">Drilldown Details</h3>
                    <x-ui.button size="default" variant="quiet" wire:click="selectDrilldown('')">Close</x-ui.button>
                </div>
                
                <div class="space-y-2">
                    @forelse($drilldownRows as $row)
                        <div class="p-3 rounded-lg bg-surface border border-rule flex items-center justify-between">
                            <div>
                                <div class="text-xs text-ink-2">
                                    @if(!empty($row->is_request))
                                        Request #{{ $row->id }} - {{ $row->platform }} ({{ $row->rating ?? 'no rating' }}) - {{ $row->status }}@if(!empty($row->settled_reason)) ({{ $row->settled_reason }})@endif
                                        <div class="italic text-[10px] mt-1 text-ink-2">"{{ $row->review_text ?? 'no text' }}"</div>
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
                                        <x-ui.button size="default" wire:click="escalateToQa({{ $row->id }})">Escalate to QA</x-ui.button>
                                    @endif
                                @endif
                                @if(!empty($row->is_ticket))
                                    @if($drilldown === 'breached_tickets' || $row->status === 'open')
                                        <x-ui.button size="default" wire:click="resolveTicket({{ $row->id }})">Resolve</x-ui.button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @empty
                        <x-ui.empty-state heading="Nothing in this segment">Pick another tile.</x-ui.empty-state>
                    @endforelse
                </div>
            </div>
        @endif
    @endif
</div>
