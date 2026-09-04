<div wire:init="load">
    @if ($errorMessage)
        <x-ui.error-panel heading="We could not render your view." retry="load">
            {{ $errorMessage }}
        </x-ui.error-panel>
    @elseif (! $ready)
        <x-ui.skeleton label="Loading view..." />
    @elseif (! $viewData)
        <x-ui.empty-state heading="View not found">
            The requested view could not be found.
        </x-ui.empty-state>
    @else
        <div class="view-header">
            <h3>{{ $viewData['view_name'] }}</h3>
            <p class="timezone-display">Timezone: {{ $viewData['timezone'] }}</p>
        </div>
        
        <div class="flex gap-4 mt-4">
            <div class="border p-4 rounded tile">
                <h4>Count</h4>
                <p>{{ $viewData['job_count'] }}</p>
            </div>
            <div class="border p-4 rounded tile">
                <h4>Estimate</h4>
                <p>{{ $viewData['estimate_tile'] }}</p>
            </div>
        </div>
    @endif
</div>
