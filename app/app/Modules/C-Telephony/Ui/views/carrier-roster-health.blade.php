<div>
    <div class="carrier-health-container p-4">
        <h3 class="text-lg font-bold">Carrier Roster & Network Health</h3>
        @if($health->isEmpty())
            <p class="text-gray-500">No carrier health metrics recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($health as $h)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $h->carrier_name }}</span>
                        <span class="text-xs {{ $h->status === 'healthy' ? 'text-green-600' : 'text-red-600' }}">[{{ $h->status }}]</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
