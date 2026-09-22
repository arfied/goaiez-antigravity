<div>
    <div class="chat-dock-view p-4">
        <h3 class="text-lg font-bold mb-4">Copilot Assistant Chat Dock</h3>

        @if(empty($turns))
            <x-ui.empty-state icon="💬" heading="How can I help?">Ask me anything about your business.</x-ui.empty-state>
        @else
            <div class="turns space-y-4 mb-4">
                @foreach($turns as $turn)
                    <div class="turn">
                        <div class="utterance font-bold text-ink">{{ $turn['utterance'] }}</div>
                        <div class="response text-ink-2" data-status="{{ $turn['status'] }}">{{ $turn['response'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <form wire:submit="ask" class="flex gap-2 mt-4">
            <input type="text" wire:model="utterance" class="border rounded p-2 flex-grow text-ink" placeholder="Ask something...">
            <x-ui.submit target="ask" busy="Sending...">Send</x-ui.submit>
        </form>
    </div>
</div>
