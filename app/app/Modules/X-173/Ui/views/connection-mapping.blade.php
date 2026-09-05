<div>
    <div class="connection-mapping-view p-4">
        <h3 class="text-lg font-bold">General Ledger Account Mapping</h3>
        
        @if($oauthMessage)
            <div class="text-sm text-blue-500">{{ $oauthMessage }}</div>
        @endif
        
        <button wire:click="connect('quickbooks')" class="bg-gray-200 px-4 py-2">Connect quickbooks</button>
        
        @if($connection)
            @if($message)
                <div class="text-sm text-red-500">{{ $message }}</div>
            @endif
            <div class="mt-4 border p-4">
                <input type="text" wire:model="internalCategory" placeholder="Internal Category">
                <input type="text" wire:model="remoteGlAccountId" placeholder="Remote ID">
                <input type="text" wire:model="remoteGlAccountName" placeholder="Remote Name">
                <button wire:click="mapAccount({{ $connection->id }})" class="bg-blue-500 text-white px-4 py-1">Map</button>
            </div>
            
            <div class="mt-4">
                @foreach($mappings as $mapping)
                    <div>{{ $mapping->internal_category }} -> {{ $mapping->remote_gl_account_name }} ({{ $mapping->remote_gl_account_id }})</div>
                @endforeach
            </div>
        @endif
    </div>
</div>
