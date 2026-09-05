<div>
    <div class="p-4 space-y-6">
        <div class="flex items-center justify-between">
            <h3 class="text-xl font-bold text-ink">Calendar</h3>
            
            <div class="flex gap-2">
                <x-ui.button wire:click="setMode('day')" :variant="$mode === 'day' ? 'primary' : 'default'">Day</x-ui.button>
                <x-ui.button wire:click="setMode('week')" :variant="$mode === 'week' ? 'primary' : 'default'">Week</x-ui.button>
                <x-ui.button wire:click="setMode('resources')" :variant="$mode === 'resources' ? 'primary' : 'default'">Resources</x-ui.button>
            </div>
        </div>

        <div wire:loading.delay wire:target="setMode, cancelAppointment">
            <x-ui.skeleton label="Loading calendar…" :lines="4" />
        </div>

        <div wire:loading.delay.remove wire:target="setMode, cancelAppointment">
            @if ($failed)
                <x-ui.error-panel heading="We couldn't load the calendar" retry="$refresh">
                    There was an error communicating with the database.
                </x-ui.error-panel>
            @elseif ($appointments->isEmpty())
                <x-ui.empty-state icon="○" heading="No appointments">
                    Your calendar is clear for this period.
                </x-ui.empty-state>
            @else
                
                @if ($mode === 'day')
                    <div class="space-y-3">
                        <x-ui.row-list>
                            @foreach ($appointments as $apt)
                                @include('x-108::partials.appointment-row', ['apt' => $apt])
                            @endforeach
                        </x-ui.row-list>
                    </div>
                @elseif ($mode === 'week')
                    <div class="space-y-6">
                        @foreach ($appointments as $date => $apts)
                            <div>
                                <h4 class="text-lg font-medium text-ink mb-3">{{ \Carbon\Carbon::parse($date)->format('l, F j') }}</h4>
                                <x-ui.row-list>
                                    @foreach ($apts as $apt)
                                        @include('x-108::partials.appointment-row', ['apt' => $apt])
                                    @endforeach
                                </x-ui.row-list>
                            </div>
                        @endforeach
                    </div>
                @elseif ($mode === 'resources')
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($appointments as $resourceId => $apts)
                            <div class="border border-rule rounded-[--radius-panel] bg-card p-4">
                                <h4 class="font-semibold text-ink mb-3">
                                    {{ $resourceId ? ($resources[$resourceId]->name ?? 'Unknown') : 'Unassigned' }}
                                </h4>
                                <div class="space-y-3">
                                    @foreach ($apts as $apt)
                                        <div class="border border-rule rounded p-3 text-sm">
                                            <div class="font-medium">{{ $apt->start_time->format('H:i') }} - {{ $apt->end_time->format('H:i') }}</div>
                                            <div class="text-ink-2">{{ $apt->first_name }} {{ $apt->last_name }}</div>
                                            <div class="mt-2">{{ $apt->service_name }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                
            @endif
        </div>
    </div>
</div>
