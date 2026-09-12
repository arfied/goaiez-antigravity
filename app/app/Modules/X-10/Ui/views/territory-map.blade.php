<div>
    <div class="territory-map-view p-4">
        <h2 class="text-xl font-bold text-ink">Every area you have mapped</h2>
        @if($territories->isEmpty())
            <x-ui.empty-state icon="○" heading="No service areas yet">
                When you map an area you cover, it appears here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($territories as $t)
                    <li class="text-ink">{{ $t->name }} · {{ $t->assigned_staff_id !== null ? 'Assigned' : 'Not assigned yet' }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
