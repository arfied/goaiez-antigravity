<div>
    <div class="whatsapp-queue-view p-4">
        <h2 class="text-lg font-bold text-ink">Template approval queue</h2>
        @if($pending->isEmpty())
            <x-ui.empty-state heading="Nothing waiting for approval.">Templates you have submitted but that have not come back yet wait here.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($pending as $p)
                    <li class="py-2" wire:key="pending-{{ $p->id }}">
                        <span class="font-semibold">{{ $p->name }}</span>
                        <span class="text-sm text-ink-2">{{ $p->body_text }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-4 border-t pt-4">
            <h3 class="font-bold">Submit Template</h3>
            
            <x-ui.toast kind="error" :message="$error" />
            
            <x-ui.toast kind="success" :message="$success" />

            <form wire:submit="submit" class="space-y-4 max-w-sm mt-2">
                <div>
                    <label class="block text-sm">Name</label>
                    <input type="text" wire:model="name" class="border p-2 w-full">
                </div>
                <div>
                    <label class="block text-sm">Category</label>
                    <input type="text" wire:model="category" class="border p-2 w-full">
                </div>
                <div>
                    <label class="block text-sm">Body Text</label>
                    <input type="text" wire:model="bodyText" class="border p-2 w-full">
                </div>
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>
    </div>
</div>
