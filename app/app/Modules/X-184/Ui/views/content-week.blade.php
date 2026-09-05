<div>
    <div class="p-4 space-y-4">
        <h3 class="text-lg font-bold text-ink">Weekly Content Cadence Proposal</h3>
        
        @if ($isSample)
            <x-ui.sample />
        @endif

        <div wire:loading.delay wire:target="approveCadence, scheduleItem">
            <x-ui.skeleton label="Loading content plans…" :lines="3" />
        </div>

        <div wire:loading.delay.remove wire:target="approveCadence, scheduleItem">
            @if ($failed)
                <x-ui.error-panel heading="We couldn't load the content plans" retry="$refresh">
                    There was an error communicating with the database.
                </x-ui.error-panel>
            @elseif ($plans->isEmpty())
                <x-ui.empty-state icon="○" heading="No content week planned yet">
                    Your content plans will appear here once generated.
                </x-ui.empty-state>
            @else
                <div class="space-y-6">
                    @foreach($plans as $plan)
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-medium text-ink">{{ $plan->week_label }}</div>
                                    <div class="text-sm text-ink-2">{{ $plan->posts_per_week_cadence }} posts per week</div>
                                </div>
                                <div>
                                    @if($plan->is_cadence_approved)
                                        <x-ui.status-pill state="ok" label="Approved">Approved</x-ui.status-pill>
                                    @else
                                        <x-ui.button wire:click="approveCadence({{ $plan->id }})" size="sm">
                                            Approve Cadence
                                        </x-ui.button>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="pl-4 border-l-2 border-rule">
                                <x-ui.row-list>
                                    @foreach($plan->items as $item)
                                        <x-ui.row>
                                            <div class="flex items-center justify-between w-full">
                                                <div>
                                                    <div class="font-medium text-ink">{{ $item->channel }} - {{ $item->topic_theme }}</div>
                                                    <div class="text-sm text-ink-2">Source: {{ $item->source_event }}</div>
                                                    <div class="text-sm text-ink-2">Date: {{ $item->scheduled_date }}</div>
                                                </div>
                                                <div>
                                                    @if($item->is_scheduled)
                                                        <x-ui.status-pill state="ok" label="Scheduled">Scheduled</x-ui.status-pill>
                                                    @else
                                                        <x-ui.button wire:click="scheduleItem({{ $item->id }})" size="sm">
                                                            Schedule
                                                        </x-ui.button>
                                                    @endif
                                                </div>
                                            </div>
                                        </x-ui.row>
                                    @endforeach
                                </x-ui.row-list>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
