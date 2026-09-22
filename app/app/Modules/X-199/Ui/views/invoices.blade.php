<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h2 class="text-xl font-semibold leading-6 text-ink">Invoices</h2>
            </div>
        </div>
        <div class="mt-8 flow-root">
            <div wire:loading>
                <x-ui.skeleton label="Reading the invoices…" lines="3" />
            </div>

            @if($error)
                <x-ui.error-panel heading="We couldn't process that">{{ $error }}</x-ui.error-panel>
            @endif

            @if($success)
                <div class="mb-6 flex flex-col gap-2 bg-card p-4 ring-1 ring-rule sm:rounded-[--radius-card] mt-4">
                    <p class="text-sm font-medium text-ink">Success</p>
                    <p class="text-sm text-ink-2">{{ $success }}</p>
                </div>
            @endif
            
            <div class="mb-6 flex flex-col gap-2 bg-paper p-4 ring-1 ring-rule sm:rounded-[--radius-card] mt-4">
                <form wire:submit.prevent="draftInvoice" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-ink">Customer</label>
                        <select wire:model="customerId" class="border ring-1 ring-rule rounded p-2 text-ink flex-1 bg-card">
                            <option value="">Select a customer</option>
                            @foreach($people as $person)
                                <option value="{{ $person['id'] }}">{{ $person['first_name'] }} {{ $person['last_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-ink">Description</label>
                        <input type="text" wire:model="lineDescription" class="border ring-1 ring-rule rounded p-2 text-ink flex-1 bg-card">
                    </div>
                    <div class="flex gap-4">
                        <div class="flex flex-col gap-1 w-1/4">
                            <label class="text-sm font-medium text-ink">Quantity</label>
                            <input type="number" wire:model="lineQuantity" min="1" class="border ring-1 ring-rule rounded p-2 text-ink flex-1 bg-card">
                        </div>
                        <div class="flex flex-col gap-1 w-3/4">
                            <label class="text-sm font-medium text-ink">Unit Price (cents)</label>
                            <input type="number" wire:model="lineUnitPriceCents" class="border ring-1 ring-rule rounded p-2 text-ink flex-1 bg-card">
                        </div>
                    </div>
                    <div>
                        <x-ui.button type="submit">Draft Invoice</x-ui.button>
                    </div>
                </form>
            </div>
            
            @if($invoices->isEmpty())
                <div wire:loading.remove>
                    <x-ui.empty-state heading="No invoices yet." action="Reload list" target="$refresh">
                        No invoice has been raised for this account. Nothing in this checkout raises one from a completed job, and nothing sends an invoice once it exists, but an owner can raise one here by hand.
                    </x-ui.empty-state>
                </div>
            @else
                <div wire:loading.remove class="overflow-hidden shadow ring-1 ring-rule sm:rounded-[--radius-card]">
                    <table class="min-w-full divide-y divide-rule">
                        <thead class="bg-paper">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-ink sm:pl-6">Number</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Customer</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Status</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Due Date</th>
                                <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-ink">Total</th>
                                <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-ink">Paid</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rule bg-card">
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-ink sm:pl-6">{{ $invoice->invoice_number }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">{{ $invoice->customer_name }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                        <x-ui.status-pill :state="$invoice->status === 'paid' ? 'ok' : ($invoice->status === 'draft' ? 'unknown' : 'attention')" :label="$invoice->status" />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">{{ $invoice->due_date }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink text-right tabular-nums">
                                        <x-ui.button size="default" variant="quiet" class="!px-0" wire:click="toggleExpanded({{ $invoice->id }})">
                                            {{ number_format($invoice->total_cents / 100, 2) }}
                                        </x-ui.button>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2 text-right tabular-nums">{{ number_format($invoice->paid_cents / 100, 2) }}</td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        @if($invoice->status === 'paid')
                                            @if($invoice->pdf_url)
                                                <x-ui.button :href="$invoice->pdf_url" variant="quiet" size="default" target="_blank">Receipt</x-ui.button>
                                            @else
                                                <x-ui.status-pill state="unknown" label="Receipt not available" />
                                            @endif
                                        @elseif(in_array($invoice->status, ['issued', 'due', 'offline_recorded']))
                                            <x-ui.button size="default" wire:loading.attr="disabled" wire:target="recordPayment({{ $invoice->id }})" wire:click="recordPayment({{ $invoice->id }})">Record payment</x-ui.button>
                                        @elseif($invoice->status === 'draft')
                                            @if($invoice->pdf_url)
                                                <x-ui.button :href="$invoice->pdf_url" variant="quiet" size="default" target="_blank">Open PDF</x-ui.button>
                                            @else
                                                <x-ui.status-pill state="unknown" label="Not issued; nothing here issues a draft yet" />
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                                @if(in_array($invoice->id, $expanded))
                                    <tr class="bg-paper">
                                        <td colspan="7" class="px-6 py-4">
                                            <ul class="divide-y divide-rule">
                                                @foreach($invoice->lines as $line)
                                                    <li class="py-2 flex justify-between">
                                                        <span class="text-sm text-ink-2">{{ $line->description }} (x{{ $line->quantity }})</span>
                                                        <span class="text-sm text-ink tabular-nums">{{ number_format($line->subtotal_cents / 100, 2) }}</span>
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
