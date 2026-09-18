<div>
    <h2 class="text-lg font-bold text-ink">Payment plans</h2>

    <p class="text-base text-ink-2">Up to {{ $terms->max_installments }} payments over {{ $terms->max_term_days }} days is a schedule. Beyond that it is credit, and it routes to a financing partner. That limit is the standing default, not an account setting: nothing in this checkout changes it yet.</p>

    @if($error)
        <x-ui.error-panel heading="We couldn't offer that plan">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    @if($success)
        <p>{{ $success }}</p>
    @endif

    @if($financing)
        <x-ui.attention-card state="attention" heading="This one is credit, not a schedule">
            {{ $financing }} No financing partner is connected yet — this waits on a financing partner.
        </x-ui.attention-card>
    @endif

    <div wire:loading>
        <x-ui.skeleton label="Working out the split…" />
    </div>

    @if($plans->isNotEmpty())
        <h3 class="font-semibold text-ink">Plans in place</h3>
        <ul class="space-y-2">
            @foreach($plans as $plan)
                <li class="flex items-center justify-between p-2 border rounded">
                    <span class="font-semibold">{{ $numbers[$plan->invoice_id] ?? ('#'.$plan->invoice_id) }}</span>
                    <span class="tabular-nums">{{ $plan->installments_count }} × {{ number_format($plan->installment_amount_cents / 100, 2) }} {{ $plan->frequency }}</span>
                    <x-ui.status-pill :state="$plan->status === 'accepted' ? 'ok' : 'attention'" :label="$plan->status" />
                </li>
            @endforeach
        </ul>
    @endif

    @if($invoices->isEmpty())
        <x-ui.empty-state heading="Nothing to split.">There is no open invoice to split: either none has been raised, or every one is already on a plan. Nothing in this checkout raises one from a completed job, and a draft is never issued.</x-ui.empty-state>
    @else
        <h3 class="font-semibold text-ink">Split an open invoice</h3>
        <ul class="space-y-4">
            @foreach($invoices as $inv)
                <li class="border rounded p-4 shadow bg-card" wire:key="plan-inv-{{ $inv->id }}">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <span class="font-semibold">{{ $inv->invoice_number }}</span>
                            <span class="text-ink-2 text-sm ml-2">Due {{ $inv->due_date->toDateString() }}</span>
                        </div>
                        <div class="tabular-nums">{{ number_format($inv->balance_cents / 100, 2) }} owed</div>
                    </div>
                    <form wire:submit="offerPlan({{ $inv->id }})" class="flex flex-wrap items-center gap-2">
                        <input type="number" min="2" wire:model.live="installments.{{ $inv->id }}" placeholder="Payments" class="border rounded px-2 py-1 w-24">
                        <select wire:model="frequency.{{ $inv->id }}" class="border rounded px-2 py-1">
                            <option value="monthly">Monthly</option>
                            <option value="biweekly">Every two weeks</option>
                            <option value="weekly">Weekly</option>
                        </select>
                        <span class="tabular-nums text-sm text-ink-2">{{ $inv->preview_line }}</span>
                        <x-ui.submit target="offerPlan({{ $inv->id }})" busy="Offering…">Offer plan</x-ui.submit>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>
