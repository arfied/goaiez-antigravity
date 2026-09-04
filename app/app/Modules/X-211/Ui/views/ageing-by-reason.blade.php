<div>
    <h1>Overdue, by reason</h1>

    @if($error)
        <x-ui.error-panel heading="We couldn't log that payment">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    @if($success)
        <p>{{ $success }}</p>
    @endif

    <div wire:loading>
        <x-ui.skeleton label="Checking what is overdue…" />
    </div>

    @if(empty($groups))
        <x-ui.empty-state heading="Nothing is overdue.">Every issued invoice is inside its terms.</x-ui.empty-state>
    @else
        @foreach($groups as $reason => $invoices)
            <div class="mb-8">
                <h2>{{ $reason }}</h2>
                <ul class="space-y-4">
                    @foreach($invoices as $inv)
                        <li class="border rounded p-4 shadow bg-white">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <span class="font-semibold">{{ $inv->invoice_number }}</span>
                                    <span class="text-gray-500 text-sm ml-2">Due {{ $inv->due_date->toDateString() }}</span>
                                </div>
                                <div class="tabular-nums">
                                    {{ number_format($inv->balance_cents / 100, 2) }}
                                </div>
                            </div>
                            
                            <x-ui.status-pill :state="$inv->days_overdue > 60 ? 'alert' : 'attention'" label="{{ $inv->days_overdue }} days overdue" />
                            
                            <form wire:submit="logPayment({{ $inv->id }})" class="flex items-center gap-2 mt-4">
                                <input type="text" wire:model="reference.{{ $inv->id }}" placeholder="Cheque or transfer reference" class="border rounded px-2 py-1 flex-1">
                                <input type="number" wire:model="amountCents.{{ $inv->id }}" placeholder="Amount in cents" class="border rounded px-2 py-1 w-32">
                                <x-ui.submit target="logPayment({{ $inv->id }})" busy="Logging…">Log payment</x-ui.submit>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif
</div>
