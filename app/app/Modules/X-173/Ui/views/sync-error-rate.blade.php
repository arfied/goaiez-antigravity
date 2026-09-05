<div>
    <h1>Sync error rate</h1>
    <p>Every line the sync saw counts: a line that could not be placed is a conflict, never a silent gap (§30.5).</p>

    <div wire:loading><x-ui.skeleton label="Reading the sync runs…" /></div>

    @if(count($runs) === 0)
        <x-ui.empty-state heading="No sync has run yet.">Invoices and payments flow nightly once a ledger is connected.</x-ui.empty-state>
    @else
        <p>This ledger: {{ $seen }} lines seen · {{ $conflicts }} conflicts · {{ $overall === null ? 'nothing synced yet' : round($overall * 100).'% conflicts' }}</p>
        
        <ul>
            @foreach($runs as $run)
                @php
                    $rate = $rates[$run->id];
                @endphp
                <li>
                    <span>{{ $run->created_at->format('Y-m-d H:i') }}</span>,
                    <span>{{ $run->records_synced }} lines posted</span>,
                    <span>{{ $run->conflicts_count }} conflicts</span>,
                    
                    @if($rate === null)
                        <x-ui.status-pill state="unknown" label="nothing to sync" />
                    @elseif($run->conflicts_count === 0)
                        <x-ui.status-pill state="ok" label="0% conflicts" />
                    @else
                        <x-ui.status-pill state="attention" :label="round($rate * 100).'% conflicts'" />
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
