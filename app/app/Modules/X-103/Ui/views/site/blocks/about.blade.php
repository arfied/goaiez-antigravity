@php $layout = in_array($block['variant'] ?? null, \App\Modules\X103\Domain\BlockPatchSchema::VARIANTS['about'], true) ? $block['variant'] : 'plain'; @endphp
<div class="site-block about {{ trim(($band ?? '').($layout !== 'plain' ? ' about--'.$layout : '')) }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="about">
    <div class="site-block__inner">
        @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string)$block['heading']) !== '')
            <h2{!! empty($context['editable']) ? '' : ' data-field="heading"' !!}>{{ $block['heading'] }}</h2>
        @endif
        <p{!! empty($context['editable']) ? '' : ' data-field="text"' !!}>{{ $block['text'] }}</p>
        @if(isset($block['image_path']) && is_scalar($block['image_path']) && trim((string) $block['image_path']) !== '')
            <div class="about-media"><img src="{{ $context['tenant_storage_url_prefix'] }}{{ basename((string) $block['image_path']) }}" alt="{{ isset($block['image_alt']) && is_scalar($block['image_alt']) ? trim((string) $block['image_alt']) : '' }}"></div>
        @endif
    </div>
</div>
