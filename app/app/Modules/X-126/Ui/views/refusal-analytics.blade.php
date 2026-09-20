<div>

    <div class="refusal-analytics-container p-4">
        <h3 class="text-lg font-bold">Capability Refusal & Autonomy Analytics</h3>
        @if($refusals->isEmpty())
            <p class="text-gray-500">No capability refusals recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($refusals as $item)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $item->refusal_code }}</span>
                        <span class="text-xs text-gray-500">{{ $item->capability_name }} — {{ $item->decision }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
