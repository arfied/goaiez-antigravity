<div>
    <h2>Resolution rate & confidence</h2>
    @if($links->isEmpty())
        <p>No links found.</p>
    @else
        <ul>
            @foreach($links as $link)
                <li>{{ $link->id }} - {{ $link->confidence_rate }}</li>
            @endforeach
        </ul>
    @endif
</div>
