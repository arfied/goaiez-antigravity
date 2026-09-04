<div>
    <livewire:x-124.chat-dock-every />
    
    <div class="max-w-7xl mx-auto px-4 py-8" wire:loading.class="opacity-50">
        @if($errorMessage)
            <x-ui.error-panel class="mb-8">
                {{ $errorMessage }}
            </x-ui.error-panel>
        @endif

        @if($assignments->isEmpty())
            <x-ui.empty-state 
                heading="No dispatch assignments today"
                action="Go to Jobs"
                target="goToJobs"
                icon="✓">
                There are no jobs assigned for today. Go to Jobs to schedule and dispatch a technician.
            </x-ui.empty-state>
        @else
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @foreach(['dispatched', 'en_route', 'on_site', 'completed'] as $colStatus)
                    <div class="column bg-paper rounded-xl border border-rule p-4">
                        <h2 class="text-lg font-medium text-ink mb-4">{{ ucfirst(str_replace('_', ' ', $colStatus)) }}</h2>
                        
                        <div class="space-y-4">
                            @foreach($assignments->where('status', $colStatus) as $assignment)
                                <div class="bg-surface rounded-lg p-4 border border-rule" wire:key="assignment-{{ $assignment->id }}">
                                    @if($assignment->is_sample)
                                        <div class="mb-2">
                                            <x-ui.status-pill state="attention" label="Sample" />
                                        </div>
                                    @endif
                                    
                                    <h3 class="font-medium text-ink mb-1">
                                        {{ $workOrders->get($assignment->job_id) ?? 'Job #' . $assignment->job_id }}
                                    </h3>
                                    <p class="text-sm text-ink-2 mb-2">Tech ID: {{ $assignment->tech_id }}</p>
                                    
                                    @if($colStatus === 'en_route')
                                        @php
                                            $pred = $predictions->get($assignment->job_id)?->first();
                                        @endphp
                                        @if($pred)
                                            <p class="text-sm text-ink-2 mb-2" title="{{ $pred->estimated_arrival_at }}">
                                                en route, {{ $pred->eta_minutes }} minutes out
                                            </p>
                                        @endif
                                    @endif

                                    <div class="mt-4 pt-3 border-t border-rule flex flex-col gap-2">
                                        @if($colStatus === 'dispatched')
                                            <button wire:click="markEnRoute({{ $assignment->job_id }}, {{ $assignment->tech_id }})" 
                                                    class="h-8 px-3 text-sm font-medium text-white bg-accent rounded hover:bg-accent-hover">
                                                Mark en route
                                            </button>
                                        @endif

                                        @if(in_array($colStatus, ['en_route', 'on_site']))
                                            <button wire:click="queryEta({{ $assignment->job_id }})" 
                                                    class="h-8 px-3 text-sm font-medium text-ink bg-paper border border-rule rounded hover:bg-surface">
                                                Where is he?
                                            </button>
                                            @if(isset($queryResults[$assignment->job_id]))
                                                <p class="text-xs text-ink-2 mt-1">{{ $queryResults[$assignment->job_id] }}</p>
                                            @endif
                                        @endif

                                        <div class="flex items-center gap-2 mt-2">
                                            <input type="number" wire:model="techIds.{{ $assignment->job_id }}" 
                                                   class="w-16 h-8 px-2 text-sm border border-rule rounded bg-paper" placeholder="ID">
                                            <button wire:click="reassign({{ $assignment->job_id }})" 
                                                    class="h-8 px-3 text-sm font-medium text-ink bg-paper border border-rule rounded hover:bg-surface flex-1">
                                                Reassign
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
