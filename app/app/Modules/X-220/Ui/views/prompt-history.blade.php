<div>
    <div class="prompt-history-container p-4">
        <h3 class="text-lg font-bold">Prompt History & Versioning</h3>
        @if($prompts->isEmpty())
            <p class="text-gray-500">No prompt versions recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($prompts as $p)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $p->prompt_key }} v{{ $p->version }}</span>
                        @if($p->frozen_at)
                            <span class="text-xs text-green-600">(Frozen {{ $p->frozen_at }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
