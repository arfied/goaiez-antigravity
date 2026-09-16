<div class="max-w-4xl mx-auto space-y-6 pb-12 w-full p-4 relative" wire:loading.class="opacity-50 pointer-events-none">
 <div wire:loading class="absolute inset-0 z-50 flex items-center justify-center">
 <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
 </div>

 <!-- Header -->
 <div class="flex items-center justify-between pb-2 border-b border-rule">
 <div>
 <h2 class="text-lg font-bold text-ink flex items-center gap-2">
 QA tickets
 @if($isSample)
 <x-ui.status-pill state="attention" label="SAMPLE" />
 @endif
 </h2>
 <p class="text-sm text-ink-2">Internal triage for low reviews and service failures.</p>
 </div>
 
 <div class="flex items-center gap-1.5 bg-surface rounded-lg p-1 border border-rule">
 <x-ui.button size="default" :variant="$tab === 'open' ? 'primary' : 'quiet'" wire:click="$set('tab', 'open')">Open</x-ui.button>
 <x-ui.button size="default" :variant="$tab === 'resolved' ? 'primary' : 'quiet'" wire:click="$set('tab', 'resolved')">Resolved</x-ui.button>
 </div>
 </div>

 @if($actionNotice)
 @if($noticeType === 'error')
 <x-ui.error-panel heading="Error">{{ $actionNotice }}</x-ui.error-panel>
 @else
 <x-ui.attention-card :state="$noticeType === 'warning' ? 'attention' : 'ok'" heading="Notice">{{ $actionNotice }}</x-ui.attention-card>
 @endif
 @endif

 @if($isEmpty && $tab === 'open')
 <x-ui.empty-state heading="No open tickets" action="Show a sample" target="toggleSample">Every recent review met the threshold.</x-ui.empty-state>
 @else
 <div class="space-y-4">
 @forelse($tickets as $t)
 <div class="rounded-xl border {{ $t->is_breached ? 'border-rose-500/50 bg-rose-950/20' : 'border-rule bg-surface' }} p-4 space-y-3 transition relative">
 <div class="flex items-start justify-between gap-2">
 <div class="flex flex-col gap-1">
 <div class="flex items-center gap-2">
 <span class="font-bold text-ink text-sm">Ticket #{{ $t->id }}</span>
 <x-ui.status-pill :state="$t->status === 'resolved' ? 'ok' : 'attention'" :label="$t->status === 'resolved' ? 'Resolved' : 'Open'" />
 @if($t->is_breached)
 <x-ui.status-pill state="alert" label="SLA breached" />
 @endif
 </div>
 <div class="text-xs text-ink-2">
 Customer: <strong class="text-ink-2">{{ $t->customer_name }}</strong>
 </div>
 </div>
 
 <div class="text-right flex flex-col text-xs text-ink-2">
 <span>Arrived: {{ $t->arrived_at ? $t->arrived_at->format('M j, g:i A') : 'N/A' }}</span>
 @if($t->status === 'resolved')
 <span>Resolved: {{ $t->resolved_at ? $t->resolved_at->format('M j, g:i A') : 'N/A' }}</span>
 <span class="text-emerald-400">Time to fix: {{ $t->time_to_fix }}</span>
 @else
 <span class="{{ $t->is_breached ? 'text-rose-400 font-bold' : 'text-ink-2' }}">
 Due: {{ $t->sla_due_at ? $t->sla_due_at->format('M j, g:i A') : 'N/A' }}
 ({{ $t->sla_due_at ? $t->sla_due_at->diffForHumans() : '' }})
 </span>
 @endif
 </div>
 </div>

 @if($t->review_rating !== null)
 <div class="p-3 rounded-lg bg-surface border border-rule">
 <div class="flex items-center gap-2 mb-1">
 <span class="text-[10px] text-ink-2 uppercase tracking-wider font-semibold">Review Snippet</span>
 <div class="text-amber-400 font-bold text-xs tracking-wider">
 {{ str_repeat('★', $t->review_rating) }}{{ str_repeat('☆', max(0, 5 - $t->review_rating)) }}
 </div>
 </div>
 <p class="text-xs text-ink-2 italic">"{{ $t->review_text ?? 'No text provided' }}"</p>
 </div>
 @endif

 @if($resolvingTicketId === $t->id)
 <div class="mt-3 p-4 rounded-xl bg-surface border border-emerald-500/40 space-y-3">
 <div class="flex items-center justify-between">
 <span class="text-xs font-bold text-ink">Resolve Ticket #{{ $t->id }}</span>
 <x-ui.button size="default" variant="quiet" wire:click="cancelResolve">Cancel</x-ui.button>
 </div>
 <textarea wire:model="resolutionNotes" rows="2" placeholder="Resolution notes..." class="w-full px-3 py-2 rounded-lg bg-surface border border-rule text-xs text-ink focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"></textarea>
 <div class="text-right">
 <x-ui.submit target="resolve" busy="Resolving…" wire:click="resolve({{ $t->id }}, $wire.resolutionNotes)">Mark resolved</x-ui.submit>
 </div>
 </div>
 @elseif($t->status !== 'resolved')
 <div class="flex items-center justify-end pt-2 border-t border-rule">
 <x-ui.button size="default" wire:click="startResolve({{ $t->id }})">Resolve</x-ui.button>
 </div>
 @else
 @if($t->resolution_notes)
 <div class="text-xs text-ink-2">
 <span class="font-semibold">Resolution Notes:</span> {{ $t->resolution_notes }}
 </div>
 @endif
 @endif
 </div>
 @empty
 <x-ui.empty-state heading="Nothing resolved yet">A ticket lands here when it is marked resolved.</x-ui.empty-state>
 @endforelse
 </div>
 @endif
</div>
