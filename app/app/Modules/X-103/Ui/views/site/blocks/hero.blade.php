<div class="site-block hero">
    <h1>{{ $block['headline'] }}</h1>
    @if(isset($block['subline']) && is_scalar($block['subline']) && trim((string)$block['subline']) !== '')
        <p>{{ $block['subline'] }}</p>
    @endif
    @if(isset($block['image_path']) && is_scalar($block['image_path']) && trim((string)$block['image_path']) !== '')
        <img src="{{ $context['tenant_storage_url_prefix'] }}{{ basename($block['image_path']) }}" alt="{{ isset($block['image_alt']) && is_scalar($block['image_alt']) ? trim((string)$block['image_alt']) : '' }}">
    @endif
</div>
