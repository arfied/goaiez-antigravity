<div wire:init="load">
    @if($errorMessage)
        <x-ui.error-panel heading="We could not load recommendations." retry="load">{{ $errorMessage }}</x-ui.error-panel>
    @else
        @if($recs->isNotEmpty())
            <div class="mb-4">
                <h3 class="font-display text-lg font-bold mb-4">Today's Recommendations</h3>
                @foreach($recs as $r)
                    <div class="mb-4">
                        <x-ui.attention-card
                            state="attention"
                            heading="{{ $r->title }}"
                        >
                            Action required: {{ $r->action_key }}
                        </x-ui.attention-card>
                        <div class="mt-2 flex gap-2">
                            <x-ui.button wire:click="accept({{ $r->id }})" size="default" variant="primary">Accept</x-ui.button>
                            <x-ui.button wire:click="dismiss({{ $r->id }})" size="default" variant="secondary">Dismiss</x-ui.button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-8 border-t pt-4 bg-surface text-ink">
            <h3 class="font-display text-lg font-bold mb-4">Add Recommendation</h3>
            @if($success)
                <div class="mb-4 p-2 bg-surface border rounded text-ink">{{ $success }}</div>
            @endif
            @if($error)
                <div class="mb-4 p-2 bg-surface border rounded text-ink">{{ $error }}</div>
            @endif

            @if($sessions->isEmpty())
                <p class="text-ink">No assistant sessions found. A session must be created via the chat dock first.</p>
            @else
                <form wire:submit="recommend" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border text-ink">
                    <select wire:model="selectedSessionId" class="border rounded p-2 bg-surface text-ink">
                        <option value="0">Select Session</option>
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}">Session #{{ $session->id }}</option>
                        @endforeach
                    </select>
                    <input type="text" wire:model="recommendTitle" placeholder="Title" class="border rounded p-2 bg-surface text-ink">
                    <input type="text" wire:model="actionKey" placeholder="Action Key" class="border rounded p-2 bg-surface text-ink">
                    <button type="submit" class="bg-surface border text-ink rounded p-2">Submit</button>
                </form>
            @endif
        </div>
    @endif
</div>
