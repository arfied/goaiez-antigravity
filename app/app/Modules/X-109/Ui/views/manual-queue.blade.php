<div>
    <div class="manual-queue-view p-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Manual Form Review Queue</h3>
            <x-ui.button wire:click="toggleSample" size="default" variant="secondary">
                {{ $isSample ? 'Hide sample' : 'Show sample' }}
            </x-ui.button>
        </div>
        
        @if ($actionFailed)
            <x-ui.alert type="error" class="mb-4">
                Action failed.
            </x-ui.alert>
        @endif

        @if ($attentionMessage)
            <x-ui.alert type="info" class="mb-4">
                {{ $attentionMessage }}
            </x-ui.alert>
        @endif
        
        @if ($queue->isEmpty())
            <x-ui.empty-state icon="check-circle" title="Queue is empty" description="No parked submissions requiring manual review." />
        @else
            <x-ui.table>
                <x-slot name="head">
                    <x-ui.table.heading>Campaign</x-ui.table.heading>
                    <x-ui.table.heading>Prospect</x-ui.table.heading>
                    <x-ui.table.heading>Reason</x-ui.table.heading>
                    <x-ui.table.heading>Action</x-ui.table.heading>
                </x-slot>
                <x-slot name="body">
                    @foreach ($queue as $item)
                        <x-ui.table.row>
                            <x-ui.table.cell>{{ is_object($item) ? $item->campaign_id : '' }}</x-ui.table.cell>
                            <x-ui.table.cell>{{ is_object($item) ? $item->prospect_identifier : '' }}</x-ui.table.cell>
                            <x-ui.table.cell>Quota exhausted</x-ui.table.cell>
                            <x-ui.table.cell>
                                <form wire:submit="resubmit({{ is_object($item) ? $item->id : 0 }})">
                                    <x-ui.submit size="sm">Re-submit</x-ui.submit>
                                </form>
                            </x-ui.table.cell>
                        </x-ui.table.row>
                    @endforeach
                </x-slot>
            </x-ui.table>
        @endif
    </div>
</div>
