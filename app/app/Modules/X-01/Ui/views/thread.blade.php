<div>
    <div class="inbox-thread p-4">
        <h3 class="text-lg font-bold">Omnichannel Conversation Thread</h3>
        @if($conversations->isEmpty())
            <p class="text-gray-500">No conversations recorded.</p>
        @else
            <ul>
                @foreach($conversations as $c)
                    <li>Conversation #{{ $c->id }} [{{ $c->channel }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
