<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-xl font-semibold leading-6 text-gray-900">Unpaid Invoices</h1>
            @if(!$showLastFivePaid)
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div class="bg-card px-4 py-5 shadow sm:rounded-[--radius-card] border border-rule">
                        <dt class="truncate text-sm font-medium text-ink-2">Unpaid Invoices</dt>
                        <dd class="mt-1 text-3xl font-semibold tracking-tight text-ink tabular-nums">{{ $unpaidCount }}</dd>
                    </div>
                    <div class="bg-card px-4 py-5 shadow sm:rounded-[--radius-card] border border-rule">
                        <dt class="truncate text-sm font-medium text-ink-2">Unpaid Value</dt>
                        <dd class="mt-1 text-3xl font-semibold tracking-tight text-ink tabular-nums">{{ number_format($unpaidValueCents / 100, 2) }}</dd>
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-8 flow-root">
            <div wire:loading>
                <x-ui.skeleton label="Reading what is still owed…" lines="3" />
            </div>

            @if($error)
                <x-ui.error-panel heading="We couldn't record that payment">{{ $error }}</x-ui.error-panel>
            @endif
            
            @if($invoices->isEmpty() && !$showLastFivePaid)
                <div wire:loading.remove>
                    <x-ui.empty-state heading="Nothing unpaid." action="View the last 5 paid" target="showPaid">
                        You have no outstanding invoices.
                    </x-ui.empty-state>
                </div>
            @elseif($invoices->isEmpty() && $showLastFivePaid)
                <div wire:loading.remove>
                    <x-ui.empty-state heading="No paid invoices yet.">
                        You haven't received any payments yet.
                    </x-ui.empty-state>
                </div>
            @else
                <div wire:loading.remove class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-[--radius-card]">
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
                                            <p class="mt-1">
                                                <x-ui.status-pill state="ok" label="covered by the card on file; service never stopped" />
                                            </p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $invoice->due_date ? $invoice->due_date->format('M j, Y') : 'N/A' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        @if($invoice->days_overdue > 0)
                                            <x-ui.status-pill state="alert" label="{{ $invoice->days_overdue }} days overdue" />
                                        @else
                                            <x-ui.status-pill state="ok" label="Not overdue" />
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 text-right tabular-nums">
                                        <x-ui.button size="default" variant="quiet" class="!px-0 font-semibold text-indigo-600" wire:click="toggleExpanded({{ $invoice->id }})">
                                            {{ number_format($invoice->outstanding_cents / 100, 2) }}
                                        </x-ui.button>
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <x-ui.button size="default" wire:loading.attr="disabled" wire:target="recordPayment({{ $invoice->id }})" wire:click="recordPayment({{ $invoice->id }})">Record payment</x-ui.button>
                                        <x-ui.button size="default" :href="$invoice->pdf_url" variant="quiet" target="_blank">Receipt</x-ui.button>
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
