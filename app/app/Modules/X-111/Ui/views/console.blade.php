<div>
    <x-ui.systems-strip module="X-111" />

    <div class="ops-console-view p-4">
        <h3 class="text-lg font-bold mb-6">Operator Control Center Console</h3>
        
        <div wire:loading class="mb-4">
            <x-ui.skeleton label="Loading console..." />
        </div>

        @if($error)
            <x-ui.error-panel heading="Error">{{ $error }}</x-ui.error-panel>
        @endif
        @if($success)
            <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $success }}</div>
        @endif

        <form wire:submit="createTicket" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <textarea wire:model="fullTranscript" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Full Transcript"></textarea>
            <input type="text" wire:model="category" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Category">
            <x-ui.button type="submit">Submit</x-ui.button>
        </form>

        @if($errorMessage)
            <x-ui.error-panel heading="We could not load the console.">{{ $errorMessage }}</x-ui.error-panel>
        @else
            <div class="mt-4">
            <h4 class="font-semibold mb-2">Operator Alerts</h4>
            @if($alerts->isEmpty())
                <x-ui.empty-state heading="No alerts">No active operator alerts.</x-ui.empty-state>
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
                <x-ui.empty-state heading="No tickets">No support tickets.</x-ui.empty-state>
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
