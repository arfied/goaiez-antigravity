<div>
    <div class="eval-report-container p-4">
        <h3 class="text-lg font-bold">Golden Set Eval Report</h3>
        @if($sets->isEmpty())
            <p class="text-gray-500">No golden set evaluations available.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($sets as $set)
                    <li class="py-2">
                        <span class="font-mono text-sm">Golden Set #{{ $set->id }} (v{{ $set->prompt_version }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
