<div>
    <div class="manual-queue-view p-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Manual Form Review Queue</h3>
            <x-ui.button wire:click="toggleSample" size="default" variant="secondary">
                {{ $isSample ? 'Hide sample' : 'Show sample' }}
            </x-ui.button>
        </div>
        
        @if ($actionFailed)
            <div class="text-red-500 mb-4 bg-red-100 p-2 rounded">
                Action failed.
            </div>
        @endif

        @if ($attentionMessage)
            <div class="text-blue-500 mb-4 bg-blue-100 p-2 rounded">
                {{ $attentionMessage }}
            </div>
        @endif
        
        @if ($queue->isEmpty())
            <x-ui.empty-state heading="Queue is empty">No parked submissions requiring manual review.</x-ui.empty-state>
        @else
            <table class="w-full text-left border-collapse border">
                <thead>
                    <tr>
                        <th class="p-2 border">Campaign</th>
                        <th class="p-2 border">Prospect</th>
                        <th class="p-2 border">Reason</th>
                        <th class="p-2 border">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($queue as $item)
                        <tr>
                            <td class="p-2 border">{{ is_object($item) ? $item->campaign_id : '' }}</td>
                            <td class="p-2 border">{{ is_object($item) ? $item->prospect_identifier : '' }}</td>
                            <td class="p-2 border">Quota exhausted</td>
                            <td class="p-2 border">
                                <form wire:submit="resubmit({{ is_object($item) ? $item->id : 0 }})">
                                    <x-ui.submit size="sm" target="resubmit({{ is_object($item) ? $item->id : 0 }})" busy="Submitting...">Re-submit</x-ui.submit>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
