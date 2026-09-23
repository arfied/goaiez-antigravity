<div>
    <div class="p-4">
        <h2 class="text-lg font-bold text-ink">Golden Set Eval Report</h2>
        @if($sets->isEmpty())
            <p class="text-ink-2">No golden set evaluations available.</p>
        @else
            <ul class="divide-y border-t border-ink-3">
                @foreach($sets as $set)
                    <li class="py-4 flex justify-between items-center">
                        <div>
                            @php
                                $prompt = \App\Modules\X220\Models\AiPrompt::find($set->prompt_id);
                                $caseCount = count($set->test_cases ?? []);
                            @endphp
                            <h3 class="font-bold text-ink mb-1">{{ $prompt ? $prompt->prompt_key : 'Unknown' }} v{{ $set->prompt_version }}</h3>
                            <div class="text-sm text-ink-2">
                                <span>Cases: {{ $caseCount }}</span> |
                                <span>Threshold: {{ $set->score_threshold }}%</span>
                            </div>
                            <div class="text-sm text-ink-3 mt-1">
                                @if(isset($lastResults[$set->id]))
                                    @if($lastResults[$set->id]['status'] === 'not_run')
                                        No evaluation run yet
                                    @else
                                        Result: {{ $lastResults[$set->id]['score'] ?? 0 }}% ({{ ($lastResults[$set->id]['passed'] ?? false) ? 'Passed' : 'Failed' }})
                                    @endif
                                @else
                                    No evaluation run yet
                                @endif
                            </div>
                        </div>
                        <div>
                            <button wire:click="run({{ $set->id }})" class="text-sm border rounded px-2 py-1 bg-surface text-ink hover:bg-paper">
                                Run
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>