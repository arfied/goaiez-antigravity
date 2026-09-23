<div>
    <h2>Person timeline</h2>

    @if($error)
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 text-ink">
            {{ $error }}
        </div>
    @endif

    @if($success)
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 text-ink">
            {{ $success }}
        </div>
    @endif

    <form wire:submit.prevent="recordEvidence" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
        <div class="flex gap-4">
            <select wire:model="personPick" class="border rounded p-2 text-ink flex-1 bg-surface">
                <option value="0">Select person...</option>
                @foreach($people as $p)
                    <option value="{{ $p['id'] }}">{{ $p['first_name'] }} ({{ $p['email'] }})</option>
                @endforeach
            </select>

            <input type="text" wire:model="fieldName" placeholder="Field name (e.g. email)" class="border rounded p-2 text-ink flex-1 bg-surface">
            <input type="text" wire:model="fieldValue" placeholder="Field value" class="border rounded p-2 text-ink flex-1 bg-surface">

            <button type="submit" class="bg-surface text-ink border rounded p-2">Record Evidence</button>
        </div>
    </form>

    @if($evidence->isEmpty())
        <p>No evidence found.</p>
    @else
        <ul>
            @foreach($evidence as $item)
                <li>{{ $item->field_value }}</li>
            @endforeach
        </ul>
    @endif
</div>
