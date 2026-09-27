<div>
<h2>Same account</h2>
<p class="text-base text-ink-2">Card payments in this checkout are taken on the goaiez platform Stripe account, not on the merchant account recorded below. Routing a charge to a tenant merchant account waits on the processor contract, so nothing is routed to it yet.</p>
@if($error) <x-ui.error-panel heading="We couldn't do that">{{ $error }}</x-ui.error-panel> @endif
@if($waiting) <x-ui.attention-card state="attention" heading="Waiting on the gateway">{{ $waiting }}</x-ui.attention-card> @endif
<x-ui.toast kind="success" :message="$success" />
<div wire:loading><x-ui.skeleton label="Reading payments and payouts…" /></div>
@if($connections->isEmpty())
<x-ui.empty-state heading="No merchant account recorded yet.">No merchant account has been recorded on this account. Recording one waits on the Stripe Connect redirect, which is not built in this checkout yet. Card payments are taken on the goaiez platform Stripe account, and no payout has ever been imported.</x-ui.empty-state>
@else
<ul class="space-y-4">
@foreach($connections as $conn)
<li class="border rounded p-4 shadow bg-card">
<span class="font-semibold">{{ $conn->gateway_name }}</span>
<p class="text-sm text-ink-2">Recorded merchant account <span class="tabular-nums">{{ $conn->merchant_account_id }}</span>, which no charge is routed to yet</p>
<x-ui.status-pill :state="$conn->is_connected ? 'ok' : 'attention'" :label="$conn->is_connected ? 'recorded only' : 'disabled'" />
<dl class="mt-2 grid grid-cols-2 gap-2 text-sm tabular-nums">
<div><dt>Payments recorded</dt><dd>{{ $conn->payments_line }}</dd></div>
<div><dt>Payouts</dt><dd>@if($conn->payouts_count === 0)No payout has ever been imported: reading payouts from the gateway is not built in this checkout yet.@else{{ $conn->payouts_count }} payouts · {{ number_format($conn->payouts_cents / 100, 2) }}@endif</dd></div>
<div><dt>Last reconciliation</dt><dd>{{ $conn->last_reconciliation }}</dd></div>
</dl>
<x-ui.button size="default" variant="secondary" wire:click="pull({{ $conn->id }})" wire:loading.attr="disabled" wire:target="pull({{ $conn->id }})">Check {{ $conn->gateway_name }} for payouts</x-ui.button>
</li>
@endforeach
</ul>
@endif
@if($detached->isNotEmpty())
<h2>Not attached to any account</h2>
<ul class="space-y-4">
@foreach($detached as $p)
<li class="border rounded p-4 shadow bg-card">
<span class="font-semibold tabular-nums">{{ number_format($p->amount_cents / 100, 2) }} {{ $p->currency }}</span>
@if($p->gateway_charge_id !== null)
<span class="text-sm text-ink-2">Gateway charge {{ $p->gateway_charge_id }}</span>
@else
<span class="text-sm text-ink-2">No gateway charge id; our own reference {{ $p->idempotency_key }}</span>
@endif
<x-ui.status-pill state="attention" label="no account recorded" />
@foreach($connections as $conn)
<x-ui.button size="default" wire:click="attach({{ $p->id }}, {{ $conn->id }})" wire:loading.attr="disabled" wire:target="attach({{ $p->id }}, {{ $conn->id }})">Attach to {{ $conn->merchant_account_id }}</x-ui.button>
@endforeach
</li>
@endforeach
</ul>
@endif
</div>
