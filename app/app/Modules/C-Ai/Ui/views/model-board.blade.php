<div wire:init="load">
    @if($errorMessage)
        <x-ui.error-panel heading="Could not load AI calls" retry="load">
            {{ $errorMessage }}
        </x-ui.error-panel>
    @elseif(! $ready)
        <x-ui.skeleton label="Loading AI models..." />
    @elseif($calls->isEmpty())
        <x-ui.empty-state heading="No model invocations recorded." icon="○">
            When AI models are invoked, they will appear here.
        </x-ui.empty-state>
    @else
        @foreach($calls as $call)
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <x-ui.status-pill state="ok" label="{{ $call->model_served ?? $call->model }}" />
                    <span class="ml-2 text-ink-2">{{ $call->ttft_ms }}ms TTFT | {{ $call->cost_cents ?? ($call->cost_hundredths_cents / 100) }}¢ cost</span>
                </div>
                <x-ui.button wire:click="retry({{ $call->id }})" size="default" variant="secondary">Retry</x-ui.button>
            </div>
        @endforeach
    @endif
</div>
