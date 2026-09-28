<div class="site-block contact {{ $band ?? '' }}">
    <div class="site-block__inner">
        @if(isset($block['address']) && is_scalar($block['address']) && trim((string)$block['address']) !== '')
            <h3>Visit</h3>
            <p>{{ $block['address'] }}</p>
        @endif
        @if(isset($block['phone']) && is_scalar($block['phone']) && trim((string)$block['phone']) !== '')
            <h3>Call</h3>
            <p><a href="tel:{{ preg_replace('/[\s\(\)\.\-]/', '', (string)$block['phone']) }}">{{ $block['phone'] }}</a></p>
        @endif
        @if(isset($block['email']) && is_scalar($block['email']) && trim((string)$block['email']) !== '')
            <h3>Write</h3>
            <p><a href="mailto:{{ $block['email'] }}">{{ $block['email'] }}</a></p>
        @endif
        @if(isset($block['hours']) && is_array($block['hours']))
            <h3>Hours</h3>
            <ul>
            @foreach($block['hours'] as $h)
                @if(isset($h['day']) && is_scalar($h['day']))
                    <li>{{ $h['day'] }}: {{ trim((string) ($h['close'] ?? '')) === '' ? 'Closed' : (($h['open'] ?? '').' - '.($h['close'] ?? '')) }}</li>
                @endif
            @endforeach
            </ul>
        @endif
        @if(isset($block['facts']) && is_array($block['facts']))
            <h3>Also</h3>
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
            @if(!isset($block['facts']) || !is_array($block['facts']))
                <h3>Also</h3>
            @endif
            <ul>
            @foreach($block['industry_facts'] as $f)
                @if(isset($f['label'], $f['value']) && is_scalar($f['value']) && trim((string)$f['value']) !== '')
                    <li>{{ $f['label'] }}: {{ $f['value'] }}</li>
                @endif
            @endforeach
            </ul>
        @endif
    </div>
</div>
