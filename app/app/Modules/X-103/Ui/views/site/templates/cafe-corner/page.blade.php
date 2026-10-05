{{-- Café Corner — cafés, bakeries, breakfast and lunch spots, food trucks: warm and light, today's hours up front, a menu of cards. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $hero = $b['hero'] ?? null;
    $h1 = ($hero ? $txt($hero['headline'] ?? null) : null) ?? ($name !== '' ? $name : null);
    $heroImg = $hero ? $img($hero['image_path'] ?? null) : null;
    $heroHref = $hero && $txt($hero['cta_label'] ?? null) ? $link($hero['cta_url'] ?? null) : null;
    $about = isset($b['about']) && $txt($b['about']['text'] ?? null) ? $b['about'] : null;
    $menu = array_values(array_filter((array) ($b['services']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['name'] ?? null)));
    $reviews = array_values(array_filter((array) ($b['reviews_strip']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['text'] ?? null)));
    $faqs = array_values(array_filter((array) ($b['faq']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['question'] ?? null) && $txt($i['answer'] ?? null)));
    $gallery = array_values(array_filter((array) ($b['gallery']['items'] ?? []), fn ($i) => is_array($i) && $img($i['image_path'] ?? null)));
    $booking = $b['booking_button'] ?? null;
    $bookHref = $booking && $txt($booking['label'] ?? null) ? $link($booking['url'] ?? null) : null;
    $cta = isset($b['cta_band']) && $txt($b['cta_band']['heading'] ?? null) ? $b['cta_band'] : null;
    $ctaHref = $cta && $txt($cta['label'] ?? null) ? $link($cta['url'] ?? null) : null;
    $openHours = array_values(array_filter($hours, fn ($h) => is_array($h) && $txt($h['day'] ?? null)));
    $shown = count($gallery) >= 3 ? array_slice($gallery, 0, min(6, intdiv(count($gallery), 3) * 3)) : $gallery;
    $nav = array_filter(['menu' => $menu ? 'Menu' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Find us']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
    $navLinks = $pages !== [] ? $pages : array_map(static fn (string $id, string $label): array => ['label' => $label, 'href' => '#'.$id, 'current' => false], array_keys($nav), array_values($nav));
@endphp
<div class="cc" id="top">
<header class="cc-nav">
    <div class="cc-wrap cc-nav__row">
        <a class="cc-brand" href="#top"><span class="cc-brand__mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($name !== '' ? $name : 'C', 0, 1)) }}</span><span>{{ $name }}</span></a>
        <nav class="cc-nav__links" aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        @if($phone)<a class="cc-button cc-button--soft cc-nav__call" href="{{ $tel }}">{{ $phone }}</a>@endif
        <details class="cc-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if(! $hero && $h1 !== null)<h1 style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0">{{ $h1 }}</h1>@endif
@if($hero)
<section class="cc-hero"{!! $at('hero') !!}>
    <div class="cc-wrap cc-hero__grid{{ $heroImg ? '' : ' cc-hero__grid--text' }}">
        <div class="cc-hero__body">
            @if($h1 !== null)<h1{!! $f('headline') !!}>{{ $h1 }}</h1>@endif
            @if($txt($hero['subline'] ?? null))<p class="cc-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="cc-actions">
                @if($heroHref)<a class="cc-button cc-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
                @if($menu)<a class="cc-button cc-button--soft cc-button--lg" href="#menu">See the menu</a>@endif
            </div>
            @if($openHours || $address)
            <div class="cc-today">
                @if($openHours)
                <ul class="cc-today__hours">
                    @foreach($openHours as $h)
                    <li><span>{{ $h['day'] }}</span><span>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</span></li>
                    @endforeach
                </ul>
                @endif
                @if($address)<p class="cc-today__where">{{ $address }}</p>@endif
            </div>
            @endif
        </div>
        @if($heroImg)<div class="cc-hero__media"><img src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}"></div>@endif
    </div>
</section>
@endif

@if($menu)
<section id="menu" class="cc-section"{!! $at('services') !!}>
    <div class="cc-wrap">
        <div class="cc-head">
            <p class="cc-kicker">Menu</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="cc-menucards{{ count($menu) % 3 !== 0 && count($menu) % 2 === 0 ? ' cc-menucards--two' : '' }}">
            @foreach($menu as $m)
            <li>
                <div class="cc-menucards__top"><h3>{{ $m['name'] }}</h3>@if($txt($m['price_text'] ?? null))<span class="cc-price">{{ $m['price_text'] }}</span>@endif</div>
                @if($txt($m['description'] ?? null))<p>{{ $m['description'] }}</p>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($shown)
<section class="cc-section cc-section--tight"{!! $at('gallery') !!} aria-label="Photos">
    <div class="cc-wrap">
        @if($txt($b['gallery']['heading'] ?? null))<h2 class="cc-gallery__head"{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        <div class="cc-gallery">@foreach($shown as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="cc-section"{!! $at('reviews_strip') !!}>
    <div class="cc-wrap">
        <div class="cc-head">
            <p class="cc-kicker">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="cc-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <figure>
                @if($stars > 0)<p class="cc-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <blockquote><p>{{ $r['text'] }}</p></blockquote>
                @if($txt($r['author'] ?? null))<figcaption>{{ $r['author'] }}@if($txt($r['source'] ?? null)) <span>· {{ $r['source'] }}</span>@endif</figcaption>@endif
            </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($cta || $bookHref)
<section class="cc-band"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="cc-wrap cc-band__inner">
        <div>
            @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Come and see us</h2>@endif
            @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        </div>
        <div class="cc-actions">
            @if($bookHref)<a class="cc-button cc-button--light cc-button--lg" href="{{ $bookHref }}"{!! $at('booking_button') !!}>{{ $booking['label'] }}</a>@endif
            @if($ctaHref && $ctaHref !== $bookHref)<a class="cc-button cc-button--outline cc-button--lg" href="{{ $ctaHref }}"{!! $f('label') !!}>{{ $cta['label'] }}</a>@endif
        </div>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="cc-section cc-section--tint"{!! $at('about') !!}>
    <div class="cc-wrap cc-about{{ $aboutImg ? '' : ' cc-about--text' }}">
        <div class="cc-about__body">
            <p class="cc-kicker">About us</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
        @if($aboutImg)<img class="cc-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
    </div>
</section>
@endif

@if($videos)
<section id="video" class="cc-section"{!! (count($videos) === 1 ? $videos[0]['at'] : '') !!}>
    <div class="cc-wrap cc-narrow">
        <div class="cc-head">
            <p class="cc-kicker">Video</p>
            <h2{!! count($videos) === 1 ? $f('name') : '' !!}>{{ count($videos) === 1 ? $videos[0]['name'] : 'Videos' }}</h2>
        </div>
        <div id="videos-x176" class="cc-videos" style="display:grid;gap:1.5rem">
            @foreach($videos as $video)
            <div class="video-item" data-name="{{ $video['name'] }}" data-url="{{ $video['contentUrl'] }}"{!! count($videos) > 1 ? $video['at'] : '' !!}>
@include('x-103::site.partials.video-player', ['video' => $video])
                @if(count($videos) > 1)<p>{{ $video['name'] }}</p>@endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="cc-section"{!! $at('faq') !!}>
    <div class="cc-wrap cc-narrow">
        <div class="cc-head">
            <p class="cc-kicker">Questions</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="cc-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="cc-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

@if($form)
<section id="message" class="cc-section"{!! $at('form') !!}>
    <div class="cc-wrap">
        <div class="cc-head">
            <p class="cc-kicker">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="cc-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="cc-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="cc-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="cc-button cc-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="visit" class="cc-section cc-section--tint"{!! $at('contact') !!}>
    <div class="cc-wrap cc-visit">
        <div class="cc-card">
            <p class="cc-kicker">Find us</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($openHours)
        <div class="cc-card">
            <p class="cc-kicker">Opening hours</p>
            <table class="cc-hours">
                @foreach($openHours as $h)
                <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
                @endforeach
            </table>
        </div>
        @endif
    </div>
</section>
</div>
