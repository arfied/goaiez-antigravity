<div>
    <div class="reorders-view p-4">
        <h2>Reorders</h2>
        <p>Stock at its reorder point is flagged on Stock by van; propose the restock there and the supplier prices it.</p>
        
        @if($orders->isEmpty())
            <x-ui.empty-state>No reorders yet. Stock at its reorder point is flagged on Stock by van; propose a restock there and it appears here.</x-ui.empty-state>
        @else
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th>PO</th>
                        <th>Supplier</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Sample</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $po)
                        <tr>
                            <td>{{ $po->po_number }}</td>
                            <td>
                                @if($po->supplier_id && isset($suppliers[$po->supplier_id]))
                                    {{ $suppliers[$po->supplier_id]->name }}
                                @else
                                    No supplier
                                @endif
                            </td>
                            <td>{{ $count[$po->id] }}</td>
                            <td>{{ $money[$po->id] }}</td>
                            <td>
                                <x-ui.status-pill 
                                    :state="$pill[$po->id][0]" 
                                    :label="$pill[$po->id][1]" 
                                />
                            </td>
                            <td>
                                @if($po->is_sample)
                                    <x-ui.status-pill state="attention" label="Sample" />
                                @endif
                            </td>
                            <td>
                                <x-ui.button size="default" wire:click="toggle({{ $po->id }})">Show items</x-ui.button>
                            </td>
                        </tr>
                        @if(isset($expanded[$po->id]))
                            <tr>
                                <td colspan="7" class="bg-surface p-2">
                                    <ul class="space-y-1">
                                        @php
                                            $items = is_array($po->items) ? $po->items : (json_decode($po->items ?? '[]', true) ?? []);
                                        @endphp
                                        @foreach($items as $idx => $item)
                                            <li>
                                                {{ $item['sku'] ?? '' }} · {{ $item['qty'] ?? '' }} · {{ $item['unit'] ?? '' }}
                                                @if(isset($itemPrices[$po->id][$idx]))
                                                    · {{ $itemPrices[$po->id][$idx] }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
