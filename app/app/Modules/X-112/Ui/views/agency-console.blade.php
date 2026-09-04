<x-surface.sample-state module="white-labelling" screen="agency_console" />
<div>
    <div class="agency-console p-4">
        <h3 class="text-lg font-bold">Agency Multi-Client Console</h3>
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
