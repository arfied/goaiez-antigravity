<div>
    <h2 class="text-lg font-bold text-ink mb-4">Webhooks</h2>
    @if($subscriptions->isEmpty())
        <x-ui.empty-state heading="No webhooks yet." icon="○">
            Webhooks push events to your system.
        </x-ui.empty-state>
    @else
        <div class="mb-4">
            @foreach($subscriptions as $sub)
                <div class="p-4 border border-rule rounded-[--radius-card] mb-2" data-active="{{ $sub->is_active ? 'yes' : 'no' }}">
                    <p class="font-bold text-ink">{{ $sub->target_url }}</p>
                    <p class="text-sm">{{ $sub->event_filter }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
