<div>
    <x-surface.sample-state module="⭐⭐ **The single decider of whether a message may be sent to a person on a channel. Every sender asks; no sender decides.** Returns GRANT or **exactly one of P-060's twenty refusal reasons — a refusal without a code fails the build**, and `platformFloorRefusal()` fails closed, so an unknown state refuses. Owns the **permit** *(immutable, provenance-carrying — lifts are recorded, never deleted)*, the **suppression list**, the **`ImportAttestation`**, and the **compliance-register slots**. **The three lanes:** a campaign declares its lane and this module reads it — Lane 1 transactional *(nothing blocks but STOP)*" screen="register_slot_states" />
    <div class="register-slot-states-container p-4">
        <h2 class="text-lg font-bold">Compliance Register Slot States</h2>
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
