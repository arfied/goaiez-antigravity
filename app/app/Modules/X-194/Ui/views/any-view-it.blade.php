<div>
    <h2 class="text-lg font-bold text-ink">View</h2>
    @if ($errorMessage)
        <x-ui.error-panel heading="We could not render your view." retry="load">
            {{ $errorMessage }}
        </x-ui.error-panel>
    @elseif (! $ready)
        <x-ui.skeleton label="Loading view..." />
    @elseif (! $viewData)
        <x-ui.empty-state heading="No view selected">
            Please select a view to see its details.
        </x-ui.empty-state>
    @else
        <div class="view-header">
            <h3>{{ $viewData['view_name'] }}</h3>
            <p class="timezone-display">Timezone: {{ $viewData['timezone'] }}</p>
        </div>
        
        @php
            $context = app(\App\Services\Tenant\LocationContext::class);
        @endphp
        @if ($context->hasChoice())
            <div class="mt-4">
                <x-account.location-picker :locations="$context->options()" :selected="$context->current()" />
            </div>
        @endif

        <div class="flex gap-4 mt-4">
            @if ($viewData['job_count'] > 0)
                <div class="border p-4 rounded tile">
                    <h4>Count</h4>
                    <p data-job-count="{{ $viewData['job_count'] }}">{{ $viewData['job_count'] }}</p>
                </div>
            @endif
            <div class="border p-4 rounded tile">
                <h4>Estimate</h4>
                <p data-estimate-tile="{{ $viewData['estimate_tile'] }}">{{ $viewData['estimate_tile'] }}</p>
            </div>
        </div>
    @endif
</div>
