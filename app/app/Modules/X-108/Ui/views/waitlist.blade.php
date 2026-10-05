<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Waiting for a slot</h2>

        @if($notice)
            <x-ui.attention-card state="ok">{{ $notice }}</x-ui.attention-card>
        @endif
        @if($error)
            <x-ui.attention-card state="attention">{{ $error }}</x-ui.attention-card>
        @endif

        @if($waitlists->isEmpty())
            <x-ui.empty-state icon="○" heading="Nobody is waiting">
                When a customer asks to hear about an opening, they appear here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($waitlists as $w)
                    <li class="text-ink">
                        {{ $w->customer_name }} · {{ $w->service_name }} · {{ $w->preferred_date->format('M j') }} · {{ ucfirst($w->status) }}
                        @if(in_array($w->status, ['pending', 'offered'], true))
                            @if($w->preferred_date->lt(today()))
                                <div class="mt-1 text-sm text-ink-2">Their day has passed.</div>
                            @elseif(($times[$w->id] ?? []) === [])
                                <div class="mt-1 text-sm text-ink-2">No free time left that day.</div>
                            @else
                                <div class="mt-1 flex flex-wrap gap-2">
                                    @foreach($times[$w->id] as $time)
                                        <x-ui.button wire:click="book({{ $w->id }}, '{{ $time['start_time'] }}')" wire:loading.attr="disabled" wire:target="book" size="default" variant="secondary">
                                            Book {{ $time['formatted_window'] }}
                                        </x-ui.button>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
