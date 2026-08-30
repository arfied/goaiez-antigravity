<div>
    <div class="alert-reply-container p-4">
        <h3 class="text-lg font-bold">Fast-Path Reply Codes</h3>
        @if($codes->isEmpty())
            <p class="text-gray-500">No active reply codes pending.</p>
        @else
            <ul>
                @foreach($codes as $c)
                    <li>Code #{{ $c->code }} for Alert #{{ $c->alert_id }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
