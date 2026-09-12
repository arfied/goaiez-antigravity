<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Waiting for a slot</h2>
        @if($waitlists->isEmpty())
            <x-ui.empty-state icon="○" heading="Nobody is waiting">
                When a customer asks to hear about an opening, they appear here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($waitlists as $w)
                    <li class="text-ink">{{ $w->customer_name }} · {{ $w->service_name }} · {{ $w->preferred_date->format('M j') }} · {{ ucfirst($w->status) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
