<div>
    <h2 class="text-lg font-bold text-ink">Sync error rate</h2>
    <p>Every line the sync saw counts: a line that could not be placed is a conflict, never a silent gap.</p>

    <div wire:loading><x-ui.skeleton label="Reading the sync runs…" /></div>

    @if(count($runs) === 0)
        <x-ui.empty-state heading="No sync has run yet.">Nothing syncs on a schedule: a run happens only when a connected ledger is synced, and connecting waits on QuickBooks, Xero or Sage OAuth credentials that do not exist in this checkout.</x-ui.empty-state>
    @else
        <p>This ledger: {{ $seen }} lines seen · {{ $conflicts }} conflicts · {{ $overallLabel }}</p>
        <p>Nothing is posted to a ledger from here: these lines were categorised inside this app only.</p>
        
        <ul>
            @foreach($runs as $run)
                @php
                    $rate = $rates[$run->id];
                @endphp
                <li>
                    <span>{{ $run->created_at->format('Y-m-d H:i') }}</span>,
                    <span>{{ $run->records_synced }} lines categorised</span>,
                    <span>{{ $run->conflicts_count }} conflicts</span>,
                    
                    @if($rate === null)
                        <x-ui.status-pill state="unknown" label="nothing to sync" />
                    @elseif($run->conflicts_count === 0)
                        <x-ui.status-pill state="ok" label="0% conflicts" />
                    @else
                        <x-ui.status-pill state="attention" :label="$rateLabels[$run->id]" />
                    @endif
                    
                    <x-ui.button size="default" wire:click="show({{ $run->id }})">Show its conflicts</x-ui.button>
                    
                    @if($shownRun === $run->id)
                        @if(count($shownConflicts) > 0)
                            <ul>
                                @foreach($shownConflicts as $sc)
                                    <li>{{ $sc->transaction_ref }} - {{ $sc->status }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p>This run had no conflicts.</p>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
