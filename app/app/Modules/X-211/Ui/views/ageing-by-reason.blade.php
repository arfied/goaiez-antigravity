<div>
    <h1 class="text-xl font-semibold mb-4">Ageing By Reason</h1>

    @foreach($groups as $reason => $invoices)
        <div class="mb-8">
            <h2 class="text-lg font-medium mb-2">{{ $reason }}</h2>
            <ul class="space-y-4">
                @foreach($invoices as $invoice)
                    <li class="border rounded p-4 shadow bg-white">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <span class="font-semibold">{{ $invoice->invoice_number }}</span>
                                <span class="text-gray-500 text-sm ml-2">Due: {{ $invoice->due_date }}</span>
                            </div>
                            <div class="tabular-nums">
                                {{ number_format(($invoice->total_cents - $invoice->paid_cents) / 100, 2) }}
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <input type="text" wire:model.defer="referenceNumber" placeholder="Reference Number" class="border rounded px-2 py-1 flex-1">
                            <x-ui.button wire:click="logPayment({{ $invoice->id }})">Log Payment</x-ui.button>
                        </div>
                        @error('referenceNumber') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
