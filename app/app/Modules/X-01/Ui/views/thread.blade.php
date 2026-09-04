<div>
    <div class="flex flex-col h-full bg-paper rounded-[--radius-card] border border-rule shadow-[--shadow-card]">
        <div class="px-4 py-4 border-b border-rule flex justify-between items-center bg-card rounded-t-[--radius-card]">
            <h3 class="font-display font-semibold text-ink text-lg flex items-center gap-2">
                Conversation
            </h3>
            
            <div wire:loading class="text-sm text-ink-3">Loading...</div>
        </div>

        @if($errorMessage)
            <div class="p-4">
                <x-ui.error-panel heading="Could not load or reply">
                    {{ $errorMessage }}
                </x-ui.error-panel>
            </div>
        @elseif($messages->isEmpty())
            <div class="p-4">
                <x-ui.empty-state heading="No messages yet" icon="○">
                    This person hasn't sent any messages.
                </x-ui.empty-state>
            </div>
        @else
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                @foreach($messages as $msg)
                    <div class="flex flex-col {{ $msg->direction === 'inbound' ? 'items-start' : 'items-end' }}">
                        <div class="px-4 py-2 rounded-[--radius-card] max-w-[80%] text-sm {{ $msg->direction === 'inbound' ? 'bg-card border border-rule text-ink' : 'bg-ink text-paper' }}">
                            {{ $msg->body }}
                        </div>
                        
                        <div class="text-[10px] text-ink-3 mt-1 flex items-center gap-2">
                            <span>{{ $msg->channel }} • {{ \Carbon\Carbon::parse($msg->created_at)->diffForHumans() }}</span>
                            @if($msg->direction === 'outbound' && str_contains((string)$msg->body, '[Human takeover'))
                                <x-ui.status-pill state="ok" label="Human takeover" class="!text-[10px] !px-1.5 !py-0.5" />
                            @endif
                            @if($msg->direction === 'inbound')
                                <button type="button" wire:click="draftAiReply({{ $msg->id }})" class="text-ink-2 hover:text-ink underline">
                                    Draft AI Reply
                                </button>
                            @else
                                <span class="text-ink-3">Delivered</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="p-4 border-t border-rule bg-card rounded-b-[--radius-card]">
                <form wire:submit="sendReply" class="flex flex-col gap-2">
                    <textarea wire:model="replyText" class="w-full rounded-[--radius-control] border border-rule bg-paper p-3 text-sm focus:outline-none focus:border-ink" rows="2" placeholder="Write a reply..."></textarea>
                    <div class="flex justify-end">
                        <x-ui.button type="submit">Send Reply</x-ui.button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
