{{-- Nail Studio — nail, lash and brow studios: black and white, big type, a square photo grid. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $hero = $b['hero'] ?? null;
    $heroImg = $hero ? $img($hero['image_path'] ?? null) : null;
    $heroHref = $hero && $txt($hero['cta_label'] ?? null) ? $link($hero['cta_url'] ?? null) : null;
    $about = isset($b['about']) && $txt($b['about']['text'] ?? null) ? $b['about'] : null;
    $services = array_values(array_filter((array) ($b['services']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['name'] ?? null)));
    $reviews = array_values(array_filter((array) ($b['reviews_strip']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['text'] ?? null)));
    $team = array_values(array_filter((array) ($b['team']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['name'] ?? null) && ! str_starts_with((string) $i['name'], 'demo·')));
    $faqs = array_values(array_filter((array) ($b['faq']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['question'] ?? null) && $txt($i['answer'] ?? null)));
    $gallery = array_values(array_filter((array) ($b['gallery']['items'] ?? []), fn ($i) => is_array($i) && $img($i['image_path'] ?? null)));
    $booking = $b['booking_button'] ?? null;
    $bookHref = $booking && $txt($booking['label'] ?? null) ? $link($booking['url'] ?? null) : null;
    $book = $bookHref ?? $heroHref ?? $tel;
    $bookLabel = $bookHref !== null ? $booking['label'] : ($heroHref !== null ? $hero['cta_label'] : ($phone ? 'Call '.$phone : null));
    $cta = isset($b['cta_band']) && $txt($b['cta_band']['heading'] ?? null) ? $b['cta_band'] : null;
    // A full grid: a multiple of three photos, at most nine.
    $grid = count($gallery) >= 3 ? array_slice($gallery, 0, min(9, intdiv(count($gallery), 3) * 3)) : $gallery;
    $nav = array_filter(['prices' => $services ? 'Prices' : null, 'work' => $gallery ? 'Work' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Visit']);
@endphp
<div class="ns">
<header class="ns-nav">
    <div class="ns-wrap ns-nav__row">
        <a class="ns-brand" href="#top">{{ $name }}</a>
        <nav class="ns-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($book)<a class="ns-button ns-nav__book" href="{{ $book }}">Book</a>@endif
        <details class="ns-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section id="top" class="ns-hero"{!! $at('hero') !!}>
    <div class="ns-wrap">
        <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
        <div class="ns-hero__row">
            @if($txt($hero['subline'] ?? null))<p class="ns-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            @if($heroHref)<a class="ns-button ns-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@elseif($book)<a class="ns-button ns-button--lg" href="{{ $book }}">{{ $bookLabel }}</a>@endif
        </div>
    </div>
    @if($heroImg)<img class="ns-hero__img" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">@endif
</section>
@endif

@if($services)
<section id="prices" class="ns-section"{!! $at('services') !!}>
    <div class="ns-wrap ns-prices">
        <div class="ns-head">
            <p class="ns-label">Prices</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <table class="ns-table">
            @foreach($services as $s)
            <tr>
                <th scope="row">{{ $s['name'] }}@if($txt($s['description'] ?? null))<span>{{ $s['description'] }}</span>@endif</th>
                <td>{{ $txt($s['price_text'] ?? null) ?? '' }}</td>
            </tr>
            @endforeach
        </table>
    </div>
</section>
@endif

@if($grid)
<section id="work" class="ns-section ns-section--tight"{!! $at('gallery') !!}>
    <div class="ns-wrap">
        <div class="ns-head ns-head--row">
            <p class="ns-label">Work</p>
            @if($txt($b['gallery']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        </div>
    </div>
    <div class="ns-grid">@foreach($grid as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
</section>
@endif

@if($reviews)
<section id="reviews" class="ns-section ns-section--ink"{!! $at('reviews_strip') !!}>
    <div class="ns-wrap">
        <div class="ns-head">
            <p class="ns-label">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="ns-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <blockquote>
                @if($stars > 0)<p class="ns-stars" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <p>{{ $r['text'] }}</p>
                @if($txt($r['author'] ?? null))<cite>— {{ $r['author'] }}@if($txt($r['source'] ?? null)), {{ $r['source'] }}@endif</cite>@endif
            </blockquote>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="ns-book"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="ns-wrap ns-book__row">
        <div>
            @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Book your next set</h2>@endif
            @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        </div>
        @if($book)<a class="ns-button ns-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
    </div>
</section>
@endif

@if($team)
<section id="team" class="ns-section"{!! $at('team') !!}>
    <div class="ns-wrap">
        <div class="ns-head">
            <p class="ns-label">Our team</p>
            @if($txt($b['team']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['team']['heading'] }}</h2>@endif
        </div>
        <ul class="ns-team">
            @foreach($team as $m)
            <li><span class="ns-team__face" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $m['name']), 0, 1)) }}</span><div><h3>{{ $m['name'] }}</h3>@if($txt($m['role'] ?? null))<p class="ns-team__role">{{ $m['role'] }}</p>@endif@if($txt($m['description'] ?? null))<p>{{ $m['description'] }}</p>@endif</div></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="ns-section"{!! $at('about') !!}>
    <div class="ns-wrap ns-about{{ $aboutImg ? '' : ' ns-about--text' }}">
        @if($aboutImg)<img class="ns-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
        <div class="ns-about__body">
            <p class="ns-label">About</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="ns-section"{!! $at('faq') !!}>
    <div class="ns-wrap ns-faq">
        <div class="ns-head">
            <p class="ns-label">Questions</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="ns-faq__list">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="ns-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

<section id="visit" class="ns-section ns-visit"{!! $at('contact') !!}>
    <div class="ns-wrap ns-visit__grid">
        <div>
            <p class="ns-label">Visit</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
        </div>
        <div class="ns-visit__details">
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($hours)
        <table class="ns-hours">
            @foreach($hours as $h)
            @if(is_array($h) && $txt($h['day'] ?? null))
            <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
            @endif
            @endforeach
        </table>
        @endif
    </div>
</section>
</div>
