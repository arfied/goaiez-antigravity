<div>
    <livewire:x-124.chat-dock-every />
    
    <div class="max-w-md mx-auto px-4 py-6" wire:loading.class="opacity-50">
        @if($errorMessage)
            <x-ui.error-panel heading="Error" class="mb-6">
                {{ $errorMessage }}
            </x-ui.error-panel>
        @endif

        @if($jobs->isEmpty())
            <x-ui.empty-state 
                heading="No jobs today"
                action="Refresh"
                target="refreshApp"
                icon="✓">
                You have no jobs scheduled for today. Enjoy your time off or tap to refresh.
            </x-ui.empty-state>
        @else
            <div class="space-y-4 mb-8">
                <h2 class="text-xl font-bold">Today's Jobs</h2>
                @foreach($jobs as $job)
                    <div class="bg-paper rounded-xl border border-rule p-4" wire:key="job-{{ $job->job_id }}">
                        @if($job->da_sample)
                            <div class="mb-2">
                                <x-ui.status-pill state="attention" label="Sample" />
                            </div>
                        @endif
                        
                        <h3 class="font-medium text-ink text-lg">{{ $job->title }}</h3>
                        <p class="text-sm text-ink-2 mb-1">Scheduled: {{ \Carbon\Carbon::parse($job->scheduled_at)->format('g:i A') }}</p>
                        <p class="text-sm font-semibold mb-4">Status: {{ $job->status ?? 'pending' }}</p>

                        <div class="grid grid-cols-2 gap-2">
                            <button wire:click="tap({{ $job->job_id }}, 'en_route')" class="h-12 bg-accent text-white rounded font-medium">En route</button>
                            <button wire:click="tap({{ $job->job_id }}, 'on_site')" class="h-12 bg-accent text-white rounded font-medium">On site</button>
                            <button wire:click="tap({{ $job->job_id }}, 'completed')" class="h-12 bg-accent text-white rounded font-medium">Done (+ Photo)</button>
                            <div class="flex gap-2">
                                <input type="text" wire:model="signatureInput.{{ $job->job_id }}" placeholder="Signature" class="w-20 border rounded px-1">
                                <button wire:click="sign({{ $job->job_id }})" class="flex-1 h-12 bg-paper border border-rule text-ink rounded font-medium">Sign</button>
                            </div>
                            <div class="flex gap-2">
                                <input type="text" wire:model="scanInput.{{ $job->job_id }}" placeholder="Barcode" class="w-20 border rounded px-1">
                                <button wire:click="scan({{ $job->job_id }})" class="flex-1 h-12 bg-paper border border-rule text-ink rounded font-medium">Scan</button>
                            </div>
                            <div class="flex gap-2">
                                <input type="text" wire:model="voiceInput.{{ $job->job_id }}" placeholder="Note" class="w-20 border rounded px-1">
                                <button wire:click="voiceNote({{ $job->job_id }})" class="flex-1 h-12 bg-paper border border-rule text-ink rounded font-medium">Voice note</button>
                            </div>
                            <div class="flex gap-2">
                                <input type="text" wire:model="photoInput.{{ $job->job_id }}" placeholder="Photo" class="w-20 border rounded px-1">
                                <button wire:click="photo({{ $job->job_id }})" class="flex-1 h-12 bg-paper border border-rule text-ink rounded font-medium">Photo</button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <button disabled class="w-full h-12 bg-gray-200 text-gray-500 rounded font-medium cursor-not-allowed" title="payment arrives with the money track">Pay on site</button>
                            <p class="text-xs text-center text-gray-500 mt-1">payment arrives with the money track</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($conflicts->isNotEmpty())
            <div class="space-y-4">
                <h2 class="text-xl font-bold text-alert">Sync Conflicts</h2>
                @foreach($conflicts as $conflict)
                    <div class="bg-alert-bg rounded-xl border border-alert p-4" wire:key="conflict-{{ $conflict->id }}">
                        @if($conflict->is_sample)
                            <div class="mb-2">
                                <x-ui.status-pill state="attention" label="Sample" />
                            </div>
                        @endif
                        <p class="text-sm font-medium text-alert mb-2">Conflict on action</p>
                        <p class="text-xs text-alert mb-4">{{ $conflict->conflict_reason }}</p>
                        
                        @if(!str_starts_with($conflict->conflict_reason, 'Resolved'))
                            <div class="flex gap-2">
                                <button wire:click="resolveConflict({{ $conflict->id }}, 'keep_mine')" class="flex-1 h-10 bg-alert text-white rounded font-medium text-sm">Keep mine</button>
                                <button wire:click="resolveConflict({{ $conflict->id }}, 'keep_server')" class="flex-1 h-10 bg-paper border border-alert text-alert rounded font-medium text-sm">Keep server</button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
