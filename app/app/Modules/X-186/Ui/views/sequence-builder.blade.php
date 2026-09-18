<div>
    <div class="p-4 space-y-6">
        <h2 class="text-lg font-bold text-ink">Broadcast Composer</h2>

        @if ($isSample)
            <x-ui.sample />
        @endif
        
        <div class="bg-card border border-rule rounded-lg p-4 space-y-4">
            <h3 class="font-medium text-ink">Compose New Sequence</h3>
            
            @if ($composeError)
                <x-ui.status-pill state="alert" label="{{ $composeError }}" />
            @endif

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="w-full sm:w-auto">
                    <label for="newCampaignId" class="block text-sm font-medium text-ink-2 mb-1">Campaign ID</label>
                    <input type="text" id="newCampaignId" wire:model="newCampaignId" placeholder="Campaign ID" class="w-full sm:w-auto min-h-11 rounded-[--radius-field] border border-rule bg-paper px-4 text-base text-ink" />
                </div>
                <div class="w-full sm:w-auto">
                    <label for="newChannel" class="block text-sm font-medium text-ink-2 mb-1">Channel</label>
                    <select id="newChannel" wire:model="newChannel" class="w-full sm:w-auto min-h-11 rounded-[--radius-field] border border-rule bg-paper px-4 text-base text-ink">
                        <option value="sms">SMS</option>
                        <option value="email">Email</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <label for="newTemplateName" class="block text-sm font-medium text-ink-2 mb-1">Template Name</label>
                    <input type="text" id="newTemplateName" wire:model="newTemplateName" placeholder="Template Name" class="w-full sm:w-auto min-h-11 rounded-[--radius-field] border border-rule bg-paper px-4 text-base text-ink" />
                </div>
                <div class="w-full sm:w-auto">
                    <x-ui.button wire:click="compose" class="w-full sm:w-auto" size="default">Create</x-ui.button>
                </div>
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
                <x-ui.empty-state icon="○" heading="No sequence yet — compose one above">
                    Your campaigns will appear here once built.
                </x-ui.empty-state>
            @else
                <div class="space-y-6">
                    @foreach($campaigns as $campaignId => $steps)
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-ink">{{ $campaignId }} ({{ $steps->count() }} steps)</div>
                                <x-ui.button wire:click="duplicate('{{ $campaignId }}')" size="default">
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
