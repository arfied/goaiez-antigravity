<div>
    <x-surface.sample-state module="white-labelling" screen="agency_console" />
    <div class="agency-console p-4">
        <h2 class="text-lg font-bold">Agency Multi-Client Console</h2>
        @if($clients->isEmpty())
            <p class="text-gray-500">No managed clients provisioned.</p>
        @else
            <ul>
                @foreach($clients as $c)
                    <li>#{{ $c->id }}: {{ $c->client_name }} [{{ $c->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
