<div class="site-block gallery {{ $band ?? '' }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="gallery">
    @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string)$block['heading']) !== '')
        <h2{!! empty($context['editable']) ? '' : ' data-field="heading"' !!}>{{ $block['heading'] }}</h2>
    @endif
    @foreach($block['items'] ?? [] as $item)
        @if(isset($item['image_path']) && is_scalar($item['image_path']) && trim((string)$item['image_path']) !== '')
            <div class="media media--square">
                <img src="{{ $context['tenant_storage_url_prefix'] }}{{ basename($item['image_path']) }}" alt="{{ isset($item['alt']) && is_scalar($item['alt']) ? trim((string)$item['alt']) : '' }}"@if(isset($item['width'], $item['height']) && (int)$item['width'] > 0 && (int)$item['height'] > 0) width="{{ (int)$item['width'] }}" height="{{ (int)$item['height'] }}"@endif loading="lazy" decoding="async">
            </div>
        @endif
    @endforeach
</div>
