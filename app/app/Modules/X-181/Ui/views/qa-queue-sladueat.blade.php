<div>
    <div class="sla-queue-container p-4 max-w-lg mx-auto md:max-w-4xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">QA Queue and SLA Due Watch</h3>
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
                <p class="text-sm">Currently no tickets in the QA queue.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($tickets as $t)
                    <div class="border rounded p-4 flex flex-col md:flex-row justify-between items-start md:items-center {{ $t->is_breached ? 'bg-red-50' : '' }}">
                        <div>
                            <div class="font-bold">Ticket #{{ $t->id }}</div>
                            <div class="text-sm text-gray-700">{{ $t->subject }}</div>
                            <div class="text-xs {{ $t->is_breached ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                                Due: {{ $t->sla_due_at ? $t->sla_due_at->diffForHumans() : 'N/A' }}
                                @if($t->is_breached) (BREACHED) @endif
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-2">
                            @if($resolvingTicketId === $t->id)
                                <input type="text" wire:model="resolutionNotes" class="border p-1 text-sm rounded" placeholder="Resolution notes...">
                                <button wire:click="resolve({{ $t->id }}, resolutionNotes)" class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Submit</button>
                                <button wire:click="cancelResolve" class="text-gray-500 px-2 py-1 text-sm">Cancel</button>
                            @else
                                <button wire:click="startResolve({{ $t->id }})" class="bg-blue-600 text-white px-3 py-1 rounded text-sm font-semibold">Resolve</button>
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
