<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-xl font-semibold leading-6 text-gray-900">Invoices</h1>
            </div>
        </div>
        <div class="mt-8 flow-root">
            <div wire:loading>
                <x-ui.skeleton label="Reading the invoices…" lines="3" />
            </div>

            @if($error)
                <x-ui.error-panel heading="We couldn't record that payment">{{ $error }}</x-ui.error-panel>
            @endif
            
            @if($invoices->isEmpty())
                <div wire:loading.remove>
                    <x-ui.empty-state heading="No invoices yet." action="Reload list" target="$refresh">
                        A completed job becomes an invoice and it sends (R235).
                    </x-ui.empty-state>
                </div>
            @else
                <div wire:loading.remove class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-[--radius-card]">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Number</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Customer</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Due Date</th>
                                <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Total</th>
                                <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Paid</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ $invoice->invoice_number }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $invoice->customer_name }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        <x-ui.status-pill :state="$invoice->status === 'paid' ? 'ok' : ($invoice->status === 'draft' ? 'unknown' : 'attention')" label="{{ $invoice->status }}" />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $invoice->due_date }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 text-right tabular-nums">
                                        <x-ui.button size="default" variant="quiet" class="!px-0" wire:click="toggleExpanded({{ $invoice->id }})">
                                            {{ number_format($invoice->total_cents / 100, 2) }}
                                        </x-ui.button>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 text-right tabular-nums">{{ number_format($invoice->paid_cents / 100, 2) }}</td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        @if($invoice->status === 'paid')
                                            <x-ui.button :href="$invoice->pdf_url" variant="quiet" size="default" target="_blank">Receipt</x-ui.button>
                                        @elseif(in_array($invoice->status, ['issued', 'due', 'offline_recorded']))
                                            <x-ui.button size="default" wire:loading.attr="disabled" wire:target="recordPayment({{ $invoice->id }})" wire:click="recordPayment({{ $invoice->id }})">Record payment</x-ui.button>
                                        @elseif($invoice->status === 'draft')
                                            <x-ui.button :href="$invoice->pdf_url" variant="quiet" size="default" target="_blank">Open PDF</x-ui.button>
                                        @endif
                                    </td>
                                </tr>
                                @if(in_array($invoice->id, $expanded))
                                    <tr class="bg-gray-50">
                                        <td colspan="7" class="px-6 py-4">
                                            <ul class="divide-y divide-gray-200">
                                                @foreach($invoice->lines as $line)
                                                    <li class="py-2 flex justify-between">
                                                        <span class="text-sm text-gray-600">{{ $line->description }} (x{{ $line->quantity }})</span>
                                                        <span class="text-sm text-gray-900 tabular-nums">{{ number_format($line->subtotal_cents / 100, 2) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
