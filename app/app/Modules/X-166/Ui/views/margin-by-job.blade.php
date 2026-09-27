<div>
    <div class="margin-job-view p-4">
        <h2>Margin by job</h2>
        <p>Expected comes from the pricebook; actual comes from the field.</p>
        
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <div class="flex gap-2">
                <input type="text" wire:model="jobId" placeholder="Job ID" class="border rounded p-2 text-ink flex-1" />
                <input type="text" wire:model="priceBookVersion" placeholder="Pricebook Version" class="border rounded p-2 text-ink flex-1" />
            </div>
            <div class="flex gap-2">
                <input type="number" wire:model="revenueCents" placeholder="Revenue (cents)" class="border rounded p-2 text-ink flex-1" />
                <input type="number" wire:model="laborCostCents" placeholder="Labor Cost (cents)" class="border rounded p-2 text-ink flex-1" />
                <input type="number" wire:model="materialsCostCents" placeholder="Materials Cost (cents)" class="border rounded p-2 text-ink flex-1" />
                <input type="number" wire:model="overheadCostCents" placeholder="Overhead Cost (cents)" class="border rounded p-2 text-ink flex-1" />
            </div>
            <div>
                <x-ui.button size="default" wire:click="recordJobCost" wire:loading.attr="disabled" wire:target="recordJobCost">Record job cost</x-ui.button>
            </div>
        </div>

        @if($error)
            <x-ui.error-panel heading="That didn't go through">{{ $error }}</x-ui.error-panel>
        @endif

        @if($success)
            <x-ui.attention-card state="ok" heading="Job cost recorded">{{ $success }}</x-ui.attention-card>
        @endif

        @if($costs->isEmpty())
            <x-ui.empty-state>No costed jobs yet. Add one with the form above — costs are not captured automatically.</x-ui.empty-state>
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
