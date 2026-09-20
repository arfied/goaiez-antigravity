<div>
    <x-ui.systems-strip module="X-111" />

    <div class="ops-console-view p-4">
        <h3 class="text-lg font-bold mb-6">Operator Control Center Console</h3>
        
        <div wire:loading class="mb-4">
            <x-ui.skeleton label="Loading console..." />
        </div>

        @if($errorMessage)
            <x-ui.error-panel heading="We could not load the console.">{{ $errorMessage }}</x-ui.error-panel>
        @else
            <div class="mt-4">
            <h4 class="font-semibold mb-2">Operator Alerts</h4>
            @if($alerts->isEmpty())
                <x-ui.empty-state heading="No alerts" description="No active operator alerts." />
            @else
                <ul class="divide-y divide-gray-200">
                    @foreach($alerts as $a)
                        <li class="py-3 flex justify-between items-center">
                            <div>
                                <span class="font-medium">#{{ $a->id }}: [{{ $a->severity }}]</span>
                                <p class="text-sm text-gray-700">{{ $a->action_verb_message }}</p>
                                <x-ui.status-pill :status="$a->status" />
                            </div>
                            <x-ui.button wire:click="resolveAlert({{ $a->id }})">Resolve</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        
        <div class="mt-8">
            <h4 class="font-semibold mb-2">Escalated Tickets</h4>
            @if($tickets->isEmpty())
                <x-ui.empty-state heading="No tickets" description="No support tickets." />
            @else
                <ul class="divide-y divide-gray-200">
                    @foreach($tickets as $t)
                        <li class="py-3 flex justify-between items-center">
                            <div>
                                <span class="font-medium">#{{ $t->id }}: [{{ $t->source }}] {{ $t->category }}</span>
                                <p class="text-sm text-gray-500">Due: {{ $t->sla_due_at }}</p>
                                <x-ui.status-pill :status="$t->status" />
                            </div>
                            <x-ui.button wire:click="resolveTicket({{ $t->id }})">Resolve</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @endif
    </div>
</div>
