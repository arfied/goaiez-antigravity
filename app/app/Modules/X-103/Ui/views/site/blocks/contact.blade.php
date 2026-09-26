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
                <li>{{ $h['day'] }}: {{ trim((string) ($h['close'] ?? '')) === '' ? ($h['open'] ?? '') : (($h['open'] ?? '').' - '.($h['close'] ?? '')) }}</li>
            @endif
        @endforeach
        </ul>
    @endif
    @if(isset($block['facts']) && is_array($block['facts']))
        @php $factLabels = \App\Services\Facts\BusinessFactKey::all(); @endphp
        <ul>
        @foreach($block['facts'] as $k => $v)
            @if(is_scalar($v) && trim((string)$v) !== '')
                <li>{{ $factLabels[$k]['label'] ?? $k }}: {{ $v }}</li>
            @endif
        @endforeach
        </ul>
    @endif
    @if(isset($block['industry_facts']) && is_array($block['industry_facts']))
        <ul>
        @foreach($block['industry_facts'] as $f)
            @if(isset($f['label'], $f['value']) && is_scalar($f['value']) && trim((string)$f['value']) !== '')
                <li>{{ $f['label'] }}: {{ $f['value'] }}</li>
            @endif
        @endforeach
        </ul>
    @endif
</div>
