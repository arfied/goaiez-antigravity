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
                    <div class="mt-2 text-sm flex gap-4">
                        <span>{{ $sub->is_active ? 'Active' : 'Paused' }}</span>
                        <button type="button" wire:click="toggleActive({{ $sub->id }})" class="text-blue-600 hover:underline">{{ $sub->is_active ? 'Pause' : 'Resume' }}</button>
                    </div>
                    <div class="mt-2 text-sm">
                        @if(isset($revealed[$sub->id]))
                            <code>{{ $sub->secret }}</code>
                        @else
                            <button type="button" wire:click="revealSecret({{ $sub->id }})" class="text-blue-600 hover:underline">Show signing secret</button>
                        @endif
                    </div>
                    <div class="mt-4">
                        <p class="font-bold text-sm mb-2 text-ink">Recent Deliveries</p>
                        @if($deliveries[$sub->id]->isEmpty())
                            <p class="text-sm">No deliveries yet.</p>
                        @else
                            <ul class="text-sm space-y-1">
                                @foreach($deliveries[$sub->id] as $del)
                                    <li>{{ $del->event }} &middot; {{ $del->status }} &middot; {{ $del->last_status_code ?? '—' }} &middot; {{ $del->created_at->diffForHumans() }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
        <h3 class="text-ink font-bold">Add Webhook</h3>
        <form wire:submit="submit" class="flex flex-col gap-2">
            <input type="text" wire:model="url" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="URL">
            <input type="text" wire:model="events" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Events (comma-separated)">
            <p class="text-sm text-subtle">Events you can choose: contact.created, message.received, lead.assigned, estimate.sent, estimate.accepted</p>
            <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
        </form>
        <x-ui.toast kind="success" :message="$success" />
        @if($newSecret)
            <div class="mt-2 p-2 bg-rule rounded text-sm break-all">
                <code>{{ $newSecret }}</code>
            </div>
        @endif
        <x-ui.toast kind="error" :message="$error" />
    </div>
</div>
