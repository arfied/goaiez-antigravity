<div>
    <div class="register-slot-states-container p-4">
        <h3 class="text-lg font-bold">Compliance Register Slot States</h3>
        @if($registers->isEmpty())
            <p class="text-gray-500">No compliance registers active.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($registers as $reg)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $reg->register_name }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
