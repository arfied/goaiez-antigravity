<div>
    <div class="commit-view p-4">
        <h2 class="text-lg font-bold">Migration Commit</h2>
        @if($error) <p class="mb-4 text-red-600">{{ $error }}</p> @endif
        @if($success) <p class="mb-4 text-green-600">{{ $success }}</p> @endif
        <ul>
            @foreach($runs as $run)
                <li>
                    {{ $run->source_system }}
                    <span>[{{ $run->status }}]</span>
                    <span>{{ $run->imported_records }} in · {{ $run->rejected_records }} rejected</span>
                    @if($run->status === 'dry_run_ready')
                        <button wire:click="commitRun({{ $run->id }})" class="rounded bg-brand text-white px-3 py-1 text-sm">Import these</button>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
