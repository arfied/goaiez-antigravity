<div>

    <div class="agency-console p-4">
        @if($success)
            <div class="bg-surface text-ink border rounded p-2 mb-4">{{ $success }}</div>
        @endif
        @if($error)
            <div class="bg-surface text-ink border rounded p-2 mb-4">{{ $error }}</div>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h2 class="text-lg font-bold">Create Agency</h2>
            <form wire:submit="createAgency" class="flex flex-col gap-2">
                <input type="text" wire:model="agencyName" class="border rounded p-2 text-ink bg-surface" placeholder="Agency Name">
                <input type="text" wire:model="whitelabelDomain" class="border rounded p-2 text-ink bg-surface" placeholder="Whitelabel Domain (Optional)">
                <select wire:model="agencyMode" class="border rounded p-2 text-ink bg-surface">
                    <option value="full_service">Full Service</option>
                    <option value="co_managed">Co-managed</option>
                    <option value="self_service">Self Service</option>
                </select>
                <button type="submit" class="bg-surface text-ink border rounded p-2">Create Agency</button>
            </form>
        </div>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h2 class="text-lg font-bold">Onboard Client</h2>
            <form wire:submit="onboardClient" class="flex flex-col gap-2">
                <input type="number" wire:model="agencyId" class="border rounded p-2 text-ink bg-surface" placeholder="Agency ID">
                <input type="text" wire:model="clientName" class="border rounded p-2 text-ink bg-surface" placeholder="Client Name">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Onboard Client</button>
            </form>
        </div>

        <div class="mb-6">
            <h2 class="text-lg font-bold">Agencies</h2>
            @forelse($agencies as $agency)
                <div class="bg-surface text-ink border rounded p-2 mb-2 mt-2">
                    #{{ $agency->id }}: {{ $agency->agency_name }} ({{ $agency->agency_mode }})
                    @if($agency->whitelabel_domain)
                        - {{ $agency->whitelabel_domain }}
                    @endif
                </div>
            @empty
                <x-ui.empty-state heading="No agencies provisioned." />
            @endforelse
        </div>

        <h2 class="text-lg font-bold">Agency Multi-Client Console</h2>
        @if($clients->isEmpty())
            <p class="text-ink-2">No managed clients provisioned.</p>
        @else
            <ul>
                @foreach($clients as $c)
                    <li>#{{ $c->id }}: {{ $c->client_name }} [{{ $c->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
