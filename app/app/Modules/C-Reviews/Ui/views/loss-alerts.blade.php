<div>
    <div class="loss-alerts p-4 max-w-lg mx-auto md:max-w-4xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Customer Loss and Churn Risk Alerts</h3>
            <div class="flex items-center space-x-2">
                <x-ui.button wire:click="toggleSample" size="default" variant="secondary">
                    {{ $isSample ? 'Exit Sample' : 'Sample' }}
                </x-ui.button>
            </div>
        </div>

        @if($actionNotice)
            <div class="mb-4 p-2 rounded {{ $noticeType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $actionNotice }}
            </div>
        @endif

        @if($isSample)
            <div class="mb-4 p-2 bg-yellow-100 text-yellow-800 rounded text-sm font-bold">
                SAMPLE DATA
            </div>
        @endif

        @if($isEmpty)
            <x-ui.empty-state heading="No customers at risk right now" action="Show a sample" target="toggleSample">
                A row appears here when a ticket passes its SLA, a review falls at or below the public threshold with no ticket resolved, or a resolved request gets a CSAT under 7.
            </x-ui.empty-state>
        @else
            <div class="space-y-4">
                @foreach($alerts as $alert)
                    <div class="border rounded p-4 flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div>
                            @if($alert->alert_type === 'ticket')
                                <div class="font-bold">Ticket #{{ $alert->id }}</div>
                            @else
                                <div class="font-bold">Review #{{ $alert->id }}</div>
                            @endif
                            <div class="text-sm text-red-600 font-semibold">{{ $alert->alert_reason }}</div>
                            <div class="text-xs text-gray-500">Risk Level: {{ $alert->risk_level }}</div>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-2">
                            @if($alert->alert_type === 'ticket')
                                @if($resolvingTicketId === $alert->id)
                                    <input type="text" wire:model="resolutionNotes" class="border p-1 text-sm rounded" placeholder="Resolution notes...">
                                    <x-ui.button wire:click="resolveAndAlert({{ $alert->id }}, $wire.resolutionNotes)" size="default" variant="primary">Submit</x-ui.button>
                                    <x-ui.button wire:click="cancelResolve" size="default" variant="quiet">Cancel</x-ui.button>
                                @else
                                    <x-ui.button wire:click="startResolve({{ $alert->id }})" size="default" variant="primary">Resolve and Alert</x-ui.button>
                                @endif
                            @else
                                <x-ui.button wire:click="alertTeam({{ $alert->id }})" size="default" variant="primary">Alert Team</x-ui.button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
        <div class="mt-8">
            <slot name="assistant"></slot>
        </div>
    </div>
</div>
