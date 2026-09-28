@if(empty($band))
<div id="videos-x176">
@else
<div id="videos-x176" class="{{ $band ?? '' }}">
@endif
  <div class="site-block__inner">
    <div class="media media--wide">
      <div class="video-item" data-name="{{ $block['name'] }}" data-url="{{ $block['contentUrl'] }}">{{ $block['name'] }}</div>
    </div>
  </div>
</div>
