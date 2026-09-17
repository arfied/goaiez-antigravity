<div>
    <h2 class="text-lg font-bold text-ink">Overdue invoices</h2>

    @if($error)
        <x-ui.error-panel heading="That didn't go through">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    @if($success)
        <p>{{ $success }}</p>
    @endif

    @if($refused)
        <x-ui.attention-card state="attention" :heading="$refusedHeading ?? 'Late fee not applied'">
            {{ $refused }}
        </x-ui.attention-card>
    @endif

    <div wire:loading>
        <x-ui.skeleton label="Checking what is overdue…" />
    </div>

    <section class="mb-8">
        <h3 class="font-semibold text-ink">Late-fee term</h3>
        @if($terms?->late_fee_percent)
            <x-ui.status-pill state="ok" :label="$terms->late_fee_percent.'% of the invoice'.($terms->late_fee_cap_cents ? ', capped at '.number_format($terms->late_fee_cap_cents / 100, 2) : ', no cap')" />
        @else
            <x-ui.status-pill state="attention" label="no late-fee term in the agreement" />
        @endif
        <form wire:submit="saveTerm" class="flex flex-wrap items-center gap-2 mt-2">
            <input type="number" min="1" max="100" wire:model="term.percent" placeholder="Percent of the invoice" class="border rounded px-2 py-1 w-40">
            <input type="number" min="1" wire:model="term.cap" placeholder="Cap in cents, blank for none" class="border rounded px-2 py-1 w-56">
            <x-ui.submit target="saveTerm" busy="Saving…">Save term</x-ui.submit>
        </form>
    </section>

    @if(empty($groups))
        <x-ui.empty-state heading="Nothing is overdue.">No invoice is past its terms. Nothing in this checkout raises one from a completed job, and a draft is never issued, so nothing reaches this screen yet.</x-ui.empty-state>
    @else
        @foreach($groups as $reason => $invoices)
            <div class="mb-8">
                <h3 class="font-semibold text-ink">{{ $reason }}</h3>
                <ul class="space-y-4">
                    @foreach($invoices as $inv)
                        <li class="border rounded p-4 shadow bg-card" wire:key="ageing-inv-{{ $inv->id }}">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <span class="font-semibold">{{ $inv->invoice_number }}</span>
                                    <span class="text-ink-2 text-sm ml-2">Due {{ $inv->due_date->toDateString() }}</span>
                                </div>
                                <div class="tabular-nums">
                                    {{ number_format($inv->balance_cents / 100, 2) }}
                                </div>
                            </div>
                            
                            <x-ui.status-pill :state="$inv->days_overdue > 60 ? 'alert' : 'attention'" :label="$inv->days_overdue.' days overdue'" />
                            @if($inv->late_fee_cents > 0) <x-ui.status-pill state="attention" :label="'late fee '.number_format($inv->late_fee_cents / 100, 2)" /> @endif
                            
                            <form wire:submit="logPayment({{ $inv->id }})" class="flex items-center gap-2 mt-4">
                                <input type="text" wire:model="reference.{{ $inv->id }}" placeholder="Cheque or transfer reference" class="border rounded px-2 py-1 flex-1">
                                <input type="number" wire:model="amountCents.{{ $inv->id }}" placeholder="Amount in cents" class="border rounded px-2 py-1 w-32">
                                <select wire:model="paymentMethod.{{ $inv->id }}" class="border rounded px-2 py-1">
                                    <option value="check">Cheque</option>
                                    <option value="cash">Cash</option>
                                    <option value="zelle">Zelle</option>
                                    <option value="wire">Wire transfer</option>
                                </select>
                                <x-ui.submit target="logPayment({{ $inv->id }})" busy="Logging…">Log payment</x-ui.submit>
                            </form>
                            <form wire:submit="applyLateFee({{ $inv->id }})" class="flex items-center gap-2 mt-2">
                                <input type="number" min="1" wire:model="feeCents.{{ $inv->id }}" placeholder="Late fee in cents" class="border rounded px-2 py-1 w-40">
                                <x-ui.submit target="applyLateFee({{ $inv->id }})" busy="Applying…">Apply late fee</x-ui.submit>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif
</div>
