<div>
    <div class="plugin-settings-view p-4">
        <h2 class="text-lg font-bold text-ink">Plugin sites</h2>
        @if($installs->isEmpty())
            <p class="text-ink-2">No plugin sites connected yet.</p>
        @else
            <ul>
                @foreach($installs as $install)
                    <li>{{ $install->site_url }} — {{ $install->is_active ? 'active' : 'inactive' }}</li>
                @endforeach
            </ul>
        @endif

        <div class="mt-4 border-t pt-4">
            <h3 class="font-bold">Activate Plugin</h3>
            
            @if($error)
                <div class="text-red-500 mb-2">{{ $error }}</div>
            @endif
            
            @if($success)
                <div class="text-green-500 mb-2">{{ $success }}</div>
            @endif

            <form wire:submit="activate" class="space-y-4 max-w-sm mt-2">
                <div>
                    <label class="block text-sm">Site URL</label>
                    <input type="text" wire:model="siteUrl" class="border p-2 w-full">
                </div>
                <div>
                    <label class="block text-sm">API Key</label>
                    <input type="text" wire:model="apiKey" class="border p-2 w-full">
                </div>
                <button type="submit" class="bg-surface text-ink border rounded p-2">Activate</button>
            </form>
        </div>
    </div>
</div>
