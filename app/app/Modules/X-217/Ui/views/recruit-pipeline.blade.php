<div>
    <div class="recruit-pipeline-view p-4 bg-surface">

        @if($success)
            <div class="mb-4 p-2 bg-surface border rounded text-ink">{{ $success }}</div>
        @endif
        @if($error)
            <div class="mb-4 p-2 bg-surface border rounded text-ink">{{ $error }}</div>
        @endif

        <form wire:submit="recruitProspect" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <input type="text" wire:model="partnerName" placeholder="Partner Name" class="border rounded p-2 text-ink flex-1 bg-surface">
            <input type="text" wire:model="email" placeholder="Email" class="border rounded p-2 text-ink flex-1 bg-surface">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
        </form>

        @if($prospects->isEmpty())
            <p class="text-ink">No prospects found in the recruitment pipeline.</p>
        @else
            <ul class="text-ink">
                @foreach($prospects as $prospect)
                    <li class="border rounded p-2 mb-2 bg-surface">
                        {{ $prospect->partner_name }} - {{ $prospect->email }} - {{ $prospect->stage }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
