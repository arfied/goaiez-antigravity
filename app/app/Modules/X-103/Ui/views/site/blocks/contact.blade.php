<div class="site-block contact">
    @if(isset($block['address']) && is_scalar($block['address']) && trim((string)$block['address']) !== '')
        <p>Address: {{ $block['address'] }}</p>
    @endif
    @if(isset($block['phone']) && is_scalar($block['phone']) && trim((string)$block['phone']) !== '')
        <p>Phone: {{ $block['phone'] }}</p>
    @endif
    @if(isset($block['email']) && is_scalar($block['email']) && trim((string)$block['email']) !== '')
        <p>Email: {{ $block['email'] }}</p>
    @endif
    @if(isset($block['hours']) && is_array($block['hours']))
        <ul>
        @foreach($block['hours'] as $h)
            @if(isset($h['day']) && is_scalar($h['day']))
                <li>{{ $h['day'] }}: {{ $h['open'] ?? '' }} - {{ $h['close'] ?? '' }}</li>
            @endif
        @endforeach
        </ul>
    @endif
</div>
