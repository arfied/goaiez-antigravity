<div>
    <div class="pipeline-board-view p-4">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold">Cold Outreach Pipeline Board</h3>
            <x-ui.button wire:click="toggleSample" size="default" variant="secondary">{{ $isSample ? 'Hide sample' : 'Show sample' }}</x-ui.button>
        </div>

        @if($actionFailed)
            <div class="text-red-500 mb-4">Action failed.</div>
        @endif

        @if($ladders->isEmpty())
            <x-ui.empty-state title="No ladders found" description="There are no active outreach ladders for this tenant." />
        @else
            <div class="space-y-4">
                @foreach($ladders as $ladder)
                    @php
                        $pendingStep = collect($ladder->steps)->where('status', 'pending')->first();
                        $stepCount = collect($ladder->steps)->count();
                    @endphp
                    <div class="border p-4 rounded bg-white shadow-sm flex flex-col gap-2">
                        <div class="flex justify-between">
                            <span class="font-bold">Ladder #{{ $ladder->id }}</span>
                            <span class="text-gray-500 text-sm">Status: {{ $ladder->status }}</span>
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
