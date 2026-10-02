@php
    $heroImage = isset($block['image_path']) && is_scalar($block['image_path']) && trim((string) $block['image_path']) !== '';
    $heroVariant = is_scalar($block['variant'] ?? null) ? (string) $block['variant'] : 'split';
    if (! in_array($heroVariant, \App\Modules\X103\Domain\BlockPatchSchema::HERO_VARIANTS, true) || ($heroVariant === 'cover' && ! $heroImage)) {
        $heroVariant = 'split';
    }
    $heroCta = isset($block['cta_label'], $block['cta_url']) && is_scalar($block['cta_label']) && is_scalar($block['cta_url'])
        && trim((string) $block['cta_label']) !== '' && \App\Modules\X103\Domain\BlockPatchSchema::isSafeLink((string) $block['cta_url']);
    $heroAlt = isset($block['image_alt']) && is_scalar($block['image_alt']) ? trim((string) $block['image_alt']) : '';
    $heroSubline = isset($block['subline']) && is_scalar($block['subline']) && trim((string) $block['subline']) !== '';
@endphp
<div class="site-block hero {{ $band ?? '' }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="hero">
    <div class="site-block__inner">
        @if($heroVariant === 'cover')
            <div class="hero-cover">
                <img src="{{ $context['tenant_storage_url_prefix'] }}{{ basename($block['image_path']) }}" alt="{{ $heroAlt }}">
                <div>
                    <h1{!! empty($context['editable']) ? '' : ' data-field="headline"' !!}>{{ $block['headline'] }}</h1>
                    @if($heroSubline)
                        <p class="lede"{!! empty($context['editable']) ? '' : ' data-field="subline"' !!}>{{ $block['subline'] }}</p>
                    @endif
                    @if($heroCta)
                        <div class="actions"><a href="{{ $block['cta_url'] }}" class="site-cta site-cta--primary"{!! empty($context['editable']) ? '' : ' data-field="cta_label"' !!}>{{ $block['cta_label'] }}</a></div>
                    @endif
                </div>
            </div>
        @elseif($heroVariant === 'centered')
            <div class="hero-center">
                <h1{!! empty($context['editable']) ? '' : ' data-field="headline"' !!}>{{ $block['headline'] }}</h1>
                @if($heroSubline)
                    <p class="lede"{!! empty($context['editable']) ? '' : ' data-field="subline"' !!}>{{ $block['subline'] }}</p>
                @endif
                @if($heroCta)
                    <div class="actions"><a href="{{ $block['cta_url'] }}" class="site-cta site-cta--primary"{!! empty($context['editable']) ? '' : ' data-field="cta_label"' !!}>{{ $block['cta_label'] }}</a></div>
                @endif
                @if($heroImage)
                    <div class="media media--wide">
                        <img src="{{ $context['tenant_storage_url_prefix'] }}{{ basename($block['image_path']) }}" alt="{{ $heroAlt }}">
                    </div>
                @endif
            </div>
        @else
            <div class="hero__grid">
                <div class="stack">
                    <h1{!! empty($context['editable']) ? '' : ' data-field="headline"' !!}>{{ $block['headline'] }}</h1>
                    @if($heroSubline)
                        <p class="lede"{!! empty($context['editable']) ? '' : ' data-field="subline"' !!}>{{ $block['subline'] }}</p>
                    @endif
                    @if($heroCta)
                        <div class="actions"><a href="{{ $block['cta_url'] }}" class="site-cta site-cta--primary"{!! empty($context['editable']) ? '' : ' data-field="cta_label"' !!}>{{ $block['cta_label'] }}</a></div>
                    @endif
                </div>
                @if($heroImage)
                    <div class="hero__media">
                        <div class="media media--wide">
                            <img src="{{ $context['tenant_storage_url_prefix'] }}{{ basename($block['image_path']) }}" alt="{{ $heroAlt }}"@if(isset($block['image_width'], $block['image_height']) && (int)$block['image_width'] > 0 && (int)$block['image_height'] > 0) width="{{ (int)$block['image_width'] }}" height="{{ (int)$block['image_height'] }}"@endif>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
