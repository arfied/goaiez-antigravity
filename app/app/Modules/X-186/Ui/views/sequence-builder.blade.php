<div>
    <div class="p-4 space-y-6">
        <h3 class="text-lg font-bold text-ink">Broadcast Composer</h3>

        @if ($isSample)
            <x-ui.sample />
        @endif
        
        <div class="bg-card border border-rule rounded-lg p-4 space-y-4">
            <h4 class="font-medium text-ink">Compose New Sequence</h4>
            <div class="flex gap-4">
                <input type="text" wire:model="newCampaignId" placeholder="Campaign ID" class="border border-rule rounded px-3 py-1" />
                <select wire:model="newChannel" class="border border-rule rounded px-3 py-1">
                    <option value="sms">SMS</option>
                    <option value="email">Email</option>
                </select>
                <input type="text" wire:model="newTemplateName" placeholder="Template Name" class="border border-rule rounded px-3 py-1" />
                <x-ui.button wire:click="compose" size="sm">Create</x-ui.button>
            </div>
        </div>

        <div wire:loading.delay wire:target="duplicate, compose">
            <x-ui.skeleton label="Loading campaigns…" :lines="3" />
        </div>

        <div wire:loading.delay.remove wire:target="duplicate, compose">
            @if ($failed)
                <x-ui.error-panel heading="We couldn't load the campaigns" retry="$refresh">
                    There was an error communicating with the database.
                </x-ui.error-panel>
            @elseif ($campaigns->isEmpty())
                <x-ui.empty-state icon="○" heading="No sequences built yet">
                    Your campaigns will appear here once built.
                </x-ui.empty-state>
            @else
                <div class="space-y-6">
                    @foreach($campaigns as $campaignId => $steps)
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-ink">{{ $campaignId }}</div>
                                <x-ui.button wire:click="duplicate('{{ $campaignId }}')" size="sm">
                                    Duplicate
                                </x-ui.button>
                            </div>
                            <x-ui.row-list>
                                @foreach($steps as $step)
                                    <x-ui.row>
                                        <div class="flex justify-between w-full">
                                            <div>
                                                <span class="font-medium">Step {{ $step->step_number }}:</span> 
                                                {{ $step->channel }} ({{ $step->template_name }})
                                            </div>
                                            <div class="text-sm text-ink-2">
                                                Delay: {{ $step->delay_days }} days
                                            </div>
                                        </div>
                                    </x-ui.row>
                                @endforeach
                            </x-ui.row-list>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
