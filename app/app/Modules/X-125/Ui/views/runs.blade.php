<div wire:init="load">
    @if ($errorMessage)
        <x-ui.error-panel heading="We could not load your flow runs." retry="load">
            {{ $errorMessage }}
        </x-ui.error-panel>
    @elseif (! $ready)
        <x-ui.skeleton label="Loading runs..." />
    @elseif ($runs->isEmpty())
        <x-ui.empty-state heading="No runs found">
            When a flow runs, its history and status will appear here.
        </x-ui.empty-state>
    @else
        <h3>Flow Execution Runs</h3>
        <div class="space-y-4 mt-4">
            @foreach ($runs as $run)
                <div class="border p-4 rounded" wire:key="run-{{ $run->id }}">
                    <div class="flex justify-between items-center">
                        <div>
                            <strong>{{ $run->flow->name }}</strong>
                            <x-ui.status-pill :state="match($run->status) { 'success' => 'ok', 'error' => 'alert', 'simulated' => 'unknown', default => 'unknown' }" />
                        </div>
                        <x-ui.button wire:click="retry({{ $run->id }})">Retry</x-ui.button>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">
                        @if ($run->flowVersion && $run->flowVersion->plain_explanation)
                            {{ $run->flowVersion->plain_explanation }}
                        @elseif ($run->flowVersion)
                            {{ app(\App\Modules\X125\Actions\FlowExplainAction::class)->explain($run->flow->trigger_event, $run->flowVersion->nodes ?? []) }}
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</div>
