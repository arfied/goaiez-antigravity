<div>
    <div>
        <h2 class="text-lg font-bold text-ink">Text thread</h2>
        @if($messages->isEmpty())
            <x-ui.empty-state heading="No texts yet.">Texts sent from your number are listed here, newest first.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($messages as $msg)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $msg->recipient_phone }}</span>
                        <span class="text-ink-2">{{ $msg->body }}</span>
                        <span class="text-sm text-ink-2">{{ $msg->status }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
