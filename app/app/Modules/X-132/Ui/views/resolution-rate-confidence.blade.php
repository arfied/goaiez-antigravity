<div>
    <h2>Resolution rate & confidence</h2>

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

    <form wire:submit.prevent="linkPeople" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
        <div class="flex gap-4">
            <select wire:model="canonicalId" class="border rounded p-2 text-ink flex-1 bg-surface">
                <option value="0">Select canonical person...</option>
                @foreach($people as $p)
                    <option value="{{ $p['id'] }}">{{ $p['first_name'] }} ({{ $p['email'] }})</option>
                @endforeach
            </select>

            <select wire:model="duplicateId" class="border rounded p-2 text-ink flex-1 bg-surface">
                <option value="0">Select duplicate person...</option>
                @foreach($people as $p)
                    <option value="{{ $p['id'] }}">{{ $p['first_name'] }} ({{ $p['email'] }})</option>
                @endforeach
            </select>

            <button type="submit" class="bg-surface text-ink border rounded p-2">Link People</button>
        </div>
    </form>

    @if($links->isEmpty())
        <p>No links found.</p>
    @else
        <ul>
            @foreach($links as $link)
                <li>{{ $link->id }} - {{ $link->confidence_rate }}</li>
            @endforeach
        </ul>
    @endif
</div>
