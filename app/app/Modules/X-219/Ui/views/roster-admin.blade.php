<div>
    <div class="p-4 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-ink">Model Roster</h2>
            <button wire:click="seed" class="px-4 py-2 bg-surface text-ink border border-rule rounded shadow text-sm">
                Seed Defaults
            </button>
        </div>

        <div>
            <h3 class="font-semibold text-ink mb-2">Providers</h3>
            @if($providers->isEmpty())
                <p class="text-sm text-ink-2">No providers registered.</p>
            @else
                <ul class="divide-y divide-rule border border-rule rounded">
                    @foreach($providers as $provider)
                        <li class="px-4 py-2 text-sm flex justify-between">
                            <span class="font-mono text-ink">{{ $provider->provider_name }}</span>
                            <span class="text-ink-2">{{ $provider->status }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <h3 class="font-semibold text-ink mb-2">Models</h3>
            @if($models->isEmpty())
                <p class="text-sm text-ink-2">No models registered in tenant roster.</p>
            @else
                <ul class="divide-y divide-rule border border-rule rounded">
                    @foreach($models as $model)
                        <li class="px-4 py-2 text-sm flex justify-between">
                            <span class="font-mono text-ink">{{ $model->model_name }}</span>
                            <span>
                                <span class="text-ink-2 mr-4">In: ${{ number_format($model->cost_per_1k_input_cents / 100, 2) }}/M</span>
                                <span class="text-ink-2">Out: ${{ number_format($model->cost_per_1k_output_cents / 100, 2) }}/M</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
