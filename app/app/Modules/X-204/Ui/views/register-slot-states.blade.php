<div>

    <div class="register-slot-states-container p-4">
        <h2 class="text-lg font-bold">Compliance Register Slot States</h2>
        @if($registers->isEmpty())
            <p class="text-ink-2">No compliance registers active.</p>
        @else
            <ul class="divide-y divide-rule">
                @foreach($registers as $reg)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $reg->register_name }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
