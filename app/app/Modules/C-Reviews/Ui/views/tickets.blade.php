<div class="max-w-4xl mx-auto space-y-6 pb-12 w-full p-4 relative" wire:loading.class="opacity-50 pointer-events-none">
    <div wire:loading class="absolute inset-0 z-50 flex items-center justify-center">
        <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
        <div>
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                QA Tickets
                @if($isSample)
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">SAMPLE</span>
                @endif
            </h1>
            <p class="text-sm text-slate-400">Internal triage for low reviews and service failures.</p>
        </div>
        
        <div class="flex items-center gap-1.5 bg-slate-900 rounded-lg p-1 border border-slate-800">
            <button wire:click="$set('tab', 'open')" class="px-3 py-1 text-xs font-medium rounded-md {{ $tab === 'open' ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white' }}">Open</button>
            <button wire:click="$set('tab', 'resolved')" class="px-3 py-1 text-xs font-medium rounded-md {{ $tab === 'resolved' ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white' }}">Resolved</button>
        </div>
    </div>

    @if($actionNotice)
        <div class="p-4 rounded-xl text-sm font-medium border shadow-lg {{ $noticeType === 'error' ? 'bg-rose-950/50 border-rose-900 text-rose-300' : ($noticeType === 'warning' ? 'bg-amber-950/50 border-amber-900 text-amber-300' : 'bg-emerald-950/50 border-emerald-900 text-emerald-300') }}">
            {{ $actionNotice }}
            <button wire:click="$set('actionNotice', null)" class="float-right text-current opacity-70 hover:opacity-100">&times;</button>
        </div>
    @endif

    @if($isEmpty && $tab === 'open')
        <div class="p-12 rounded-2xl border border-dashed border-slate-800 text-center space-y-4">
            <p class="text-slate-400">No open tickets — every recent review met the threshold.</p>
            <div class="flex items-center justify-center gap-4">
                <span class="text-sm font-semibold text-slate-400">View QA Report &rarr;</span>
                <button wire:click="toggleSample" class="text-sm font-semibold text-amber-400 hover:text-amber-300 transition">Show Sample Data</button>
            </div>
        </div>
    @elseif($isSample)
        <div class="p-8 rounded-xl border border-dashed border-slate-800 text-center text-xs text-slate-500">
            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">SAMPLE</span>
            No sample tickets generated yet. Exit sample mode to see live data.
            <div class="mt-4"><button wire:click="toggleSample" class="text-sm font-semibold text-amber-400 hover:text-amber-300 transition">Exit Sample Mode</button></div>
        </div>
    @else
        <div class="space-y-4">
            @forelse($tickets as $t)
                <div class="rounded-xl border {{ $t->is_breached ? 'border-rose-500/50 bg-rose-950/20' : 'border-slate-800 bg-slate-900/60' }} p-4 space-y-3 transition relative">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-white text-sm">Ticket #{{ $t->id }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $t->status === 'resolved' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30' }}">
                                    {{ $t->status }}
                                </span>
                                @if($t->is_breached)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-500 text-white animate-pulse">BREACHED</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-400">
                                Customer: <strong class="text-slate-200">{{ $t->customer_name }}</strong>
                            </div>
                        </div>
                        
                        <div class="text-right flex flex-col text-xs text-slate-400">
                            <span>Arrived: {{ $t->arrived_at ? $t->arrived_at->format('M j, g:i A') : 'N/A' }}</span>
                            @if($t->status === 'resolved')
                                <span>Resolved: {{ $t->resolved_at ? $t->resolved_at->format('M j, g:i A') : 'N/A' }}</span>
                                <span class="text-emerald-400">Time to fix: {{ $t->time_to_fix }}</span>
                            @else
                                <span class="{{ $t->is_breached ? 'text-rose-400 font-bold' : 'text-slate-300' }}">
                                    Due: {{ $t->sla_due_at ? $t->sla_due_at->format('M j, g:i A') : 'N/A' }}
                                    ({{ $t->sla_due_at ? $t->sla_due_at->diffForHumans() : '' }})
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($t->review_rating !== null)
                        <div class="p-3 rounded-lg bg-slate-950/50 border border-slate-800/50">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Review Snippet</span>
                                <div class="text-amber-400 font-bold text-xs tracking-wider">
                                    {{ str_repeat('★', $t->review_rating) }}{{ str_repeat('☆', max(0, 5 - $t->review_rating)) }}
                                </div>
                            </div>
                            <p class="text-xs text-slate-300 italic">"{{ $t->review_text ?? 'No text provided' }}"</p>
                        </div>
                    @endif

                    @if($resolvingTicketId === $t->id)
                        <div class="mt-3 p-4 rounded-xl bg-slate-950 border border-emerald-500/40 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-white">Resolve Ticket #{{ $t->id }}</span>
                                <button wire:click="cancelResolve" class="text-xs text-slate-400 hover:text-white">&times; Cancel</button>
                            </div>
                            <textarea wire:model="resolutionNotes" rows="2" placeholder="Resolution notes..." class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"></textarea>
                            <div class="text-right">
                                <button wire:click="resolve({{ $t->id }}, $wire.resolutionNotes)" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition">
                                    Mark Resolved
                                </button>
                            </div>
                        </div>
                    @elseif($t->status !== 'resolved')
                        <div class="flex items-center justify-end pt-2 border-t border-slate-800/50">
                            <button wire:click="startResolve({{ $t->id }})" class="px-3 py-1.5 rounded-lg bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-600/40 text-xs font-medium transition">
                                Resolve
                            </button>
                        </div>
                    @else
                        @if($t->resolution_notes)
                            <div class="text-xs text-slate-400">
                                <span class="font-semibold">Resolution Notes:</span> {{ $t->resolution_notes }}
                            </div>
                        @endif
                    @endif
                </div>
            @empty
                <div class="p-8 rounded-xl border border-dashed border-slate-800 text-center text-xs text-slate-500">
                    No tickets found in this tab.
                </div>
            @endforelse
        </div>
    @endif
</div>
