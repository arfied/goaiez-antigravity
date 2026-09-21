<div>
    <div class="cutover-queue-view p-4">
        <h2 class="text-lg font-bold text-ink">Redirect queue</h2>
        @if($error)
            <div class="text-ink-2 bg-surface border p-2 mb-4 rounded">{{ $error }}</div>
        @endif
        @if($success)
            <div class="text-ink-2 bg-surface border p-2 mb-4 rounded">{{ $success }}</div>
        @endif

        <form wire:submit="buildRedirect" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <input type="text" wire:model="sourceUrl" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Old URL">
            <input type="text" wire:model="newDomainHost" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="New domain host">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
        </form>
        @if($redirects->isEmpty())
            <p class="text-ink-2">No redirects mapped.</p>
        @else
            <ul>
                @foreach($redirects as $r)
                    <li>{{ $r->source_url }} &rarr; {{ $r->destination_url }} [{{ $r->status_code }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
