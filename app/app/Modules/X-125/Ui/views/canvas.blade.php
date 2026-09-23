<div>
    <div class="flow-canvas-view p-4">
        <h2 class="text-xl font-bold text-ink">Every automation you have set up</h2>
        @if($flows->isEmpty())
            <x-ui.empty-state icon="○" heading="No automations yet">
                When you set up an automation, it appears here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($flows as $f)
                    <li class="text-ink">{{ $f->name }} · {{ ucfirst(str_replace('_', ' ', $f->status)) }}</li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8 p-4 bg-surface border rounded">
            <h3 class="text-lg font-bold text-ink mb-4">Create Automation</h3>
            
            @if($success)
                <div class="mb-4 text-ink-2">{{ $success }}</div>
            @endif
            @if($error)
                <div class="mb-4 text-ink-3">{{ $error }}</div>
            @endif
            
            <form wire:submit="createFlow" class="flex flex-col gap-4">
                <input type="text" wire:model="flowName" placeholder="Flow Name" class="border rounded p-2 text-ink bg-surface">
                <input type="text" wire:model="triggerEvent" placeholder="Trigger Event" class="border rounded p-2 text-ink bg-surface">
                <input type="text" wire:model="stepLabel" placeholder="Step Label" class="border rounded p-2 text-ink bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Create Flow</button>
            </form>
        </div>
    </div>
</div>
