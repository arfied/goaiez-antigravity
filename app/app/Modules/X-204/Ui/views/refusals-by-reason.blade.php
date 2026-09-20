<div>

    <div class="refusals-by-reason-container p-4">
        <h2 class="text-lg font-bold">Consent Refusals by Reason</h2>
        @if($refusals->isEmpty())
            <p class="text-ink-2">No consent refusals recorded.</p>
        @else
            <ul class="divide-y divide-rule">
                @foreach($refusals as $ref)
                    <li class="py-2">
                        <span class="font-mono text-sm text-ink mr-2">{{ $ref->recipient_phone }}</span>
                        <span class="font-mono text-sm text-red-600">{{ $ref->refusal_reason }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
