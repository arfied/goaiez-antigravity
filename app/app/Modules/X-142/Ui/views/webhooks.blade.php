<div>
    <x-surface.sample-state module="full read/write across every registered action *(the category leaders ship read-only MCP; ours is complete because the registry was built for it)*" screen="webhooks" />
    @if($subscriptions->isEmpty())
        <x-ui.empty-state heading="No webhooks yet." icon="○">
            Webhooks push events to your system.
        </x-ui.empty-state>
    @else
        <div class="mb-4">
            <h1 class="font-display text-lg font-bold mb-4">Webhooks</h1>
            @foreach($subscriptions as $sub)
                <div class="p-4 border border-rule rounded-[--radius-card] mb-2" data-active="{{ $sub->is_active ? 'yes' : 'no' }}">
                    <h2 class="font-bold">{{ $sub->target_url }}</h2>
                    <p class="text-sm">{{ $sub->event_filter }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
