{{-- Calm Spa — day spas, massage, facials, wellness studios. A frozen layout: the AI fills the blocks, never this markup. --}}
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
    // The one action this page is built around: the booking link, else the top banner's button, else a phone call.
    $book = $bookHref !== null ? ['href' => $bookHref, 'label' => $booking['label'], 'field' => 'label', 'at' => 'booking_button']
        : ($heroHref !== null ? ['href' => $heroHref, 'label' => $hero['cta_label'], 'field' => 'cta_label', 'at' => 'hero']
        : ($phone ? ['href' => $tel, 'label' => 'Call '.$phone, 'field' => null, 'at' => null] : null));
    $cta = isset($b['cta_band']) && $txt($b['cta_band']['heading'] ?? null) ? $b['cta_band'] : null;
    $first = $reviews[0] ?? null;
    $firstStars = $first && is_numeric($first['rating'] ?? null) ? max(0, min(5, (int) round((float) $first['rating']))) : 0;
    $nav = array_filter(['treatments' => $services ? 'Treatments' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Visit']);
    $leaf = '<svg class="cs-leaf" viewBox="0 0 40 12" aria-hidden="true"><path d="M2 6h13M25 6h13"/><path d="M20 1c3 2 3 8 0 10-3-2-3-8 0-10z"/></svg>';
@endphp
<div class="cs">
<header class="cs-nav">
    <div class="cs-wrap cs-nav__row">
        <a class="cs-brand" href="#top">{{ $name }}</a>
        <nav class="cs-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($book)<a class="cs-button cs-button--solid cs-nav__book" href="{{ $book['href'] }}">{{ $book['field'] === null ? 'Call us' : 'Book now' }}</a>@endif
        <details class="cs-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section id="top" class="cs-hero{{ $heroImg ? '' : ' cs-hero--text' }}"{!! $at('hero') !!}>
    <div class="cs-wrap cs-hero__grid">
        <div class="cs-hero__body">
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p class="cs-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="cs-actions">
                @if($heroHref)<a class="cs-button cs-button--solid" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@elseif($book)<a class="cs-button cs-button--solid" href="{{ $book['href'] }}">{{ $book['label'] }}</a>@endif
                @if($services)<a class="cs-button cs-button--line" href="#treatments">See treatments</a>@endif
            </div>
        </div>
        @if($heroImg)
        <figure class="cs-hero__media">
            <img src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">
            @if($first && $firstStars > 0)
            <figcaption class="cs-hero__quote"><span class="cs-stars" aria-label="{{ $firstStars }} out of 5 stars">{{ str_repeat('★', $firstStars) }}</span>“{{ \Illuminate\Support\Str::limit(trim((string) $first['text']), 90) }}”@if($txt($first['author'] ?? null))<cite>{{ $first['author'] }}</cite>@endif</figcaption>
            @endif
        </figure>
        @endif
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="cs-section cs-about{{ $aboutImg ? '' : ' cs-about--text' }}"{!! $at('about') !!}>
    <div class="cs-wrap cs-about__grid">
        @if($aboutImg)<img class="cs-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
        <div class="cs-about__card">
            {!! $leaf !!}
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
    </div>
</section>
@endif

@if($services)
<section id="treatments" class="cs-section cs-menu-section"{!! $at('services') !!}>
    <div class="cs-wrap">
        <div class="cs-head">
            <p class="cs-kicker">Treatments</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
            {!! $leaf !!}
        </div>
        <ul class="cs-list">
            @foreach($services as $s)
            <li>
                <p class="cs-list__row"><span class="cs-list__name">{{ $s['name'] }}</span>@if($txt($s['price_text'] ?? null))<span class="cs-list__dots" aria-hidden="true"></span><span class="cs-list__price">{{ $s['price_text'] }}</span>@endif</p>
                @if($txt($s['description'] ?? null))<p class="cs-list__desc">{{ $s['description'] }}</p>@endif
            </li>
            @endforeach
        </ul>
        @if($book)<p class="cs-center"><a class="cs-button cs-button--solid" href="{{ $book['href'] }}">{{ $book['field'] === null ? $book['label'] : 'Book a treatment' }}</a></p>@endif
    </div>
</section>
@endif

@if($gallery)
<section class="cs-gallery-section"{!! $at('gallery') !!}>
    <div class="cs-wrap">
        @if($txt($b['gallery']['heading'] ?? null))<h2 class="cs-center"{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        <div class="cs-gallery cs-gallery--{{ min(count($gallery), 5) }}">@foreach(array_slice($gallery, 0, 5) as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="cs-section cs-reviews"{!! $at('reviews_strip') !!}>
    <div class="cs-wrap">
        <div class="cs-head">
            <p class="cs-kicker">Kind words</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="cs-quotes">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <blockquote class="cs-quote">
                @if($stars > 0)<p class="cs-stars" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <p class="cs-quote__text">“{{ $r['text'] }}”</p>
                @if($txt($r['author'] ?? null))<cite>{{ $r['author'] }}@if($txt($r['source'] ?? null)) · {{ $r['source'] }}@endif</cite>@endif
            </blockquote>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="cs-book"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="cs-wrap cs-book__inner">
        {!! $leaf !!}
        @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Make time for yourself</h2>@endif
        @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        @if($book)<a class="cs-button cs-button--solid cs-button--lg" href="{{ $book['href'] }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $book['label'] }}</a>@endif
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="cs-section"{!! $at('faq') !!}>
    <div class="cs-wrap cs-faq">
        <div class="cs-head cs-head--left">
            <p class="cs-kicker">Before you visit</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="cs-faq__list">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="cs-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

<section id="visit" class="cs-section cs-visit"{!! $at('contact') !!}>
    <div class="cs-wrap">
        <div class="cs-head">
            <p class="cs-kicker">Visit</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
        </div>
        <div class="cs-visit__grid">
            @if($address)<div><h3>Find us</h3><p>{{ $address }}</p></div>@endif
            @if($hours)
            <div><h3>Opening hours</h3>
                <table class="cs-hours">
                    @foreach($hours as $h)
                    @if(is_array($h) && $txt($h['day'] ?? null))
                    <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
                    @endif
                    @endforeach
                </table>
            </div>
            @endif
            @if($phone || $email)
            <div><h3>Get in touch</h3>
                @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
                @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
            </div>
            @endif
        </div>
    </div>
</section>
</div>
