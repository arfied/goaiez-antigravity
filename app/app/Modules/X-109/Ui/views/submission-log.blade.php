<div>
    <div class="submission-log-view p-4">
        <div class="flex justify-end items-center mb-4">
            <x-ui.button wire:click="toggleSample" size="default" variant="secondary">
                {{ $isSample ? 'Hide sample' : 'Show sample' }}
            </x-ui.button>
        </div>
        
        @if ($logs->isEmpty())
            <x-ui.empty-state heading="No submissions yet">No contact forms have been submitted for this tenant.</x-ui.empty-state>
        @else
            <table class="w-full text-left border-collapse border">
                <thead>
                    <tr>
                        <th class="p-2 border">Campaign</th>
                        <th class="p-2 border">Prospect</th>
                        <th class="p-2 border">Quota Remaining</th>
                        <th class="p-2 border">Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="p-2 border">{{ is_object($log) ? $log->campaign_id : '' }}</td>
                            <td class="p-2 border">{{ is_object($log) ? $log->prospect_identifier : '' }}</td>
                            <td class="p-2 border">{{ is_object($log) ? $log->available_quota : '' }}</td>
                            <td class="p-2 border">{{ is_object($log) ? $log->updated_at : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
