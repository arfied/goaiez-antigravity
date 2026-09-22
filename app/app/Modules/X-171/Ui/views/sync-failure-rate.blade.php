<div>
    <div class="sync-failure-rate-view p-4">
        <h2>Sync failure rate</h2>
        <p>A sync conflict surfaces; it never silently overwrites.</p>
        
        <x-ui.gauge :score="$score" label="Sync health" :sentence="$sentence" />
        
        @if($rate !== null)
            <p>{{ $rate }}</p>
        @endif
        
        @if($conflicts->isEmpty())
            <x-ui.empty-state>No sync conflicts. Every device mutation replayed cleanly.</x-ui.empty-state>
        @else
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Versions</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Sample</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($conflicts as $c)
                        <tr>
                            <td>{{ $c->device_id }}</td>
                            <td>{{ $versions[$c->id] }}</td>
                            <td>{{ $c->conflict_reason }}</td>
                            <td>
                                <x-ui.status-pill 
                                    :state="$pill[$c->id][0]" 
                                    :label="$pill[$c->id][1]" 
                                />
                            </td>
                            <td>
                                @if($c->is_sample)
                                    <x-ui.status-pill state="attention" label="Sample" />
                                @endif
                            </td>
                            <td>
                                @if(!str_starts_with($c->conflict_reason, 'Resolved:'))
                                    <x-ui.button size="default" wire:click="keepDevice({{ $c->id }})">Keep device</x-ui.button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
