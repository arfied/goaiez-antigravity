<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-lg mx-auto">
        <div class="mb-8">
            <h1 class="text-xl font-semibold leading-6 text-gray-900">Paid Today</h1>
            <div class="mt-4 bg-white px-4 py-5 shadow sm:rounded-lg border border-gray-200">
                <dt class="truncate text-sm font-medium text-gray-500">Total Received Today</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 tabular-nums">{{ number_format($totalCents / 100, 2) }}</dd>
            </div>
        </div>

        <div class="mt-8 flow-root">
            <div wire:loading class="w-full text-center p-4">
                <span class="text-gray-500 text-base">Loading...</span>
            </div>

            @if($error)
                <div role="alert" class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative text-base">
                    {{ $error }}
                </div>
            @endif
            
            @if($invoices->isEmpty())
                <div wire:loading.remove class="text-center p-8 bg-white rounded-lg border border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">No paid invoices today.</h3>
                </div>
            @else
                <div wire:loading.remove class="space-y-6">
                    @foreach($invoices as $invoice)
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg bg-white">
                            <div class="p-4 border-b border-gray-200">
                                <h3 class="text-base font-medium text-gray-900">{{ $invoice->invoice_number }}</h3>
                                <p class="mt-1 text-sm text-gray-500">Paid: {{ $invoice->updated_at->format('g:i A') }}</p>
                                <p class="mt-3 text-base text-gray-700">
                                    <button wire:click="explain({{ $invoice->id }})" wire:loading.attr="disabled" class="text-indigo-600 hover:text-indigo-900 font-semibold tabular-nums">
                                        {{ number_format($invoice->total_cents / 100, 2) }}
                                    </button>
                                </p>
                            </div>
                            
                            @if($explainedInvoiceId === $invoice->id)
                                <div class="bg-gray-50 px-4 py-4 sm:px-6 border-t border-gray-200">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-2">Invoice Lines</h4>
                                    <ul class="space-y-2">
                                        @foreach($invoiceLines as $line)
                                            <li class="flex justify-between text-sm text-gray-700">
                                                <span>{{ $line->description }} ({{ $line->quantity }}x)</span>
                                                <span class="tabular-nums font-medium">{{ number_format($line->subtotal_cents / 100, 2) }}</span>
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
