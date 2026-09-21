<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Interests</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <form wire:submit="submit" class="flex flex-col gap-2">
                @if($success) <div class="text-ink font-bold">{{ $success }}</div> @endif
                @if($error) <div class="text-ink font-bold">{{ $error }}</div> @endif
                <input type="text" wire:model="personId" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Customer ID (Person ID)">
                <input type="text" wire:model="topic" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Topic">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h3 class="font-bold text-ink">Infer Interest</h3>
            <form wire:submit="inferInterest" class="flex flex-col gap-2">
                <input type="text" wire:model="inferPersonId" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Customer ID (Person ID)">
                <input type="text" wire:model="inferTopic" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Inferred Topic">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Infer</button>
            </form>
        </div>

        @if($interests->isEmpty())
            <x-ui.empty-state icon="○" heading="No interests recorded yet">
                Inferences and tags will show up here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($interests as $i)
                    <li class="text-ink">
                        {{ $people[$i->person_id] ?? 'Customer #' . $i->person_id }} - {{ $i->topic }} - 
                        @if($i->is_tenant_set)
                            set by you
                        @else
                            inferred, {{ (int) round($i->confidence_rate * 100) }}% sure
                        @endif
                        ({{ $i->source }})
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
