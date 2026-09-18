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
                                <button class="ml-2 text-blue-500 underline copy-affordance" data-value="{{ $record['value'] }}">Copy</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <x-ui.empty-state heading="No sending domain yet.">Connecting a domain writes it here with the records to publish.</x-ui.empty-state>
        @endif
    </div>
</div>
