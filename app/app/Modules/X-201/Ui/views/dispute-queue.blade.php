<div>
<h2 class="text-lg font-bold text-ink">Dispute queue</h2>
<x-ui.attention-card state="attention" heading="One account at a time">the operator queue across accounts is behind row-level security and no cross-account read path is built in this checkout yet; below is this account's open disputes.</x-ui.attention-card>
@if($error) <x-ui.error-panel heading="We couldn't act on that dispute">{{ $error }}</x-ui.error-panel> @endif
@if($success) <p>{{ $success }}</p> @endif
<div wire:loading><x-ui.skeleton label="Reading the disputes…" /></div>
@if($disputes->isEmpty())
<x-ui.empty-state heading="No open disputes.">No chargeback has arrived. A dispute opens here when the gateway chargeback webhook reaches this app, and no such webhook is received in this checkout, so nothing opens one and nothing compiles on a schedule: a bundle is compiled from this queue by hand.</x-ui.empty-state>
@else
<ul class="space-y-4">
@foreach($disputes as $d)
<li class="border rounded p-4 shadow bg-card" wire:key="dispute-{{ $d->id }}">
<span class="font-semibold">Invoice #{{ $d->invoice_id }}</span>
<span class="tabular-nums">{{ number_format($d->chargeback_amount_cents / 100, 2) }}</span>
<span class="text-sm text-ink-2">{{ $d->reason }}</span>
<x-ui.status-pill :state="$d->status === 'opened' ? 'attention' : 'ok'" :label="$d->status === 'submitted' ? 'sealed, not sent' : $d->status" />
<p class="text-sm">{{ $d->evidence_count }} evidence items · {{ $d->has_signature ? 'signature on file' : 'no signature yet' }}</p>
@if($d->evidence_items->isNotEmpty())
<ul class="text-sm">
@foreach($d->evidence_items as $item)
<li>{{ $item->evidence_type }}: {{ $item->file_url_or_content }}</li>
@endforeach
</ul>
@endif
<form wire:submit="compile({{ $d->id }})" class="mt-3 flex flex-wrap items-center gap-2">
<input type="text" wire:model="note.{{ $d->id }}" placeholder="Add a note to the bundle" class="border rounded px-2 py-1 w-64">
<x-ui.submit target="compile({{ $d->id }})" busy="Compiling…">Compile evidence</x-ui.submit>
</form>
@if($d->status === 'compiled')
<x-ui.button size="default" wire:click="submit({{ $d->id }})" wire:loading.attr="disabled" wire:target="submit({{ $d->id }})">Seal the defence</x-ui.button>
@endif
@if($d->status === 'submitted')
<x-ui.button size="default" wire:click="outcome({{ $d->id }}, 'won')" wire:loading.attr="disabled" wire:target="outcome({{ $d->id }}, 'won')">Won</x-ui.button>
<x-ui.button size="default" variant="secondary" wire:click="outcome({{ $d->id }}, 'lost')" wire:loading.attr="disabled" wire:target="outcome({{ $d->id }}, 'lost')">Lost</x-ui.button>
@endif
</li>
@endforeach
</ul>
<p class="text-sm text-ink-2 mt-4">Call logs, transcripts and captured signatures join the bundle when those modules expose a read path; the submission deadline arrives with the gateway's chargeback webhook.</p>
@endif
</div>
