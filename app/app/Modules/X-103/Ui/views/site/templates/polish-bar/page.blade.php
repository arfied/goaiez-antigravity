{{-- Polish Bar — nail salons, lash and brow bars. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $hero = $b['hero'] ?? null;
    $heroImg = $hero ? $img($hero['image_path'] ?? null) : null;
    $about = isset($b['about']) && $txt($b['about']['text'] ?? null) ? $b['about'] : null;
    $services = array_values(array_filter((array) ($b['services']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['name'] ?? null)));
    $reviews = array_values(array_filter((array) ($b['reviews_strip']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['text'] ?? null)));
    $faqs = array_values(array_filter((array) ($b['faq']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['question'] ?? null) && $txt($i['answer'] ?? null)));
    $gallery = array_values(array_filter((array) ($b['gallery']['items'] ?? []), fn ($i) => is_array($i) && $img($i['image_path'] ?? null)));
    $booking = $b['booking_button'] ?? null;
    $bookHref = $booking && $txt($booking['label'] ?? null) ? $link($booking['url'] ?? null) : null;
    $heroHref = $hero && $txt($hero['cta_label'] ?? null) ? $link($hero['cta_url'] ?? null) : null;
    $book = $bookHref ?? $heroHref ?? $tel;
    $bookLabel = $bookHref !== null ? $booking['label'] : ($heroHref !== null ? $hero['cta_label'] : ($phone ? 'Call '.$phone : null));
    $cta = isset($b['cta_band']) && $txt($b['cta_band']['heading'] ?? null) ? $b['cta_band'] : null;
    // The two pictures beside the banner photo come from the gallery, so the top of the page shows the work itself.
    $collage = array_slice($gallery, 0, 2);
    $nav = array_filter(['menu' => $services ? 'Menu' : null, 'work' => $gallery ? 'Our work' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Visit']);
@endphp
<div class="pb">
<header class="pb-nav">
    <div class="pb-wrap pb-nav__row">
        <a class="pb-brand" href="#top">{{ $name }}<span aria-hidden="true">.</span></a>
        <nav class="pb-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($book)<a class="pb-button pb-button--dark pb-nav__book" href="{{ $book }}">Book</a>@endif
        <details class="pb-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section id="top" class="pb-hero{{ $heroImg ? '' : ' pb-hero--text' }}"{!! $at('hero') !!}>
    <div class="pb-wrap pb-hero__grid">
        <div class="pb-hero__body">
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p class="pb-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="pb-actions">
                @if($heroHref)<a class="pb-button pb-button--pop" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@elseif($book)<a class="pb-button pb-button--pop" href="{{ $book }}">{{ $bookLabel }}</a>@endif
                @if($services)<a class="pb-button pb-button--outline" href="#menu">See prices</a>@endif
            </div>
            @if($phone)<p class="pb-hero__phone">Questions? Call <a href="{{ $tel }}">{{ $phone }}</a></p>@endif
        </div>
        @if($heroImg)
        <div class="pb-collage pb-collage--{{ count($collage) }}">
            <img class="pb-collage__main" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">
            @foreach($collage as $c)<img class="pb-collage__tile" src="{{ $img($c['image_path']) }}" alt="{{ $txt($c['alt'] ?? null) ?? '' }}">@endforeach
        </div>
        @endif
    </div>
</section>
@endif

@if($services)
<div class="pb-ribbon" aria-hidden="true"><p>@foreach(array_slice($services, 0, 6) as $s)<span>{{ $s['name'] }}</span>@endforeach</p></div>
<section id="menu" class="pb-section"{!! $at('services') !!}>
    <div class="pb-wrap">
        <div class="pb-head">
            <p class="pb-tag">The menu</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="pb-prices">
            @foreach($services as $s)
            <li>
                <div class="pb-prices__top"><h3>{{ $s['name'] }}</h3>@if($txt($s['price_text'] ?? null))<span class="pb-prices__price">{{ $s['price_text'] }}</span>@endif</div>
                @if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($gallery)
<section id="work" class="pb-section pb-section--blush"{!! $at('gallery') !!}>
    <div class="pb-wrap">
        <div class="pb-head">
            <p class="pb-tag">Fresh sets</p>
            @if($txt($b['gallery']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        </div>
        @php $shown = count($gallery) >= 4 ? array_slice($gallery, 0, min(8, intdiv(count($gallery), 4) * 4)) : $gallery; @endphp
        <div class="pb-gallery">@foreach($shown as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="pb-section"{!! $at('about') !!}>
    <div class="pb-wrap pb-about{{ $aboutImg ? '' : ' pb-about--text' }}">
        <div class="pb-about__body">
            <p class="pb-tag">About us</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
        @if($aboutImg)<img class="pb-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="pb-section pb-section--blush"{!! $at('reviews_strip') !!}>
    <div class="pb-wrap">
        <div class="pb-head">
            <p class="pb-tag">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="pb-reviews">
            @foreach(array_slice($reviews, 0, 6) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <blockquote class="pb-review">
                @if($stars > 0)<p class="pb-stars" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <p>{{ $r['text'] }}</p>
                @if($txt($r['author'] ?? null))<cite>{{ $r['author'] }}@if($txt($r['source'] ?? null))<span>{{ $r['source'] }}</span>@endif</cite>@endif
            </blockquote>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="pb-book"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="pb-wrap pb-book__row">
        <div>
            @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Ready for fresh nails?</h2>@endif
            @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        </div>
        @if($book)<a class="pb-button pb-button--pop pb-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="pb-section"{!! $at('faq') !!}>
    <div class="pb-wrap pb-faq">
        <div class="pb-head pb-head--left">
            <p class="pb-tag">Questions</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="pb-faq__list">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="pb-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

<section id="visit" class="pb-section pb-visit"{!! $at('contact') !!}>
    <div class="pb-wrap pb-visit__grid">
        <div class="pb-visit__card">
            <p class="pb-tag">Visit</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p class="pb-visit__address">{{ $address }}</p>@endif
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($hours)
        <div class="pb-visit__card">
            <p class="pb-tag">Hours</p>
            <table class="pb-hours">
                @foreach($hours as $h)
                @if(is_array($h) && $txt($h['day'] ?? null))
                <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
                @endif
                @endforeach
            </table>
        </div>
        @endif
    </div>
</section>
</div>
