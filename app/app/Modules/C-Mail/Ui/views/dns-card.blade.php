<div>
    <x-surface.sample-state module="C-Mail" screen="dns_card" />
    <div class="dns-card-view p-4">
        <h3 class="text-lg font-bold">DNS & DMARC Records</h3>
        @if($domain)
            <div class="records mt-4">
                @foreach($records as $type => $record)
                    <div class="record mb-4 p-4 border rounded">
                        <h4 class="font-bold">{{ $type }}</h4>
                        <div class="mt-2">
                            <span class="font-mono text-sm bg-gray-100 p-1">{{ $record['name'] }}</span>
                            <span class="font-mono text-sm bg-gray-100 p-1 ml-2">{{ $record['value'] }}</span>
                            <button class="ml-2 text-blue-500 underline copy-affordance" data-value="{{ $record['value'] }}">Copy</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p>No mail domain configured.</p>
        @endif
    </div>
</div>
