<div>
    <div class="alert-reply-container p-4">
        <h2 class="text-lg font-bold text-ink">Reply codes</h2>
        @if($codes->isEmpty())
            <p class="text-ink-2">No active reply codes pending.</p>
        @else
            <ul>
                @foreach($codes as $c)
                    <li>Code #{{ $c->code }} for Alert #{{ $c->alert_id }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
