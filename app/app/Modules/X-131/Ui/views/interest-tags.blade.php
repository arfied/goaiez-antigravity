<div>
    <h2>Interests</h2>
    @if($interests->isEmpty())
        <p>No interests recorded yet</p>
    @else
        <ul>
            @foreach($interests as $i)
                <li>
                    {{ $people[$i->person_id] ?? 'Customer #' . $i->person_id }} - {{ $i->topic }} - 
                    @if($i->is_tenant_set)
                        set by you
                    @else
                        inferred, {{ (int) round($i->confidence_rate * 100) }}% sure
                    @endif
                    ({{ $i->source }})
                </li>
            @endforeach
        </ul>
    @endif
</div>
