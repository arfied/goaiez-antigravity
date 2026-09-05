<div>
    <div class="mb-6 sm:mb-8">
        <h2 class="text-sm font-semibold text-ink-2 uppercase tracking-wider mb-3">Happened Today</h2>
        
        <div wire:loading>
            <x-ui.skeleton label="Loading payments..." />
        </div>
        
        <div wire:loading.remove>
            @if($loadError)
                <x-ui.error-panel heading="Could not load payments">
                    {{ $loadError }}
                </x-ui.error-panel>
            @elseif($isSample)
                <x-ui.sample />
            @else
                <div class="mb-4">
                    <p class="text-3xl font-display font-bold text-ink">${{ number_format($total / 100, 2) }}</p>
                    <p class="text-xs text-ink-2">Paid today</p>
                </div>
                @if($invoices->isEmpty())
                    <x-ui.empty-state icon="💰" heading="No payments yet">
                        No invoices have been paid today.
                    </x-ui.empty-state>
                @else
                    <x-ui.row-list>
                        @foreach($invoices as $inv)
                            {{-- Row acts: leads to the receipt/invoice details --}}
                            <x-ui.row >
                                <div class="flex-1 min-w-0 pr-4">
                                    <p class="text-sm font-medium text-ink truncate">Invoice {{ $inv->invoice_number }}</p>
                                </div>
                                <div class="text-sm font-semibold text-ink">
                                    +${{ number_format($inv->paid_cents / 100, 2) }}
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.row-list>
                @endif
            @endif
        </div>
    </div>
</div>
