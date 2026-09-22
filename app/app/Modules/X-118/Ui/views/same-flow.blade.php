<div>
    <div class="same-flow p-4">
        <h2 class="text-lg font-bold">Standard Flow</h2>
        <ul>
            @foreach($runs as $run)
                <li wire:key="run-{{ $run->id }}">{{ $run->business_name }}</li>
            @endforeach
        </ul>
    </div>
</div>
