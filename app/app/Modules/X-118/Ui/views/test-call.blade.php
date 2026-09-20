<div>
    <div class="test-call p-4">
        <h2 class="text-lg font-bold">Direct Test Call</h2>
        <p class="text-ink-2">Test call placed directly to your new live line.</p>
        <ul>
            @foreach($runs as $run)
                <li wire:key="run-{{ $run->id }}">{{ $run->business_name }}</li>
            @endforeach
        </ul>
    </div>
</div>
