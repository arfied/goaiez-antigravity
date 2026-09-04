<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-xl font-semibold leading-6 text-gray-900">Unpaid Invoices</h1>
            @if(!$showLastFivePaid)
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div class="bg-white px-4 py-5 shadow sm:rounded-lg border border-gray-200">
                        <dt class="truncate text-sm font-medium text-gray-500">Unpaid Invoices</dt>
                        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 tabular-nums">{{ $unpaidCount }}</dd>
                    </div>
                    <div class="bg-white px-4 py-5 shadow sm:rounded-lg border border-gray-200">
                        <dt class="truncate text-sm font-medium text-gray-500">Unpaid Value</dt>
                        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 tabular-nums">{{ number_format($unpaidValueCents / 100, 2) }}</dd>
                    </div>
                </div>
            @endif
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
            
            @if($invoices->isEmpty() && !$showLastFivePaid)
                <div wire:loading.remove class="text-center p-8 bg-white rounded-lg border border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Nothing unpaid.</h3>
                    <div class="mt-6">
                        <button wire:click="showPaid" class="text-indigo-600 hover:text-indigo-900 text-base font-semibold">View full invoices list (last 5 paid)</button>
                    </div>
                </div>
            @elseif($invoices->isEmpty() && $showLastFivePaid)
                <div wire:loading.remove class="text-center p-8 bg-white rounded-lg border border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">No paid invoices found.</h3>
                </div>
            @else
                <div wire:loading.remove class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Number</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Due Date</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Days Overdue</th>
                                <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Outstanding</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                        {{ $invoice->invoice_number }}
                                        @if($invoice->has_overflow)
                                            <p class="mt-1 text-xs text-green-600 font-normal">covered by the card on file; service never stopped</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $invoice->due_date ? $invoice->due_date->format('M j, Y') : 'N/A' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        @if($invoice->days_overdue > 0)
                                            <span class="text-red-600 font-medium">{{ $invoice->days_overdue }} days</span>
                                        @else
                                            Not overdue
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 text-right cursor-pointer tabular-nums" wire:click="toggleExpanded({{ $invoice->id }})">
                                        <button class="hover:underline font-semibold text-indigo-600">{{ number_format($invoice->outstanding_cents / 100, 2) }}</button>
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <button wire:loading.attr="disabled" wire:target="recordPayment({{ $invoice->id }})" wire:click="recordPayment({{ $invoice->id }})" class="text-indigo-600 hover:text-indigo-900 p-2">Record payment</button>
                                        <a href="{{ $invoice->pdf_url }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 p-2">Receipt</a>
                                    </td>
                                </tr>
                                @if(in_array($invoice->id, $expanded))
                                    <tr class="bg-gray-50">
                                        <td colspan="5" class="px-6 py-4">
                                            <p class="text-sm text-gray-600">Total: <span class="tabular-nums font-semibold">{{ number_format($invoice->total_cents / 100, 2) }}</span></p>
                                            <p class="text-sm text-gray-600">Paid: <span class="tabular-nums font-semibold">{{ number_format($invoice->paid_cents / 100, 2) }}</span></p>
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
