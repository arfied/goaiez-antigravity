<div class="site-block about {{ $band ?? '' }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="about">
    <div class="site-block__inner">
        @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string)$block['heading']) !== '')
            <h2{!! empty($context['editable']) ? '' : ' data-field="heading"' !!}>{{ $block['heading'] }}</h2>
        @endif
        <p{!! empty($context['editable']) ? '' : ' data-field="text"' !!}>{{ $block['text'] }}</p>
    </div>
</div>
