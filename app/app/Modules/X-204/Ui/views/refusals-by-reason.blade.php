<div>
    <div class="refusals-by-reason-container p-4">
        <h3 class="text-lg font-bold">Consent Refusals by Reason</h3>
        @if($refusals->isEmpty())
            <p class="text-gray-500">No consent refusals recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($refusals as $ref)
                    <li class="py-2">
                        <span class="font-mono text-sm text-red-600">{{ $ref->refusal_reason }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
