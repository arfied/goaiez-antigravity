<div class="site-block hero">
    <h1>{{ $block['headline'] }}</h1>
    @if(isset($block['subline']) && is_scalar($block['subline']) && trim((string)$block['subline']) !== '')
        <p>{{ $block['subline'] }}</p>
    @endif
    @if(isset($block['image_path']) && is_scalar($block['image_path']) && trim((string)$block['image_path']) !== '')
        <img src="{{ rtrim($context['tenant_storage_url_prefix'] ?? '', '/') . '/' . ltrim((string)$block['image_path'], '/') }}" alt="">
    @endif
</div>
