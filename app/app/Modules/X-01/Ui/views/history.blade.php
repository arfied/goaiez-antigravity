<div>
    <div class="history-card p-4">
        <h2 class="text-lg font-bold text-ink">Activity</h2>
        @if($messages->isEmpty())
            <x-ui.empty-state heading="Nothing has happened yet.">Every text, call and chat with a customer lands here in one timeline, newest first.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($messages as $m)
                    <li class="py-2" wire:key="msg-{{ $m->id }}">
                        <span class="text-sm text-ink-2">{{ $m->direction }}</span>
                        <span class="font-semibold">{{ $m->body }}</span>
                        <span class="text-sm text-ink-2">{{ $m->sender_type }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
