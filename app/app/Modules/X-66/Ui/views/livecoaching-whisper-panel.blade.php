<div>
    <div class="whisper-panel p-4">
        <h2 class="text-lg font-bold text-ink">Call coaching</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            @if($success)
                <div class="text-green-600 mb-2">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-red-600 mb-2">{{ $error }}</div>
            @endif
            <form wire:submit.prevent="coach" class="flex flex-col gap-2">
                <input type="number" wire:model="sessionId" placeholder="Session ID" class="border rounded p-2 text-ink flex-1 bg-surface">
                <textarea wire:model="transcript" placeholder="Transcript" class="border rounded p-2 text-ink flex-1 bg-surface"></textarea>
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        @if($autopsies->isEmpty())
            <x-ui.empty-state heading="No coaching notes yet.">After a call, what went well and what to try next time is written down here.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($autopsies as $a)
                    <li class="py-2" wire:key="autopsy-{{ $a->id }}">
                        <span class="font-semibold">{{ $a->coaching_notes }}</span>
                        <span class="text-sm text-ink-2">{{ $a->sentiment }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
