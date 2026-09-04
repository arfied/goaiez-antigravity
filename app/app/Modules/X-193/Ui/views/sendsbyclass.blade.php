<div>
    <x-surface.sample-state module="classifies every `send.requested` as **`marketing`" screen="sendsbyclass" />
    <div class="sends-by-class-view p-4">
        <h3 class="text-lg font-bold">Notification Sends by Class</h3>
        @if($classes->isEmpty())
            <p class="text-gray-500">No caller classifications configured.</p>
        @else
            <ul>
                @foreach($classes as $c)
                    <li>{{ $c->caller_type }}: [{{ $c->classification }}] (Quiet hours: {{ $c->respects_quiet_hours ? 'Yes' : 'No' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
