<div>
    <div class="loss-alerts p-4 max-w-lg mx-auto md:max-w-4xl">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-ink">Loss alerts</h2>
            <div class="flex items-center space-x-2">
                <x-ui.button wire:click="toggleSample" size="default" variant="secondary">
                    {{ $isSample ? 'Exit Sample' : 'Sample' }}
                </x-ui.button>
            </div>
        </div>

        @if($actionNotice)
            <div class="mb-4 p-2 rounded {{ $noticeType === 'success' ? 'bg-green-100 text-green-800' : ($noticeType === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                {{ $actionNotice }}
            </div>
        @endif

        @if($isSample)
            <div class="mb-4 p-2 bg-yellow-100 text-yellow-800 rounded text-sm font-bold">
                SAMPLE DATA
                <span class="block font-normal">Sample mode, actions are off. Submit, Alert Team, Prepare Removal and Confirm Removal do nothing on sample rows. Exit Sample to act on your own.</span>
            </div>
        @endif

        @if($isEmpty)
            <x-ui.empty-state heading="No customers at risk right now" action="Show a sample" target="toggleSample">
                A row appears here when a ticket passes its SLA, a review falls below the public threshold with no ticket resolved, or a resolved request gets a CSAT under 7.
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
                            <div class="text-xs text-ink-2">Risk Level: {{ $alert->risk_level }}</div>
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
                                @if($preparingReviewId === $alert->id)
                                    <div class="flex flex-col space-y-2">
                                        <input type="text" wire:model="prepareTosGround" class="border p-1 text-sm rounded" placeholder="ToS Ground...">
                                        <input type="text" wire:model="prepareGoogleId" class="border p-1 text-sm rounded" placeholder="Google Review ID...">
                                        <textarea wire:model="prepareBody" class="border p-1 text-sm rounded" placeholder="Explanation..."></textarea>
                                        <div class="flex space-x-2">
                                            <x-ui.button wire:click="prepareRemoval({{ $alert->id }}, $wire.prepareTosGround, $wire.prepareBody, $wire.prepareGoogleId)" size="default" variant="primary">Submit Request</x-ui.button>
                                            <x-ui.button wire:click="cancelPrepare" size="default" variant="quiet">Cancel</x-ui.button>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex flex-col space-y-2">
                                        <x-ui.button wire:click="alertTeam({{ $alert->id }})" size="default" variant="primary">Alert Team</x-ui.button>
                                        <x-ui.button wire:click="startPrepare({{ $alert->id }})" size="default" variant="secondary">Prepare Removal</x-ui.button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
        @if(isset($removalRequests) && $removalRequests->isNotEmpty())
            <div class="mt-8">
                <h4 class="text-lg font-bold mb-4">Google Review Removal Requests</h4>
                <div class="space-y-4">
                    @foreach($removalRequests as $req)
                        <div class="border rounded p-4 flex flex-col md:flex-row justify-between items-start md:items-center">
                            <div>
                                <div class="font-bold">Removal for Review #{{ $req->review_request_id }}</div>
                                <div class="text-sm text-ink-2">ToS Ground: {{ $req->tos_ground }}</div>
                                <div class="text-sm text-ink-2">Status: {{ $req->status }}</div>
                            </div>
                            <div class="mt-4 md:mt-0 flex space-x-2">
                                @if($req->status === 'prepared')
                                    <x-ui.button wire:click="confirmRemoval({{ $req->id }})" size="default" variant="primary">Confirm Removal</x-ui.button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-8">
            <slot name="assistant"></slot>
        </div>
    </div>
</div>
