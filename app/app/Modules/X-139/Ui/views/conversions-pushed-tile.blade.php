<div>
    <div class="conversions-pushed-view p-4">
        @if($count > 0)
            <h2 class="text-lg font-bold">Uploaded Conversions: {{ $count }} (${{ number_format($value, 2) }})</h2>
        @else
            <x-ui.empty-state heading="No conversions have been uploaded.">Sending sales back to your ads waits on an ad-platform connection, which cannot be set up yet.</x-ui.empty-state>
        @endif
    </div>
</div>
