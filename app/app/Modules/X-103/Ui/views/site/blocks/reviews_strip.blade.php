<div class="site-block reviews {{ $band ?? '' }}">
    <div class="site-block__inner">
        @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string)$block['heading']) !== '')
            <h2>{{ $block['heading'] }}</h2>
        @endif
        <div class="review-list">
            @foreach($block['items'] ?? [] as $item)
                @if(isset($item['rating']) && is_scalar($item['rating']) && isset($item['text']) && is_scalar($item['text']))
                    <blockquote class="card">
                        <p>{{ $item['text'] }}</p>
                        <cite>
                            {{ $item['rating'] }} stars
                            @if(isset($item['author']) && is_scalar($item['author']) && trim((string)$item['author']) !== '')
                                by {{ $item['author'] }}
                            @endif
                            @if(isset($item['source']) && is_scalar($item['source']) && trim((string)$item['source']) !== '')
                                on {{ $item['source'] }}
                            @endif
                        </cite>
                    </blockquote>
                @endif
            @endforeach
        </div>
    </div>
</div>
