<div class="site-block about">
    @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string)$block['heading']) !== '')
        <h2>{{ $block['heading'] }}</h2>
    @endif
    <p>{{ $block['text'] }}</p>
</div>
