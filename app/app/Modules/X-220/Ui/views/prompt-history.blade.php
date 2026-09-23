<div>
    <div class="p-4">
        <h2 class="text-lg font-bold text-ink">Prompt History & Versioning</h2>
        @if($prompts->isEmpty())
            <p class="text-ink-2">No prompt versions recorded.</p>
        @else
            <ul class="divide-y border-t border-ink-3">
                @foreach($prompts->groupBy('prompt_key') as $key => $keyPrompts)
                    <li class="py-4">
                        <h3 class="font-bold text-ink mb-2">{{ $key }}</h3>
                        <ul class="space-y-2">
                            @foreach($keyPrompts as $p)
                                <li class="flex justify-between items-center p-2 bg-surface border rounded">
                                    <div>
                                        <span class="font-mono text-sm font-semibold text-ink">v{{ $p->version }}</span>
                                        <span class="text-xs text-ink-2 ml-2">Created: {{ $p->created_at->format('Y-m-d H:i') }}</span>
                                        <span class="text-xs text-ink-2 ml-2">Calls: {{ $p->calls_count ?? 0 }}</span>
                                        @if($p->frozen_at)
                                            <span class="text-xs text-ink-3 ml-2">(Frozen {{ $p->frozen_at->format('Y-m-d H:i') }})</span>
                                        @endif
                                    </div>
                                    @if(!$p->frozen_at)
                                        <button wire:click="freeze({{ $p->id }})" class="text-sm border rounded px-2 py-1 bg-surface text-ink hover:bg-paper">Freeze</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
