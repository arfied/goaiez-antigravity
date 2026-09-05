<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-lg mx-auto">
        <div class="mb-8">
            <h1 class="font-display text-xl font-semibold leading-6 text-ink">Paid Today</h1>
            <div class="mt-4 bg-card px-4 py-5 shadow sm:rounded-[--radius-card] border border-rule">
                <dt class="truncate text-sm font-medium text-ink-2">Total Received Today</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-ink tabular-nums">{{ number_format($totalCents / 100, 2) }}</dd>
            </div>
        </div>

        <div class="mt-8 flow-root">
            <div wire:loading>
                <x-ui.skeleton label="Reading today's payments…" lines="3" />
            </div>

            @if($error)
                <x-ui.error-panel heading="We couldn't open that invoice">{{ $error }}</x-ui.error-panel>
            @endif
            
            @if($invoices->isEmpty())
                <div wire:loading.remove>
                    <x-ui.empty-state icon="○" heading="No paid invoices today.">
                        When invoices are paid today, they will appear here.
                    </x-ui.empty-state>
                </div>
            @else
                <div wire:loading.remove class="space-y-6">
                    @foreach($invoices as $invoice)
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-[--radius-card] bg-card">
                            <div class="p-4 border-b border-rule">
                                <h3 class="text-base font-medium text-ink">{{ $invoice->invoice_number }} <x-ui.status-pill state="ok" label="Paid" /></h3>
                                <p class="mt-1 text-sm text-ink-2">Paid: {{ $invoice->paid_at->format('g:i A') }}</p>
                                <p class="mt-3 text-base text-ink">
                                    <x-ui.button wire:click="explain({{ $invoice->id }})" wire:loading.attr="disabled" wire:target="explain({{ $invoice->id }})" variant="quiet" size="default" class="!px-0 tabular-nums">
                                        {{ number_format($invoice->total_cents / 100, 2) }}
                                    </x-ui.button>
                                </p>
                            </div>
                            
                            @if($explainedInvoiceId === $invoice->id)
                                <div class="bg-paper px-4 py-4 sm:px-6 border-t border-rule">
                                    <h4 class="text-sm font-semibold text-ink mb-2">Invoice Lines</h4>
                                    <ul class="space-y-2">
                                        @foreach($invoiceLines as $line)
                                            <li class="flex justify-between text-sm text-ink-2">
                                                <span>{{ $line->description }} ({{ $line->quantity }}x)</span>
                                                <span class="tabular-nums font-medium text-ink">{{ number_format($line->subtotal_cents / 100, 2) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
