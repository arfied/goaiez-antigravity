<div>
    <div class="conflicts-list-view p-4">
        <h3 class="text-lg font-bold">Uncategorised / Review Queue Conflicts</h3>
        
        @foreach($conflicts as $conflict)
            <div class="mt-4 border p-4">
                <div>Ref: {{ $conflict->transaction_ref }}</div>
                <div>Status: {{ $conflict->status }}</div>
                @if(isset($messages[$conflict->id]))
                    <div class="text-sm text-red-500">{{ $messages[$conflict->id] }}</div>
                @endif
                
                <div class="flex gap-2 mt-2">
                    <input type="text" wire:model="resolutions.{{ $conflict->id }}" placeholder="Category" class="border rounded px-2">
                    <button wire:click="resolve({{ $conflict->id }})" class="bg-blue-500 text-white px-4 py-1 rounded">Resolve</button>
                </div>
            </div>
        @endforeach
    </div>
</div>
