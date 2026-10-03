@php $ctaPhoto = isset($block['image_path']) && is_scalar($block['image_path']) && trim((string) $block['image_path']) !== ''; @endphp
<div class="site-block cta site-block--primary{{ $ctaPhoto ? ' cta--photo' : '' }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="cta_band">
    @if($ctaPhoto)
        <img class="cta-photo" src="{{ $context['tenant_storage_url_prefix'] }}{{ basename((string) $block['image_path']) }}" alt="">
    @endif
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
