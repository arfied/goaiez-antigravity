<div>
    <div class="ops-console-view p-4">
        <h3 class="text-lg font-bold">Operator Control Center Console</h3>
        <div class="mt-4">
            <h4 class="font-semibold">Operator Alerts</h4>
            @if($alerts->isEmpty())
                <p class="text-gray-500">No active operator alerts.</p>
            @else
                <ul>
                    @foreach($alerts as $a)
                        <li>#{{ $a->id }}: [{{ $a->severity }}] {{ $a->action_verb_message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="mt-4">
            <h4 class="font-semibold">Escalated Tickets</h4>
            @if($tickets->isEmpty())
                <p class="text-gray-500">No support tickets.</p>
            @else
                <ul>
                    @foreach($tickets as $t)
                        <li>#{{ $t->id }}: [{{ $t->source }}] {{ $t->category }} (Due: {{ $t->sla_due_at }})</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
