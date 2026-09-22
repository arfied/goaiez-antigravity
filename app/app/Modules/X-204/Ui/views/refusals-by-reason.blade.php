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

        <div class="mt-8 bg-surface p-4 border rounded">
            @if($success)
                <div class="text-ink bg-surface border rounded p-2 mb-4">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-ink bg-surface border rounded p-2 mb-4">{{ $error }}</div>
            @endif

            <h3 class="text-lg font-bold text-ink">Check whether a number may be messaged</h3>
            <form wire:submit="decide" class="flex flex-col gap-4 mt-4 mb-8">
                <input type="text" wire:model="decidePhone" placeholder="Phone Number" class="border rounded p-2 text-ink bg-surface">
                <input type="text" wire:model="decideChannel" placeholder="Channel (e.g. sms)" class="border rounded p-2 text-ink bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Check Number</button>
            </form>

            <h3 class="text-lg font-bold text-ink">Stop messages to a number</h3>
            <form wire:submit="suppress" class="flex flex-col gap-4 mt-4">
                <input type="text" wire:model="suppressPhone" placeholder="Phone Number" class="border rounded p-2 text-ink bg-surface">
                <input type="text" wire:model="suppressChannel" placeholder="Channel (e.g. sms)" class="border rounded p-2 text-ink bg-surface">
                <input type="text" wire:model="suppressReason" placeholder="Reason (e.g. opt_out)" class="border rounded p-2 text-ink bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Suppress Number</button>
            </form>
        </div>
    </div>
</div>
