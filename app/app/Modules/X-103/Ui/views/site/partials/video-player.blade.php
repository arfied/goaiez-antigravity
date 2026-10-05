{{-- One video as its address allows (SiteBlockRenderer::videoPlayer): the service's own player, a video file, or a link. --}}
@php($player = \App\Modules\X103\Domain\SiteBlockRenderer::videoPlayer($video['contentUrl'] ?? null))
@if($player['kind'] === 'embed' && ! empty($preview))
<div style="display:grid;place-items:center;width:100%;aspect-ratio:16/9;padding:1rem;background:var(--color-card);color:var(--color-ink);text-align:center;border-radius:inherit"><span><strong><span aria-hidden="true">▶</span> {{ $video['name'] }}</strong><br>Plays on your live site</span></div>
@elseif($player['kind'] === 'embed')
<iframe src="{{ $player['src'] }}" title="{{ $video['name'] }}" loading="lazy" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" style="display:block;width:100%;height:auto;aspect-ratio:16/9;border:0;border-radius:inherit"></iframe>
@elseif($player['kind'] === 'file')
<video controls preload="metadata" src="{{ $player['src'] }}" style="display:block;width:100%;height:auto;border-radius:inherit"></video>
@elseif($player['kind'] === 'link')
<a href="{{ $player['src'] }}" rel="noopener" style="display:grid;place-items:center;width:100%;aspect-ratio:16/9;background:var(--color-card);color:var(--color-ink);font-weight:600;text-decoration:none;border-radius:inherit"><span><span aria-hidden="true">▶</span> Watch the video</span></a>
@endif
