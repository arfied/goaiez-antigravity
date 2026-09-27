<div>
    <h2 class="text-lg font-bold text-ink">Inbox</h2>
    <div wire:poll.10s class="flex flex-col h-full bg-paper rounded-[--radius-card] border border-rule shadow-[--shadow-card] mt-4">
        <div class="px-4 py-4 border-b border-rule flex justify-between items-center bg-card rounded-t-[--radius-card]">
                                                <h3 class="font-display font-semibold text-ink text-lg flex items-center gap-2">
                Conversation
                @if($customer)
                    <span class="text-sm font-normal text-ink-2 ml-4 bullet-head">&bull; {{ $customer->name }} &bull; {{ $customer->phone }} &bull; {{ $customer->email }}</span>
                    @if($isGhostRisk)
                        <span class="text-xs font-bold text-red-600 bg-red-100 px-2 py-0.5 rounded ghost-risk-flag">Ghost Risk</span>
                    @endif
                @endif
            </h3>
            <div class="flex items-center gap-4">
                @if($hasActiveTakeover)
                    <button type="button" wire:click="releaseTakeover" class="text-sm font-medium text-blue-600 hover:text-blue-700 bg-blue-50 px-3 py-1.5 rounded-md border border-blue-200 shadow-sm transition-colors">
                        Release Takeover
                    </button>
                @endif
                <div wire:loading class="text-sm text-ink-3">Loading...</div>
            </div>
        </div>

        @if($notice)
            <div class="p-4">
                <div class="flex flex-col gap-3 rounded-[--radius-card] border border-rule bg-card p-4 sm:p-5">
                    <p class="text-base leading-relaxed text-ink-2">
                        {{ str_replace('Inbox.', '', $notice) }}<a href="{{ route('account.inbox') }}" class="underline text-ink">Inbox</a>.
                    </p>
                </div>
            </div>
        @elseif($errorMessage)
            <div class="p-4">
                <x-ui.error-panel heading="Could not load or reply">
                    {{ $errorMessage }}
                </x-ui.error-panel>
            </div>
        @elseif($customer && $messages->isEmpty())
            <div class="p-4">
                <x-ui.empty-state heading="No messages yet" icon="○">
                    This person hasn't sent any messages.
                </x-ui.empty-state>
            </div>
        @elseif(!$customer)
            <div class="inbox-thread p-4">
                <h3 class="text-lg font-bold">Omnichannel Conversation Thread</h3>
                @if($conversations->isEmpty())
                    <p class="text-ink-2">No conversations recorded.</p>
                @else
                    <p class="text-ink-2">Select a conversation. {{ $conversations->count() }} found.</p>
                    <ul class="space-y-2 mt-2">
                        @foreach($conversations as $conversation)
                            <li class="p-3 border border-rule rounded">
                                {{ $conversation->subject ?? 'No subject' }}
                            </li>
                        @endforeach
                    </ul>
                @endif
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
                        <x-ui.button type="submit">Add to the conversation</x-ui.button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
