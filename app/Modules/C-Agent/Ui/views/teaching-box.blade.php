<div>
    <div class="teaching-box-container p-4">
        <h2 class="text-lg font-bold text-ink">Agent teaching</h2>
        @if($instructions->isEmpty())
            <p class="text-ink-2">No custom instructions defined.</p>
        @else
            <ul>
                @foreach($instructions as $inst)
                    <li>{{ $inst->instruction_key }}: {{ $inst->instruction_text }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
