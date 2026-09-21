<div>
    <div class="doc-vault-view p-4 bg-surface text-ink border">
        <h2 class="text-lg font-bold">Employee Document Vault</h2>
        
        <p class="mt-4 mb-6">No documents are stored yet, but here is who will be able to view them.</p>

        @if($staff->isEmpty())
            <x-ui.empty-state heading="No staff yet.">Invite staff on the Staff screen.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($staff as $s)
                    <li class="py-2 flex justify-between" wire:key="staff-{{ $s->id }}">
                        <div>
                            <span class="font-semibold">{{ $s->name }}</span>
                            <span class="text-sm text-ink-2">({{ $s->role_name }})</span>
                        </div>
                        <div>
                            @if($s->can_view)
                                <span class="text-sm text-ink-2">Can view documents</span>
                            @else
                                <span class="text-sm text-ink-3">Not permitted</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
