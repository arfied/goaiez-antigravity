<div>
<h1>Same account</h1>
<p class="text-base text-ink-2">Money from this account's invoices lands in this account's own merchant account — never the platform's, never another tenant's.</p>
@if($error) <x-ui.error-panel heading="We couldn't do that">{{ $error }}</x-ui.error-panel> @endif
@if($waiting) <x-ui.attention-card state="attention" heading="Waiting on the gateway">{{ $waiting }}</x-ui.attention-card> @endif
@if($success) <p>{{ $success }}</p> @endif
<div wire:loading><x-ui.skeleton label="Reading where money lands…" /></div>
@if($connections->isEmpty())
<x-ui.empty-state heading="No merchant account connected yet.">Connect a gateway on the connect card; payments land now, payouts wait on the same import.</x-ui.empty-state>
@else
<ul class="space-y-4">
@foreach($connections as $conn)
<li class="border rounded p-4 shadow bg-white">
<span class="font-semibold">{{ $conn->gateway_name }}</span>
<p class="text-sm text-ink-2">Money lands in <span class="tabular-nums">{{ $conn->merchant_account_id }}</span></p>
<x-ui.status-pill :state="$conn->is_connected ? 'ok' : 'attention'" :label="$conn->is_connected ? 'connected' : 'disconnected'" />
<dl class="mt-2 grid grid-cols-2 gap-2 text-sm tabular-nums">
<div><dt>Payments landed</dt><dd>{{ $conn->payments_count }} payments · {{ number_format($conn->payments_cents / 100, 2) }}</dd></div>
<div><dt>Payouts</dt><dd>{{ $conn->payouts_count }} payouts · {{ number_format($conn->payouts_cents / 100, 2) }}</dd></div>
<div><dt>Last reconciliation</dt><dd>{{ $conn->last_reconciliation }}</dd></div>
</dl>
<x-ui.button size="default" variant="secondary" wire:click="pull({{ $conn->id }})" wire:loading.attr="disabled" wire:target="pull({{ $conn->id }})">Pull payouts from {{ $conn->gateway_name }}</x-ui.button>
</li>
@endforeach
</ul>
@endif
@if($detached->isNotEmpty())
<h2>Not attached to any account</h2>
<ul class="space-y-4">
@foreach($detached as $p)
<li class="border rounded p-4 shadow bg-white">
<span class="font-semibold tabular-nums">{{ number_format($p->amount_cents / 100, 2) }} {{ $p->currency }}</span>
<span class="text-sm text-ink-2">{{ $p->gateway_charge_id ?? $p->idempotency_key }}</span>
<x-ui.status-pill state="attention" label="lands nowhere" />
@foreach($connections as $conn)
<x-ui.button size="default" wire:click="attach({{ $p->id }}, {{ $conn->id }})" wire:loading.attr="disabled" wire:target="attach({{ $p->id }}, {{ $conn->id }})">Attach to {{ $conn->merchant_account_id }}</x-ui.button>
@endforeach
</li>
@endforeach
</ul>
@endif
</div>
