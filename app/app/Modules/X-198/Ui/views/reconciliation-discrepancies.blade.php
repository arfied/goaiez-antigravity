<div>
<h2 class="text-lg font-bold text-ink">Reconciliation discrepancies</h2>
<x-ui.attention-card state="attention" heading="One account at a time">the cross-account roll-up is an operator read behind row-level security and no cross-account read path is built in this checkout yet; what follows is this account's runs.</x-ui.attention-card>
@if($error) <x-ui.error-panel heading="We couldn't mark that run">{{ $error }}</x-ui.error-panel> @endif
@if($success) <p>{{ $success }}</p> @endif
<div wire:loading><x-ui.skeleton label="Reading the runs…" /></div>
@if($runs->isEmpty())
<x-ui.empty-state heading="No payouts have been imported yet.">Reconciliation compares what the processor paid out against what we expected. Importing payouts from the gateway is not connected yet, so there is nothing to compare — this screen fills in the moment it is.</x-ui.empty-state>
@else
<ul class="space-y-4">
@foreach($runs as $run)
<li class="border rounded p-4 shadow bg-card">
<span class="font-semibold">{{ $run->payout_label }}</span>
<span class="text-sm text-ink-2">{{ $run->payout_date }}</span>
@if($run->reviewed_label === null)
<x-ui.status-pill state="attention" label="unreviewed" />
@else
<x-ui.status-pill state="ok" :label="$run->reviewed_label" />
@endif
<dl class="mt-2 grid grid-cols-2 gap-2 text-sm tabular-nums">
<div><dt>Expected</dt><dd>{{ number_format($run->expected_cents / 100, 2) }}</dd></div>
<div><dt>Actual</dt><dd>{{ number_format($run->actual_cents / 100, 2) }}</dd></div>
<div><dt>Discrepancy</dt><dd>{{ number_format($run->discrepancy_cents / 100, 2) }}</dd></div>
<div><dt>Reason</dt><dd>{{ $run->discrepancy_reason }}</dd></div>
</dl>
@if($run->reviewed_label === null)
<x-ui.button size="default" wire:click="review({{ $run->id }})" wire:loading.attr="disabled" wire:target="review({{ $run->id }})">Mark reviewed</x-ui.button>
@endif
</li>
@endforeach
</ul>
<p class="text-sm text-ink-2 mt-4">A discrepancy is written, never corrected. Re-checking a payout with the gateway waits on its payout read path.</p>
@endif
</div>
