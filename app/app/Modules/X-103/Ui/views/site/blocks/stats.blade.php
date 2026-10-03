@php $layout = in_array($block['variant'] ?? null, \App\Modules\X103\Domain\BlockPatchSchema::VARIANTS['stats'], true) ? $block['variant'] : 'row'; @endphp
<div class="site-block stats {{ trim(($band ?? '').($layout !== 'row' ? ' stats--'.$layout : '')) }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="stats">
    <div class="site-block__inner">
        @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string) $block['heading']) !== '')
            <h2{!! empty($context['editable']) ? '' : ' data-field="heading"' !!}>{{ $block['heading'] }}</h2>
        @endif
        <ul class="stat-list">
            @foreach($block['items'] ?? [] as $item)
                @if(is_array($item) && isset($item['value']) && is_scalar($item['value']) && trim((string) $item['value']) !== '')
                    <li>
                        <strong>{{ $item['value'] }}</strong>
                        @if(isset($item['label']) && is_scalar($item['label']) && trim((string) $item['label']) !== '')
                            <span>{{ $item['label'] }}</span>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</div>
