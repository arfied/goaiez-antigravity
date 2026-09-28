<div class="site-block team site-block--band">
    <div class="site-block__inner">
        @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string)$block['heading']) !== '')
            <h2>{{ $block['heading'] }}</h2>
        @endif
        <ul>
            @foreach($block['items'] ?? [] as $item)
                @continue(str_starts_with((string) ($item['name'] ?? ''), 'demo·'))
                @if(isset($item['name']) && is_scalar($item['name']) && trim((string)$item['name']) !== '')
                    <li class="card">
                        <strong>{{ $item['name'] }}</strong>
                        @if(isset($item['role']) && is_scalar($item['role']) && trim((string)$item['role']) !== '')
                            <span>{{ $item['role'] }}</span>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</div>
