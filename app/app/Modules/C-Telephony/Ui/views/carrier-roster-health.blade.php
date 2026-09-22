<div>
    <div class="carrier-health-container p-4">
        <h3 class="text-lg font-bold">Carrier Roster & Network Health</h3>

        @if($error)
            <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $error }}</div>
        @endif
        @if($success)
            <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $success }}</div>
        @endif

        <form wire:submit="recordHealth" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <input type="text" wire:model="carrierName" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Carrier Name">
            <select wire:model="status" class="border rounded p-2 text-ink flex-1 bg-surface">
                <option value="healthy">healthy</option>
                <option value="unhealthy">unhealthy</option>
            </select>
            <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
        </form>

        @if($health->isEmpty())
            <p class="text-gray-500">No carrier health metrics recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($health as $h)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $h->carrier_name }}</span>
                        <span class="text-xs {{ $h->status === 'healthy' ? 'text-green-600' : 'text-red-600' }}">[{{ $h->status }}]</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
