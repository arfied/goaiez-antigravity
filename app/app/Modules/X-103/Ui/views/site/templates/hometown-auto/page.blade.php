{{-- Hometown Auto — family garages, tyre shops, service stations, small-town mechanics: white and royal blue, the words on a card over the photo, a checklist of services. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $hero = $b['hero'] ?? null;
    $heroImg = $hero ? $img($hero['image_path'] ?? null) : null;
    $heroHref = $hero && $txt($hero['cta_label'] ?? null) ? $link($hero['cta_url'] ?? null) : null;
    $stats = array_values(array_filter((array) ($b['stats']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['value'] ?? null)));
    $about = isset($b['about']) && $txt($b['about']['text'] ?? null) ? $b['about'] : null;
    $services = array_values(array_filter((array) ($b['services']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['name'] ?? null)));
    $reviews = array_values(array_filter((array) ($b['reviews_strip']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['text'] ?? null)));
    $faqs = array_values(array_filter((array) ($b['faq']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['question'] ?? null) && $txt($i['answer'] ?? null)));
    $gallery = array_values(array_filter((array) ($b['gallery']['items'] ?? []), fn ($i) => is_array($i) && $img($i['image_path'] ?? null)));
    $booking = $b['booking_button'] ?? null;
    $bookHref = $booking && $txt($booking['label'] ?? null) ? $link($booking['url'] ?? null) : null;
    $book = $bookHref ?? $heroHref ?? $tel;
    $bookLabel = $bookHref !== null ? $booking['label'] : ($heroHref !== null ? $hero['cta_label'] : ($phone ? 'Call '.$phone : null));
    $cta = isset($b['cta_band']) && $txt($b['cta_band']['heading'] ?? null) ? $b['cta_band'] : null;
    $trust = array_values(array_filter([$txt($facts['insurance'] ?? null), $txt($facts['licence_number'] ?? null), $txt($facts['service_area'] ?? null) ? 'Serving '.$facts['service_area'] : null]));
    $openHours = array_values(array_filter($hours, fn ($h) => is_array($h) && $txt($h['day'] ?? null)));
    $shown = count($gallery) >= 3 ? array_slice($gallery, 0, min(6, intdiv(count($gallery), 3) * 3)) : $gallery;
    $nav = array_filter(['services' => $services ? 'Services' : null, 'about' => $about ? 'About us' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Hours & directions']);
@endphp
<div class="hm" id="top">
<header class="hm-nav">
    <div class="hm-wrap hm-nav__row">
        <a class="hm-brand" href="#top">{{ $name }}</a>
        <nav class="hm-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($phone)<a class="hm-nav__call" href="{{ $tel }}"><span>Call</span> {{ $phone }}</a>@endif
        <details class="hm-menu">
            <summary aria-label="Menu"><span></span><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="hm-hero{{ $heroImg ? '' : ' hm-hero--plain' }}"{!! $at('hero') !!}>
    @if($heroImg)<img class="hm-hero__img" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">@endif
    <div class="hm-wrap">
        <div class="hm-hero__card">
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p class="hm-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="hm-actions">
                @if($heroHref)<a class="hm-button hm-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
                @if($phone)<a class="hm-button hm-button--line hm-button--lg" href="{{ $tel }}">{{ $phone }}</a>@endif
            </div>
        </div>
    </div>
</section>
@endif

@if($trust)
<div class="hm-trust"><ul class="hm-wrap hm-trust__row">@foreach($trust as $t)<li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>{{ $t }}</li>@endforeach</ul></div>
@endif

@if($stats)
<section class="hm-section hm-section--tight"{!! $at('stats') !!}>
    <ul class="hm-wrap hm-stats">@foreach(array_slice($stats, 0, 4) as $s)<li><strong>{{ $s['value'] }}</strong>@if($txt($s['label'] ?? null))<span>{{ $s['label'] }}</span>@endif</li>@endforeach</ul>
</section>
@endif

@if($services)
<section id="services" class="hm-section hm-section--card"{!! $at('services') !!}>
    <div class="hm-wrap">
        <div class="hm-head">
            <p class="hm-label">What we fix</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="hm-checklist">
            @foreach($services as $s)
            <li>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>
                <div class="hm-checklist__body">
                    <div class="hm-checklist__top"><h3>{{ $s['name'] }}</h3>@if($txt($s['price_text'] ?? null))<span class="hm-price">{{ $s['price_text'] }}</span>@endif</div>
                    @if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif
                </div>
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($reviews)
@php $lead = $reviews[0]; $rest = array_slice($reviews, 1, 2); @endphp
<section id="reviews" class="hm-section hm-section--blue"{!! $at('reviews_strip') !!}>
    <div class="hm-wrap">
        <div class="hm-head hm-head--center">
            <p class="hm-label hm-label--light">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="hm-reviews">
            @foreach(array_merge([$lead], $rest) as $ri => $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <figure class="{{ $ri === 0 ? 'hm-review hm-review--lead' : 'hm-review' }}">
                @if($stars > 0)<p class="hm-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <blockquote><p>“{{ $r['text'] }}”</p></blockquote>
                @if($txt($r['author'] ?? null))<figcaption>{{ $r['author'] }}@if($txt($r['source'] ?? null)) · {{ $r['source'] }}@endif</figcaption>@endif
            </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="hm-section hm-section--tight"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="hm-wrap">
        <div class="hm-band">
            <div>
                @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Bring it in</h2>@endif
                @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
            </div>
            @if($book)<a class="hm-button hm-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
        </div>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="hm-section"{!! $at('about') !!}>
    <div class="hm-wrap hm-about{{ $aboutImg ? '' : ' hm-about--text' }}">
        @if($aboutImg)<img class="hm-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
        <div class="hm-about__body">
            <p class="hm-label">About us</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
            @if($txt($facts['years_in_business'] ?? null))<p class="hm-since">{{ $facts['years_in_business'] }} years in business</p>@endif
        </div>
    </div>
</section>
@endif

@if($shown)
<section class="hm-section hm-section--tight"{!! $at('gallery') !!} aria-label="Photos">
    <div class="hm-wrap">
        @if($txt($b['gallery']['heading'] ?? null))<h2 class="hm-gallery__head"{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        <div class="hm-gallery">@foreach($shown as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="hm-section"{!! $at('faq') !!}>
    <div class="hm-wrap hm-narrow">
        <div class="hm-head">
            <p class="hm-label">Questions</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="hm-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="hm-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

<section id="visit" class="hm-section hm-section--card"{!! $at('contact') !!}>
    <div class="hm-wrap hm-visit">
        <div class="hm-visit__where">
            <p class="hm-label">Hours & directions</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p class="hm-visit__phone"><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($openHours)
        <table class="hm-hours">
            @foreach($openHours as $h)
            <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
            @endforeach
        </table>
        @endif
    </div>
</section>
</div>
