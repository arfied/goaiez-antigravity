<div>
    <h2>Person timeline</h2>
    @if($evidence->isEmpty())
        <p>No evidence found.</p>
    @else
        <ul>
            @foreach($evidence as $item)
                <li>{{ $item->field_value }}</li>
            @endforeach
        </ul>
    @endif
</div>
