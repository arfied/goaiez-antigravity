<div id="faq-x176" class="site-block--band">
  <div class="site-block__inner">
    @if (isset($block['items']) && is_array($block['items']))
      @foreach ($block['items'] as $item)
        <div class="faq-item" data-question="{{ $item['question'] }}">{{ $item['question'] }} - {{ $item['answer'] }}</div>
      @endforeach
    @elseif (isset($block['question']))
      <div class="faq-item" data-question="{{ $block['question'] }}">{{ $block['question'] }} - {{ $block['answer'] }}</div>
    @endif
  </div>
</div>
