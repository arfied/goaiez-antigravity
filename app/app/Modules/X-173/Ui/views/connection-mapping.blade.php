<div>
    <h1>Ledger connection and mapping</h1>
    <p>This screen maps each category to one ledger account, reviewed once by a person. Nothing has synced: connecting a ledger waits on QuickBooks, Xero or Sage OAuth credentials, and none exist in this checkout.</p>

    @if($error)
        <x-ui.error-panel :heading="$errorHeading ?? 'Could not save that mapping'">{{ $error }}</x-ui.error-panel>
    @endif
    
    <x-ui.toast kind="success" :message="$success" />
    
    @if($waiting)
        <x-ui.attention-card state="attention" heading="Waiting on the ledger">{{ $waiting }}</x-ui.attention-card>
    @endif

    <div wire:loading><x-ui.skeleton label="Reading the ledger…" /></div>

    @if(count($connections) === 0)
        <x-ui.empty-state heading="No ledger connected yet.">Connecting waits on OAuth credentials for QuickBooks, Xero and Sage; none exist in this checkout, so the Connect button below names what it is waiting on and connects nothing. When the credentials are granted the chart of accounts is proposed here for confirmation.</x-ui.empty-state>
    @else
        <ul>
            @foreach($connections as $conn)
                <li>
                    <span>{{ $conn->provider }}</span>,
                    <span>{{ $conn->realm_id }}</span>,
                    <x-ui.status-pill :state="$conn->is_active ? 'ok' : 'attention'" :label="$conn->is_active ? 'active' : 'inactive'" />
                    
                    @php
                        $connMappings = $mappings->get($conn->id, collect());
                    @endphp
                    
                    @if($connMappings->isEmpty())
                        <p>Nothing mapped yet for this ledger.</p>
                    @else
                        @foreach($connMappings as $m)
                            <p>{{ $m->internal_category }} → {{ $m->remote_gl_account_name }} ({{ $m->remote_gl_account_id }})</p>
                        @endforeach
                    @endif

                    <form wire:submit="mapAccount({{ $conn->id }})">
                        <input type="text" wire:model="map.{{ $conn->id }}.category" placeholder="Internal Category">
                        <input type="text" wire:model="map.{{ $conn->id }}.glId" placeholder="Remote ID">
                        <input type="text" wire:model="map.{{ $conn->id }}.glName" placeholder="Remote Name">
                        <x-ui.submit target="mapAccount({{ $conn->id }})" busy="Mapping…">Map this category</x-ui.submit>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <form wire:submit="connect">
        <select wire:model="provider">
            <option value="quickbooks">quickbooks</option>
            <option value="xero">xero</option>
            <option value="sage">sage</option>
        </select>
        <input type="text" wire:model="realmId" placeholder="Company / realm id">
        <x-ui.submit target="connect" busy="Connecting…">Connect</x-ui.submit>
    </form>
</div>
