<x-surface.sample-state module="C-Agent" screen="thread" />
<div>
    <div class="agent-thread-container p-4">
        <h3 class="text-lg font-bold">Agent Turns Thread</h3>
        @if($turns->isEmpty())
            <p class="text-gray-500">No agent turns recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($turns as $turn)
                    <li class="py-2">
                        <p class="text-sm font-semibold">User: {{ $turn->user_message }}</p>
                        <p class="text-sm text-gray-700">Agent: {{ $turn->agent_reply }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
