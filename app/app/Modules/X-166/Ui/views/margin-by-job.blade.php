<div>
    <div class="margin-job-view p-4">
        <h2>Margin by job</h2>
        <p>Expected comes from the pricebook; actual comes from the field.</p>
        
        @if($costs->isEmpty())
            <x-ui.empty-state>No costed jobs yet. A job is costed when it completes.</x-ui.empty-state>
        @else
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th>Pricebook version</th>
                        <th>Revenue</th>
                        <th>Cost</th>
                        <th>Margin</th>
                        <th>Margin %</th>
                        <th>Sample</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($costs as $c)
                        <tr>
                            <td>{{ $c->job_id }}</td>
                            <td>{{ $c->price_book_version }}</td>
                            <td>{{ $money[$c->id]['revenue'] }}</td>
                            <td>{{ $money[$c->id]['total_cost'] }}</td>
                            <td>{{ $money[$c->id]['margin'] }}</td>
                            <td>
                                <x-ui.status-pill 
                                    :state="$c->gross_margin_pct < 20.0 ? 'attention' : 'ok'" 
                                    :label="$pct[$c->id]" 
                                />
                            </td>
                            <td>
                                @if($c->is_sample)
                                    <x-ui.status-pill state="attention" label="Sample" />
                                @endif
                            </td>
                            <td>
                                <x-ui.button size="default" wire:click="toggle({{ $c->id }})">Show breakdown</x-ui.button>
                            </td>
                        </tr>
                        @if(isset($expanded[$c->id]))
                            <tr>
                                <td colspan="8" class="bg-surface p-2">
                                    <div class="flex space-x-4">
                                        <span>Labour: {{ $money[$c->id]['labor'] }}</span>
                                        <span>Materials: {{ $money[$c->id]['materials'] }}</span>
                                        <span>Overhead: {{ $money[$c->id]['overhead'] }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
