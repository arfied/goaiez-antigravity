<div>
<h2 class="text-lg font-bold text-ink">Disputes</h2>
<p class="text-base text-ink-2">A chargeback on one of this account's invoices opens a dispute here. You compile the bundle from the dispute queue and add what only you know. Money does not go back from this card: a dispute is defended, and giving money back is the gateway account's.</p>
@if($error) <x-ui.error-panel heading="We couldn't do that">{{ $error }}</x-ui.error-panel> @endif
<x-ui.toast kind="success" :message="$success" />
<div wire:loading><x-ui.skeleton label="Reading the disputes…" /></div>
@if($disputes->isEmpty())
<x-ui.empty-state heading="No disputes.">No chargeback has arrived. A dispute opens here when the gateway chargeback webhook reaches this app; no such webhook is received in this checkout, so no bundle exists to add to yet.</x-ui.empty-state>
@else
<ul class="space-y-4">
@foreach($disputes as $d)
<li class="border rounded p-4 shadow bg-card">
<span class="font-semibold">Invoice #{{ $d->invoice_id }}</span>
<span class="tabular-nums">{{ number_format($d->chargeback_amount_cents / 100, 2) }}</span>
<span class="text-sm text-ink-2">{{ $d->reason }}</span>
<x-ui.status-pill :state="$d->status === 'won' ? 'ok' : ($d->status === 'lost' ? 'alert' : 'attention')" :label="$d->status === 'submitted' ? 'sealed, not sent' : $d->status" />
<p class="text-sm">{{ $d->evidence_count }} evidence items</p>
@if($d->evidence_items->isNotEmpty())
<ul class="text-sm">
@foreach($d->evidence_items as $item)
<li>{{ $item->evidence_type }}: {{ $item->file_url_or_content }}</li>
@endforeach
</ul>
@endif
@if($d->deadline_at)
<p class="text-sm text-ink-2">Deadline: {{ $d->deadline_at->format('j M Y') }}. Nothing watches this clock yet, so no one is raised as it nears.</p>
@else
<p class="text-sm text-ink-2">Deadline: none yet. The gateway's chargeback webhook sets it, and no such webhook reaches this checkout.</p>
@endif
@if($d->is_open)
<form wire:submit="addNote({{ $d->id }})" class="mt-3 flex flex-wrap items-center gap-2">
<input type="text" wire:model="note.{{ $d->id }}" placeholder="What only you know about this job" class="border rounded px-2 py-1 w-64">
<x-ui.submit target="addNote({{ $d->id }})" busy="Adding…">Add to the bundle</x-ui.submit>
</form>
@endif
@if($d->status === 'compiled')
<x-ui.button size="default" wire:click="approve({{ $d->id }})" wire:loading.attr="disabled" wire:target="approve({{ $d->id }})">Approve and seal</x-ui.button>
@endif
@if($d->status === 'submitted')
<p class="text-sm text-ink-2">Sealed. Nothing was sent to any gateway, so record the decision yourself from the dispute queue when it reaches you.</p>
@endif
</li>
@endforeach
</ul>
@endif
</div>
