<div id="faq-x176" class="site-block faq {{ $band ?? '' }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="faq">
  <div class="site-block__inner">
    @if (isset($block['items']) && is_array($block['items']))
      @foreach ($block['items'] as $item)
        @php
          $q = isset($item['question']) && is_scalar($item['question']) ? trim((string)$item['question']) : '';
          $a = isset($item['answer']) && is_scalar($item['answer']) ? trim((string)$item['answer']) : '';
        @endphp
        @if ($q !== '' && $a !== '')
          <div class="faq-item" data-question="{{ $q }}">
            <h3>{{ $q }}</h3>
            <p>{{ $a }}</p>
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
