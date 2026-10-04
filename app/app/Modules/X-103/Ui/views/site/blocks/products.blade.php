@php
    $productItems = array_values(array_filter((array) ($block['items'] ?? []), fn ($i) => is_array($i) && isset($i['name']) && is_scalar($i['name']) && trim((string) $i['name']) !== ''));
@endphp
<div class="site-block products {{ $band ?? '' }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="products">
    <div class="site-block__inner">
        @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string) $block['heading']) !== '')
            <h2{!! empty($context['editable']) ? '' : ' data-field="heading"' !!}>{{ $block['heading'] }}</h2>
        @endif
        <ul class="product-list">
            @foreach($productItems as $item)
                <li class="card">
                    @if(isset($item['image_path']) && is_scalar($item['image_path']) && trim((string) $item['image_path']) !== '')
                        <img src="{{ $context['tenant_storage_url_prefix'] }}{{ basename((string) $item['image_path']) }}" alt="{{ isset($item['image_alt']) && is_scalar($item['image_alt']) ? trim((string) $item['image_alt']) : trim((string) $item['name']) }}" loading="lazy" decoding="async">
                    @endif
                    <strong>{{ $item['name'] }}</strong>
                    @if(isset($item['price_text']) && is_scalar($item['price_text']) && trim((string) $item['price_text']) !== '')
                        <span>{{ $item['price_text'] }}</span>
                    @endif
                    @if(isset($item['description']) && is_scalar($item['description']) && trim((string) $item['description']) !== '')
                        <p>{{ $item['description'] }}</p>
                    @endif
                    @if(isset($item['url']) && is_scalar($item['url']) && \App\Modules\X103\Domain\BlockPatchSchema::isSafeLink((string) $item['url']))
                        <a href="{{ $item['url'] }}">View {{ $item['name'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
