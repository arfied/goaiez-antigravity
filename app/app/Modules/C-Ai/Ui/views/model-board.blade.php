<x-surface.sample-state module="C-Ai" screen="model_board" />
<div>
    <div class="model-board-container p-4">
        <h3 class="text-lg font-bold">AI Model Board & Invocation Engine</h3>
        @if($calls->isEmpty())
            <p class="text-gray-500">No model invocations recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($calls as $call)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $call->model_served }}</span>
                        <span class="text-xs text-gray-500">{{ $call->ttft_ms }}ms TTFT | {{ $call->cost_cents }}¢ cost</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
