<div>
    <div class="groundcheck p-4">
        <h2 class="text-lg font-bold text-ink">Onboarding checks</h2>
        @if($steps->isEmpty())
            <x-ui.empty-state heading="No checks recorded yet.">Each thing we worked out about your business while setting you up is listed here, and anything that stopped us is marked.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($steps as $s)
                    <li class="py-2" wire:key="step-{{ $s->id }}">
                        <span class="font-semibold">{{ $s->step_name }}</span>
                        <span class="text-sm text-ink-2">{{ $s->status }}</span>
                        <span class="text-sm text-ink-2">{{ $s->is_hard_stop ? 'stopped us' : 'cleared' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
