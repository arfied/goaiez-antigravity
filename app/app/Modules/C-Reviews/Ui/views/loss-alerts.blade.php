<div>
    <div class="loss-alerts p-4 max-w-lg mx-auto md:max-w-4xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Customer Loss and Churn Risk Alerts</h3>
            <div class="flex items-center space-x-2">
                <button wire:click="toggleSample" class="text-sm px-2 py-1 bg-gray-200 rounded">
                    {{ $isSample ? 'Exit Sample' : 'Sample' }}
                </button>
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
            <div class="text-gray-500 py-8 text-center border rounded">
                <p>so what do I do?</p>
                <p class="text-sm">Currently no high-risk customers or SLA breaches.</p>
            </div>
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
                                    <button wire:click="resolveAndAlert({{ $alert->id }}, resolutionNotes)" class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Submit</button>
                                    <button wire:click="cancelResolve" class="text-gray-500 px-2 py-1 text-sm">Cancel</button>
                                @else
                                    <button wire:click="startResolve({{ $alert->id }})" class="bg-red-600 text-white px-3 py-1 rounded text-sm font-semibold">Resolve and Alert</button>
                                @endif
                            @else
                                <button wire:click="alertTeam({{ $alert->id }})" class="bg-orange-500 text-white px-3 py-1 rounded text-sm font-semibold">Alert Team</button>
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
