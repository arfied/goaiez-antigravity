<div>
    <div class="stock-van-view p-4">
        <h2>Stock by van</h2>
        
        @if($itemsGrouped->isEmpty())
            <x-ui.empty-state>No stock yet. The parts list is inferred from your invoices.</x-ui.empty-state>
        @else
            @php
                $locIds = $itemsGrouped->keys()->sort(function($a, $b) use ($locations) {
                    if ($a === '') return 1;
                    if ($b === '') return -1;
                    $nameA = $locations[$a]->name ?? '';
                    $nameB = $locations[$b]->name ?? '';
                    return strcmp($nameA, $nameB);
                });
            @endphp
            @foreach($locIds as $locId)
                @php
                    $locItems = $itemsGrouped[$locId];
                    $locName = $locId !== '' && isset($locations[$locId]) ? $locations[$locId]->name : 'No van';
                @endphp
                <div class="mb-8">
                    <h3>{{ $locName }}</h3>
                    <table class="w-full text-left">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>SKU</th>
                                <th>On hand</th>
                                <th>Reorder point</th>
                                <th>Status</th>
                                <th>Sample</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($locItems as $item)
                                <tr>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ $item->sku }}</td>
                                    <td>{{ $qty[$item->id] }}</td>
                                    <td>{{ $point[$item->id] }}</td>
                                    <td>
                                        <x-ui.status-pill 
                                            :state="$low[$item->id] ? 'attention' : 'ok'" 
                                            :label="$low[$item->id] ? 'Low' : 'In stock'" 
                                        />
                                    </td>
                                    <td>
                                        @if($item->is_sample)
                                            <x-ui.status-pill state="attention" label="Sample" />
                                        @endif
                                    </td>
                                    <td>
                                        @if(isset($proposed[$item->id]))
                                            Proposed {{ $proposed[$item->id] }}
                                        @else
                                            <x-ui.button size="default" wire:click="proposeRestock({{ $item->id }})">Propose restock</x-ui.button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>
</div>
