<div>
    <div class="pipeline-board-view p-4">
        <div class="flex justify-end mb-6">
            <x-ui.button wire:click="toggleSample" size="default" variant="secondary">{{ $isSample ? 'Hide sample' : 'Show sample' }}</x-ui.button>
        </div>

        @if($actionFailed)
            <div class="text-red-500 mb-4">Action failed.</div>
        @endif

        <x-ui.toast kind="success" :message="$success" />
        <x-ui.toast kind="error" :message="$error" />

        <form wire:submit="startOutreach" class="mb-6 flex flex-col gap-2 bg-paper p-4 rounded mt-4 shadow-sm border text-ink">
            <h2 class="font-bold">Start outreach to a new prospect</h2>
            <input type="text" wire:model="prospectName" placeholder="Name" class="border rounded p-2 flex-1">
            <input type="text" wire:model="prospectEmail" placeholder="Email" class="border rounded p-2 flex-1">
            <button type="submit" class="bg-surface text-ink border rounded p-2 font-bold">Start Outreach</button>
        </form>

        @if($ladders->isEmpty())
            <x-ui.empty-state heading="No ladders found">There are no active outreach ladders for this tenant.</x-ui.empty-state>
        @else
            <div class="space-y-4">
                @foreach($ladders as $ladder)
                    @php
                        $pendingStep = collect($ladder->steps)->where('status', 'pending')->first();
                        $stepCount = collect($ladder->steps)->count();
                    @endphp
                    <div class="border p-4 rounded bg-paper shadow-sm flex flex-col gap-2">
                        <div class="flex justify-between">
                            <span class="font-bold">Ladder #{{ $ladder->id }}</span>
                            <span class="text-ink text-sm">Status: {{ $ladder->status }}</span>
                        </div>
                        <div class="text-sm">
                            <p>Person: {{ optional($ladder->person)->name ?? 'Person #'.$ladder->person_id }}</p>
                            <p>Steps: {{ $stepCount }} (Next: {{ $pendingStep ? $pendingStep->type : 'None' }})</p>
                            <p>Last touch: {{ optional($ladder->updated_at)->diffForHumans() }}</p>
                        </div>
                        
                        <div class="flex gap-2 mt-2">
                            <form wire:submit="halt({{ $ladder->id }})">
                                <x-ui.submit target="halt({{ $ladder->id }})" busy="Halting...">Halt</x-ui.submit>
                            </form>
                            
                            <form wire:submit="requestDemo({{ $ladder->id }}, 'Next Tuesday')">
                                <x-ui.submit target="requestDemo({{ $ladder->id }}, 'Next Tuesday')" busy="Requesting...">Request Demo</x-ui.submit>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
