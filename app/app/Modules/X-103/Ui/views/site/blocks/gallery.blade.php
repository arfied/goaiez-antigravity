<div class="site-block gallery">
    @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string)$block['heading']) !== '')
        <h2>{{ $block['heading'] }}</h2>
    @endif
    @foreach($block['items'] ?? [] as $item)
        @if(isset($item['image_path']) && is_scalar($item['image_path']) && trim((string)$item['image_path']) !== '')
            <img src="{{ $context['tenant_storage_url_prefix'] }}{{ basename($item['image_path']) }}" alt="{{ isset($item['alt']) && is_scalar($item['alt']) ? trim((string)$item['alt']) : '' }}">
        @endif
    @endforeach
</div>
