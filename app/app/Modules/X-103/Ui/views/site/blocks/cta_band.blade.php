<div class="site-block cta site-block--primary" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="cta_band">
    <div class="site-block__inner cta-band">
        <h2{!! empty($context['editable']) ? '' : ' data-field="heading"' !!}>{{ $block['heading'] }}</h2>
        @if(isset($block['text']) && is_scalar($block['text']) && trim((string) $block['text']) !== '')
            <p{!! empty($context['editable']) ? '' : ' data-field="text"' !!}>{{ $block['text'] }}</p>
        @endif
        @if(isset($block['label'], $block['url']) && is_scalar($block['label']) && is_scalar($block['url']) && trim((string) $block['label']) !== '' && \App\Modules\X103\Domain\BlockPatchSchema::isSafeLink((string) $block['url']))
            <a href="{{ $block['url'] }}"{!! empty($context['editable']) ? '' : ' data-field="label"' !!}>{{ $block['label'] }}</a>
        @endif
    </div>
</div>
