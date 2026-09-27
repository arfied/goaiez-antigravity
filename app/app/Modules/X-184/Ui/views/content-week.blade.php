<div>
    <div class="p-4 space-y-4">
        <h2 class="text-lg font-bold text-ink">Posts planned for your week</h2>

        <form wire:submit="proposePlan" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <div class="font-bold text-ink">Propose Plan</div>
            <x-ui.toast kind="success" :message="$success" />
            <x-ui.toast kind="error" :message="$error" />

            <label class="text-sm text-ink-2">Week Label</label>
            <input type="text" wire:model="weekLabel" class="border rounded p-2 text-ink flex-1 bg-surface">

            <label class="text-sm text-ink-2">Source Event</label>
            <input type="text" wire:model="itemSourceEvent" class="border rounded p-2 text-ink flex-1 bg-surface">

            <label class="text-sm text-ink-2">Topic Theme</label>
            <input type="text" wire:model="itemTopicTheme" class="border rounded p-2 text-ink flex-1 bg-surface">

            <label class="text-sm text-ink-2">Channel</label>
            <input type="text" wire:model="itemChannel" class="border rounded p-2 text-ink flex-1 bg-surface">

            <div>
                <button type="submit" class="bg-surface text-ink border rounded p-2">Propose</button>
            </div>
        </form>

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
                                                    <div class="text-sm text-ink-2">Date: {{ $item->scheduled_date->toFormattedDateString() }}</div>
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
