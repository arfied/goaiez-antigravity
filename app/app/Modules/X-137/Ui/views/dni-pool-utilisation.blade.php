<div>
    <div class="dni-pool-view p-4">
        <h2 class="text-lg font-bold text-ink">DNI pool usage</h2>
        <p class="text-ink-2">{{ $poolSize }} numbers in the pool · {{ $active }} allocated · {{ $rate }}% in use</p>
        <p class="text-ink-2">Fallback: {{ $fallback ?? 'none set' }}</p>
        @if($numbers->isEmpty())
            <p class="text-ink-2">No pool numbers yet.</p>
        @else
            <ul>
                @foreach($numbers as $number)
                    <li class="font-mono text-sm">{{ $number }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
