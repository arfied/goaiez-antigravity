@if(empty($band))
<div id="videos-x176" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="video_embed">
@else
<div id="videos-x176" class="{{ $band ?? '' }}" data-block-index="{{ $blockIndex ?? '' }}" data-block-type="video_embed">
@endif
  <div class="site-block__inner">
    <div class="media media--wide">
      <div class="video-item" data-name="{{ $block['name'] }}" data-url="{{ $block['contentUrl'] }}">
@include('x-103::site.partials.video-player', ['video' => $block])
      </div>
    </div>
    <p>{{ $block['name'] }}</p>
  </div>
</div>
