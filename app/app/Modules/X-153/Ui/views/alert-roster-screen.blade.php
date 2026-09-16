<div>
    <div class="alert-roster-container p-4">
        <h2 class="text-lg font-bold text-ink">Team alerts</h2>
        @if($alerts->isEmpty())
            <p class="text-ink-2">No alerts broadcasted.</p>
        @else
            <ul>
                @foreach($alerts as $a)
                    <li>{{ $a->title }} [{{ $a->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
