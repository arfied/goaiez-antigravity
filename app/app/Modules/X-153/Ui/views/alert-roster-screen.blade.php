<x-surface.sample-state module="five-character alphanumeric reply codes *(R75/R125 — 28.6M combinations; a code is never reused into another visitor's thread)*" screen="alert_roster_screen" />
<div>
    <div class="alert-roster-container p-4">
        <h3 class="text-lg font-bold">Staff Alert Roster</h3>
        @if($alerts->isEmpty())
            <p class="text-gray-500">No alerts broadcasted.</p>
        @else
            <ul>
                @foreach($alerts as $a)
                    <li>{{ $a->title }} [{{ $a->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
