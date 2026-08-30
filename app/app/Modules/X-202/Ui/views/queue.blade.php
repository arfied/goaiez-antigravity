<div>
    <div class="approval-queue p-4">
        <h3 class="text-lg font-bold">Pending Approval Queue</h3>
        @if($items->isEmpty())
            <p class="text-gray-500">No items pending approval.</p>
        @else
            <ul>
                @foreach($items as $i)
                    <li>#{{ $i->id }}: {{ $i->subject }} [{{ $i->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
