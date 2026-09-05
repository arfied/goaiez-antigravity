<div>
    <div class="submission-log-view p-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Contact Form Submission Log</h3>
            <x-ui.button wire:click="toggleSample" size="default" variant="secondary">
                {{ $isSample ? 'Hide sample' : 'Show sample' }}
            </x-ui.button>
        </div>
        
        @if ($logs->isEmpty())
            <x-ui.empty-state icon="document-text" title="No submissions yet" description="No contact forms have been submitted for this tenant." />
        @else
            <x-ui.table>
                <x-slot name="head">
                    <x-ui.table.heading>Campaign</x-ui.table.heading>
                    <x-ui.table.heading>Prospect</x-ui.table.heading>
                    <x-ui.table.heading>Quota Remaining</x-ui.table.heading>
                    <x-ui.table.heading>Submitted At</x-ui.table.heading>
                </x-slot>
                <x-slot name="body">
                    @foreach ($logs as $log)
                        <x-ui.table.row>
                            <x-ui.table.cell>{{ is_object($log) ? $log->campaign_id : '' }}</x-ui.table.cell>
                            <x-ui.table.cell>{{ is_object($log) ? $log->prospect_identifier : '' }}</x-ui.table.cell>
                            <x-ui.table.cell>{{ is_object($log) ? $log->available_quota : '' }}</x-ui.table.cell>
                            <x-ui.table.cell>{{ is_object($log) ? $log->updated_at : '' }}</x-ui.table.cell>
                        </x-ui.table.row>
                    @endforeach
                </x-slot>
            </x-ui.table>
        @endif
    </div>
</div>
