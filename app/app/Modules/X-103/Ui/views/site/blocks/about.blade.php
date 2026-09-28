<div class="site-block about site-block--band">
    <div class="site-block__inner">
        @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string)$block['heading']) !== '')
            <h2>{{ $block['heading'] }}</h2>
        @endif
        <p>{{ $block['text'] }}</p>
    </div>
</div>
