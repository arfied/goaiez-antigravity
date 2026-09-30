<div class="site-block booking {{ $band ?? '' }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="booking_button">
    <div class="site-block__inner">
        <div class="actions">
            @if(isset($block['url']) && is_scalar($block['url']) && trim((string) $block['url']) !== '')
                <a href="{{ $block['url'] }}" class="site-cta site-cta--primary">{{ $block['label'] }}</a>
            @else
                <span class="site-cta site-cta--primary site-cta--off" aria-disabled="true">{{ $block['label'] }}</span>
            @endif
        </div>
    </div>
</div>
