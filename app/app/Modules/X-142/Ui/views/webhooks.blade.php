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

    <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
        <h3 class="text-ink font-bold">Add Webhook</h3>
        <form wire:submit="submit" class="flex flex-col gap-2">
            <input type="text" wire:model="url" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="URL">
            <input type="text" wire:model="events" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Events (comma-separated)">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
        </form>
        <x-ui.toast kind="success" :message="$success" />
        <x-ui.toast kind="error" :message="$error" />
    </div>
</div>
