<div>
    <div class="mb-6 sm:mb-8">
        <h2 class="text-sm font-semibold text-ink-2 uppercase tracking-wider mb-3">Unpaid Invoices</h2>
        
        <div wire:loading>
            <x-ui.skeleton label="Loading invoices..." />
        </div>
        
        <div wire:loading.remove>
            @if($loadError)
                <x-ui.error-panel heading="Could not load invoices">
                    {{ $loadError }}
                </x-ui.error-panel>
            @elseif($isSample)
                <x-ui.sample />
            @else
                <div class="mb-4">
                    <p class="text-3xl font-display font-bold text-ink">${{ number_format($totalOutstanding / 100, 2) }}</p>
                    <p class="text-xs text-ink-2">Outstanding</p>
                </div>
                @if($invoices->isEmpty())
                    <x-ui.empty-state icon="📄" heading="No unpaid invoices">
                        All issued invoices have been paid.
                    </x-ui.empty-state>
                @else
                    <x-ui.row-list>
                        @foreach($invoices as $inv)
                            <x-ui.row>
                                <div class="flex-1 min-w-0 pr-4">
                                    <p class="text-sm font-medium text-ink truncate">Invoice {{ $inv->invoice_number }}</p>
                                    <p class="text-xs text-ink-2 truncate">Due {{ \Carbon\Carbon::parse($inv->due_date)->format('M j, Y') }}</p>
                                </div>
                                <div class="text-sm font-semibold text-ink">
                                    ${{ number_format(($inv->total_cents - $inv->paid_cents) / 100, 2) }}
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.row-list>
                @endif
            @endif
        </div>
    </div>
</div>
