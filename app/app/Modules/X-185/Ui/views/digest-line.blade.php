<div>
    <div class="digest-line-view p-4">
        <h2 class="text-xl font-bold text-ink">Sequences of messages you have set up</h2>
        @if($sequences->isEmpty())
            <x-ui.empty-state icon="○" heading="No sequences yet">
                When you set up a sequence of messages, it appears here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($sequences as $s)
                    <li class="text-ink">{{ $s->name }} · {{ $s->is_active ? 'running' : 'stopped' }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
