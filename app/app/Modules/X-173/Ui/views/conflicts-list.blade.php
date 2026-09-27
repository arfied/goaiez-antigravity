<div>
    <h2 class="text-lg font-bold text-ink">Sync conflicts</h2>
    <p>A line the sync could not place with confidence waits here for a person. It is never closed by the sync: two systems disagreeing about money is a human decision.</p>

    @if($error)
        <x-ui.error-panel heading="We couldn't resolve that">{{ $error }}</x-ui.error-panel>
    @endif
    
    <x-ui.toast kind="success" :message="$success" />

    <div wire:loading><x-ui.skeleton label="Reading the conflicts…" /></div>

    @if(count($conflicts) === 0)
        <x-ui.empty-state heading="No line has ever been synced.">A conflict appears here only after a sync run, and no sync has run: syncing waits on a connected ledger, and connecting waits on QuickBooks, Xero or Sage OAuth credentials that do not exist in this checkout.</x-ui.empty-state>
    @else
        <ul>
            @foreach($conflicts as $c)
                <li wire:key="conflict-{{ $c->id }}">
                    <span>{{ $c->transaction_ref }}</span>,
                    <span>{{ round($c->confidence_rate * 100) }}%</span>,
                    <span>{{ $c->assigned_category }}</span>,
                    <x-ui.status-pill :state="$c->status === 'open' ? 'attention' : 'ok'" :label="$c->status" />,
                    <span>run {{ $c->sync_run_id }}</span>
                    
                    @if($c->status === 'open')
                        <form wire:submit="resolve({{ $c->id }})">
                            <input type="text" wire:model="resolutions.{{ $c->id }}" placeholder="The account this line belongs to">
                            <x-ui.submit target="resolve({{ $c->id }})" busy="Recording…">Record the account</x-ui.submit>
                        </form>
                    @else
                        <p>Resolved by a person — {{ $c->assigned_category }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
