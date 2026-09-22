<div>
    <div class="private-inbox-view p-4">
        <h2 class="text-lg font-bold text-ink">Fixer inbox</h2>
        
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <h3 class="font-bold text-ink">Simulate Staff SMS</h3>
            @if($success)
                <div class="text-green-600 mb-2">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-red-600 mb-2">{{ $error }}</div>
            @endif
            <form wire:submit.prevent="process" class="flex flex-col gap-2">
                <input type="number" wire:model="staffPersonId" placeholder="Staff Person ID" class="border rounded p-2 text-ink flex-1 bg-surface">
                <input type="text" wire:model="smsBody" placeholder="SMS Body" class="border rounded p-2 text-ink flex-1 bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        @if($delegateError) <div class="text-ink-2 bg-surface border p-2 mb-4 rounded">{{ $delegateError }}</div> @endif
        @if($delegateSuccess) <div class="text-ink-2 bg-surface border p-2 mb-4 rounded">{{ $delegateSuccess }}</div> @endif

        <form wire:submit.prevent="delegateCommand" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded border">
            <select wire:model="delegateCommandId" class="border rounded p-2 text-ink bg-surface">
                <option value="">Choose a command</option>
                @foreach($commands as $c)<option value="{{ $c->id }}">Command #{{ $c->id }}</option>@endforeach
            </select>
            <input type="text" wire:model="delegateReason" class="border rounded p-2 text-ink bg-surface" placeholder="Why you are handing it on">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Hand on</button>
        </form>

        @if($commands->isEmpty())
            <x-ui.empty-state heading="No commands yet.">When someone on the crew texts the assistant, what they asked and what it did appears here.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($commands as $c)
                    <li class="py-2" wire:key="cmd-{{ $c->id }}">
                        <span class="font-semibold">{{ $c->raw_command }}</span>
                        <span class="text-sm text-ink-2">{{ $c->parsed_intent }}</span>
                        <span class="text-sm text-ink-2">{{ $c->status }}</span>
                        <span class="text-sm text-ink-2 tabular-nums">{{ $c->eta_minutes_delayed }} min</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
