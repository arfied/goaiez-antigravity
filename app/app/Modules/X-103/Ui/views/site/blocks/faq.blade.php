@php $layout = in_array($block['variant'] ?? null, \App\Modules\X103\Domain\BlockPatchSchema::VARIANTS['faq'], true) ? $block['variant'] : 'list'; @endphp
<div id="faq-x176" class="site-block faq {{ trim(($band ?? '').($layout !== 'list' ? ' faq--'.$layout : '')) }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="faq">
  <div class="site-block__inner">
    @if (isset($block['items']) && is_array($block['items']))
      @foreach ($block['items'] as $itemIndex => $item)
        @php
          $q = isset($item['question']) && is_scalar($item['question']) ? trim((string)$item['question']) : '';
          $a = isset($item['answer']) && is_scalar($item['answer']) ? trim((string)$item['answer']) : '';
        @endphp
        @if ($q !== '' && $a !== '')
          <div class="faq-item" data-question="{{ $q }}">
            <h3{!! empty($context['editable']) ? '' : ' data-field="items.'.(int) $itemIndex.'.question"' !!}>{{ $q }}</h3>
            <p{!! empty($context['editable']) ? '' : ' data-field="items.'.(int) $itemIndex.'.answer"' !!}>{{ $a }}</p>
          </div>
        @endif
      @endforeach
    @elseif (isset($block['question']))
      @php
        $q = isset($block['question']) && is_scalar($block['question']) ? trim((string)$block['question']) : '';
        $a = isset($block['answer']) && is_scalar($block['answer']) ? trim((string)$block['answer']) : '';
      @endphp
      @if ($q !== '' && $a !== '')
        <div class="faq-item" data-question="{{ $q }}">
          <h3{!! empty($context['editable']) ? '' : ' data-field="question"' !!}>{{ $q }}</h3>
          <p{!! empty($context['editable']) ? '' : ' data-field="answer"' !!}>{{ $a }}</p>
        </div>
      @endif
    @endif
  </div>
</div>
