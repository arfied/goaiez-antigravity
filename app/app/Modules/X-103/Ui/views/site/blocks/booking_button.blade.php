@php $layout = in_array($block['variant'] ?? null, \App\Modules\X103\Domain\BlockPatchSchema::VARIANTS['booking_button'], true) ? $block['variant'] : 'inline'; @endphp
<div class="site-block booking {{ trim(($band ?? '').($layout !== 'inline' ? ' booking--'.$layout : '')) }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="booking_button">
    <div class="site-block__inner">
        <div class="actions">
            @if(isset($block['url']) && is_scalar($block['url']) && trim((string) $block['url']) !== '')
                <a href="{{ $block['url'] }}" class="site-cta site-cta--primary"{!! empty($context['editable']) ? '' : ' data-field="label"' !!}>{{ $block['label'] }}</a>
            @else
                <span class="site-cta site-cta--primary site-cta--off" aria-disabled="true"{!! empty($context['editable']) ? '' : ' data-field="label"' !!}>{{ $block['label'] }}</span>
            @endif
        </div>
    </div>
</div>
