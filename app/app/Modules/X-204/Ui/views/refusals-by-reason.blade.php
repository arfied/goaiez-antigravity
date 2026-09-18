<div>
    <x-surface.sample-state module="⭐⭐ **The single decider of whether a message may be sent to a person on a channel. Every sender asks; no sender decides.** Returns GRANT or **exactly one of P-060's twenty refusal reasons — a refusal without a code fails the build**, and `platformFloorRefusal()` fails closed, so an unknown state refuses. Owns the **permit** *(immutable, provenance-carrying — lifts are recorded, never deleted)*, the **suppression list**, the **`ImportAttestation`**, and the **compliance-register slots**. **The three lanes:** a campaign declares its lane and this module reads it — Lane 1 transactional *(nothing blocks but STOP)*" screen="refusals_by_reason" />
    <div class="refusals-by-reason-container p-4">
        <h2 class="text-lg font-bold">Consent Refusals by Reason</h2>
        @if($refusals->isEmpty())
            <p class="text-gray-500">No consent refusals recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($refusals as $ref)
                    <li class="py-2">
                        <span class="font-mono text-sm text-gray-800 mr-2">{{ $ref->recipient_phone }}</span>
                        <span class="font-mono text-sm text-red-600">{{ $ref->refusal_reason }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
