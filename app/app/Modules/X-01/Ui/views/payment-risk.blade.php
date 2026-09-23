<div>
    @if(count($customerGroups) === 0)
        <div class="empty-state">
            No invoice is past its due date. This screen fills from the invoices you send.
        </div>
    @else
        @foreach($customerGroups as $customerId => $group)
            <div class="mb-6 border rounded p-4 shadow">
                <h2 class="font-bold mb-2">{{ $group['customer_name'] }}</h2>
                <div class="mb-2">
                    @php
                        $invoicesCount = count($group['invoices']);
                        $oldestOverdue = max(array_column($group['invoices'], 'days_overdue'));
                        $totalOutstanding = array_sum(array_column($group['invoices'], 'outstanding_cents'));
                        $latestDunning = $group['invoices'][0]['latest_dunning'] ?? null;
                        $risk = $group['invoices'][0]['risk'];
                    @endphp
                    <span>Count: {{ $invoicesCount }}</span> |
                    <span>Oldest: {{ $oldestOverdue }} days</span> |
                    <span>Total: ${{ number_format($totalOutstanding / 100, 2) }}</span> |
                    <span>Dunning: {{ $latestDunning }}</span> |
                    <span>Tier: {{ $risk }}</span>
                </div>

                <table class="w-full text-left">
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Record Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['invoices'] as $invoice)
                            <tr>
                                <td>{{ $invoice['invoice_number'] }}</td>
                                <td>
                                    <select wire:model="reasonCode_{{ $invoice['invoice_id'] }}">
                                        @foreach($reasons as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button wire:click="recordReason({{ $invoice['invoice_id'] }}, $event.target.previousElementSibling.value)">Record what happened</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    @if($message)
        <div class="toast">{{ $message }}</div>
    @endif
</div>
