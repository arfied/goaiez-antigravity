<div>
    <x-surface.sample-state module="C-Agent" screen="teaching_box" />
    <div class="teaching-box-container p-4">
        <h3 class="text-lg font-bold">Agent Teaching Box</h3>
        @if($instructions->isEmpty())
            <p class="text-gray-500">No custom instructions defined.</p>
        @else
            <ul>
                @foreach($instructions as $inst)
                    <li>{{ $inst->instruction_key }}: {{ $inst->instruction_text }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
