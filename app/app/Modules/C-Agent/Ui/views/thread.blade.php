<div>
    <div class="agent-thread-container p-4">
        <h2 class="text-lg font-bold text-ink">Agent turns</h2>
        @if($turns->isEmpty())
            <p class="text-ink-2">No agent turns recorded.</p>
        @else
            <ul class="divide-y divide-rule">
                @foreach($turns as $turn)
                    <li class="py-2">
                        <p class="text-sm font-semibold">User: {{ $turn->user_message }}</p>
                        <p class="text-sm text-ink-2">Agent: {{ $turn->agent_reply }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
