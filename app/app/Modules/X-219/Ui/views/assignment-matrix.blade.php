<div>
    <div class="assignment-matrix-container p-4">
        <h3 class="text-lg font-bold">Model Assignment Matrix</h3>
        @if($assignments->isEmpty())
            <p class="text-gray-500">No module assignments configured.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($assignments as $a)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $a->target_module }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
