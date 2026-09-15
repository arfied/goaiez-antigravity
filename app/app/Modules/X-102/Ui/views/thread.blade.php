<div>
    <div class="chat-thread-view p-4">
        <h2 class="text-lg font-bold text-ink">Chat thread</h2>
        @if($turns->isEmpty())
            <p class="text-ink-2">No chat turns yet.</p>
        @else
            <ul>
                @foreach($turns as $turn)
                    <li>{{ $turn->author_type }}: {{ $turn->message }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
