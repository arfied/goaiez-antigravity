<div wire:init="load">
    @if ($errorMessage)
        <x-ui.error-panel :message="$errorMessage" retry="load" />
    @elseif (! $ready)
        <x-ui.skeleton label="Loading runs..." />
    @elseif ($runs->isEmpty())
        <x-ui.empty-state title="No runs found" description="There are no flow runs yet." />
    @else
        <h3>Flow Execution Runs</h3>
        <div class="space-y-4 mt-4">
            @foreach ($runs as $run)
                <div class="border p-4 rounded" wire:key="run-{{ $run->id }}">
                    <div class="flex justify-between items-center">
                        <div>
                            <strong>{{ $run->flow->name ?? 'Unknown Flow' }}</strong>
                            <x-ui.status-pill :status="$run->status" />
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
