@php $layout = in_array($block['variant'] ?? null, \App\Modules\X103\Domain\BlockPatchSchema::VARIANTS['reviews_strip'], true) ? $block['variant'] : 'cards'; @endphp
<div class="site-block reviews {{ trim(($band ?? '').($layout !== 'cards' ? ' reviews--'.$layout : '')) }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="reviews_strip">
    <div class="site-block__inner">
        @if(isset($block['heading']) && is_scalar($block['heading']) && trim((string)$block['heading']) !== '')
            <h2{!! empty($context['editable']) ? '' : ' data-field="heading"' !!}>{{ $block['heading'] }}</h2>
        @endif
        <div class="review-list">
            @foreach($block['items'] ?? [] as $item)
                @if(isset($item['rating']) && is_scalar($item['rating']) && isset($item['text']) && is_scalar($item['text']))
                    <blockquote class="card">
                        @php $stars = is_numeric($item['rating']) ? max(0, min(5, (int) round((float) $item['rating']))) : 0; @endphp
                        @if($stars > 0)
                            <div class="review-stars" aria-hidden="true">{{ str_repeat('★', $stars) }}{{ str_repeat('☆', 5 - $stars) }}</div>
                        @endif
                        <p>{{ $item['text'] }}</p>
                        <cite>
                            {{ $item['rating'] }} stars
                            @if(isset($item['author']) && is_scalar($item['author']) && trim((string)$item['author']) !== '')
                                by {{ $item['author'] }}
                            @endif
                            @if(isset($item['source']) && is_scalar($item['source']) && trim((string)$item['source']) !== '')
                                on {{ $item['source'] }}
                            @endif
                        </cite>
                    </blockquote>
                @endif
            @endforeach
        </div>
    </div>
</div>
