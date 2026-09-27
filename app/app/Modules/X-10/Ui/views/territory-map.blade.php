<div>
    <div class="territory-map-view p-4">
        <h2 class="text-xl font-bold text-ink mb-4">Every area you have mapped</h2>

        <div class="mb-6 flex gap-2">
            <input type="text" wire:model="name" placeholder="Service area name" class="border rounded p-2 text-ink flex-1 bg-surface" />
            <x-ui.button size="default" wire:click="defineArea" wire:loading.attr="disabled" wire:target="defineArea">Define service area</x-ui.button>
        </div>

        @if($error)
            <x-ui.error-panel heading="That didn't go through">{{ $error }}</x-ui.error-panel>
        @endif

        <x-ui.toast kind="success" :message="$success" />

        @if($territories->isEmpty())
            <x-ui.empty-state icon="○" heading="No service areas yet">
                When you map an area you cover, it appears here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($territories as $t)
                    <li class="text-ink">{{ $t->name }} · {{ $t->assigned_staff_id !== null ? 'Assigned' : 'Not assigned yet' }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
