<div>
    <x-surface.sample-state module="rich media" screen="degrade_rate_per" />
    <div class="rcs-degrade-view p-4">
        <h3 class="text-lg font-bold">RCS vs SMS Degradation Metrics</h3>
        @if($caps->isEmpty())
            <p class="text-gray-500">No RCS device checks performed.</p>
        @else
            <ul>
                @foreach($caps as $c)
                    <li>{{ $c->phone_number }}: {{ $c->has_rcs ? 'RCS' : 'Degraded SMS' }} (Degraded: {{ $c->degraded_count }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
