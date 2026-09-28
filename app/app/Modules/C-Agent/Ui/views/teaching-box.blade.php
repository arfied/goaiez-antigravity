<div>
    <div class="teaching-box-container p-4">
        <h2 class="text-lg font-bold text-ink">Agent teaching</h2>
        @if($instructions->isEmpty())
            <p class="text-ink-2">No custom instructions defined.</p>
        @else
            <ul>
                @foreach($instructions as $inst)
                    <li>{{ $inst->instruction_key }}: {{ $inst->instruction_text }}</li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8 p-4 bg-surface border rounded">
            <h3 class="text-md font-bold text-ink">Teach Fact</h3>
            <x-ui.toast kind="success" :message="$success" />
            <x-ui.toast kind="error" :message="$error" />
            <form wire:submit="teachAgent" class="flex flex-col gap-2 mt-4">
                <input type="text" wire:model="key" placeholder="Key" class="border rounded p-2 text-ink bg-paper">
                <input type="text" wire:model="value" placeholder="Value" class="border rounded p-2 text-ink bg-paper">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Teach</button>
            </form>
        </div>
    </div>
</div>
