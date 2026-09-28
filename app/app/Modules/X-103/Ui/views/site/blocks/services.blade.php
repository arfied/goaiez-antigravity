<div class="site-block services {{ $band ?? '' }}">
    <div class="site-block__inner">
        @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string)$block['heading']) !== '')
            <h2>{{ $block['heading'] }}</h2>
        @endif
        <ul>
            @foreach($block['items'] ?? [] as $item)
                @if(isset($item['name']) && is_scalar($item['name']) && trim((string)$item['name']) !== '')
                    <li class="card">
                        <strong>{{ $item['name'] }}</strong>
                        @if(isset($item['price_text']) && is_scalar($item['price_text']) && trim((string)$item['price_text']) !== '')
                            <span>{{ $item['price_text'] }}</span>
                        @endif
                        @if(isset($item['description']) && is_scalar($item['description']) && trim((string)$item['description']) !== '')
                            <p>{{ $item['description'] }}</p>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</div>
