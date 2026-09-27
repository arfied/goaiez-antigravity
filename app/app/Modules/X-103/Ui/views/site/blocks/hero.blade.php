<div class="site-block hero">
    <h1>{{ $block['headline'] }}</h1>
    @if(isset($block['subline']) && is_scalar($block['subline']) && trim((string)$block['subline']) !== '')
        <p>{{ $block['subline'] }}</p>
    @endif
    @if(isset($block['image_path']) && is_scalar($block['image_path']) && trim((string)$block['image_path']) !== '')
        <img src="{{ $context['tenant_storage_url_prefix'] }}{{ basename($block['image_path']) }}" alt="{{ isset($block['image_alt']) && is_scalar($block['image_alt']) ? trim((string)$block['image_alt']) : '' }}"@if(isset($block['image_width'], $block['image_height']) && (int)$block['image_width'] > 0 && (int)$block['image_height'] > 0) width="{{ (int)$block['image_width'] }}" height="{{ (int)$block['image_height'] }}"@endif>
    @endif
</div>
