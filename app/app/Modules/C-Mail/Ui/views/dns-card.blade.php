<div>
    <div class="dns-card-view p-4">
        <h2 class="text-lg font-bold text-ink">Email domain</h2>
        @if($domain)
            <p class="text-ink-2">{{ $domain->domain_name }}</p>
            @if(empty($records))
                <p class="text-ink-2">Every record for this domain is verified.</p>
            @else
                <div class="records mt-4">
                    @foreach($records as $type => $record)
                        <div class="record mb-4 p-4 border rounded">
                            <h3 class="font-bold">{{ $type }}</h3>
                            <div class="mt-2">
                                <span class="font-mono text-sm bg-surface p-1">{{ $record['name'] }}</span>
                                <span class="font-mono text-sm bg-surface p-1 ml-2">{{ $record['value'] }}</span>
                                <x-ui.button variant="quiet" size="default" type="button" class="ml-2" x-on:click="navigator.clipboard.writeText(@js($record['value']))">Copy</x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <x-ui.empty-state heading="No sending domain yet.">Connecting a domain writes it here with the records to publish.</x-ui.empty-state>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <h3 class="text-ink font-bold">Add Domain</h3>
            <form wire:submit="submit" class="flex flex-col gap-2">
                <input type="text" wire:model="domainName" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Domain Name">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
            @if($success)
                <div class="text-ink bg-surface p-2 mt-2">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-ink-2 bg-surface p-2 mt-2">{{ $error }}</div>
            @endif
        </div>
    </div>
</div>
