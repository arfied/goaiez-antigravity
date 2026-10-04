{{-- Clear Office — insurance agencies, real estate offices, consultants, IT and marketing firms: white and deep teal, a soft panel hero, service cards. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $hero = $b['hero'] ?? null;
    $heroImg = $hero ? $img($hero['image_path'] ?? null) : null;
    $heroHref = $hero && $txt($hero['cta_label'] ?? null) ? $link($hero['cta_url'] ?? null) : null;
    $stats = array_values(array_filter((array) ($b['stats']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['value'] ?? null)));
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
    $openHours = array_values(array_filter($hours, fn ($h) => is_array($h) && $txt($h['day'] ?? null)));
    $shown = count($gallery) >= 3 ? array_slice($gallery, 0, min(6, intdiv(count($gallery), 3) * 3)) : $gallery;
    $nav = array_filter(['services' => $services ? 'Services' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Reviews' : null, 'faq' => $faqs ? 'FAQ' : null, 'contact' => 'Contact']);
@endphp
<div class="co">
<header class="co-nav">
    <div class="co-wrap co-nav__row">
        <a class="co-brand" href="#top"><span class="co-brand__dot" aria-hidden="true"></span>{{ $name }}</a>
        <nav class="co-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($book)<a class="co-button co-nav__book" href="{{ $book }}">{{ $bookHref !== null ? 'Get in touch' : $bookLabel }}</a>@endif
        <details class="co-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section id="top" class="co-hero"{!! $at('hero') !!}>
    <div class="co-wrap">
        <div class="co-panel{{ $heroImg ? '' : ' co-panel--text' }}">
            <div class="co-panel__body">
                <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
                @if($txt($hero['subline'] ?? null))<p class="co-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
                <div class="co-actions">
                    @if($heroHref)<a class="co-button co-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
                    @if($phone)<a class="co-button co-button--plain co-button--lg" href="{{ $tel }}">{{ $phone }}</a>@endif
                </div>
            </div>
            @if($heroImg)<img class="co-panel__img" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">@endif
        </div>
    </div>
</section>
@endif

@if($stats)
<section class="co-stats"{!! $at('stats') !!}>
    <ul class="co-wrap co-stats__row">@foreach(array_slice($stats, 0, 4) as $s)<li><strong>{{ $s['value'] }}</strong>@if($txt($s['label'] ?? null))<span>{{ $s['label'] }}</span>@endif</li>@endforeach</ul>
</section>
@endif

@if($reviews)
<section id="reviews" class="co-section"{!! $at('reviews_strip') !!}>
    <div class="co-wrap">
        <div class="co-head">
            <p class="co-label">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="co-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <figure>
                @if($stars > 0)<p class="co-stars" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <blockquote><p>{{ $r['text'] }}</p></blockquote>
                @if($txt($r['author'] ?? null))<figcaption><span class="co-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $r['author']), 0, 1)) }}</span>{{ $r['author'] }}@if($txt($r['source'] ?? null)) <span class="co-source">· {{ $r['source'] }}</span>@endif</figcaption>@endif
            </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($services)
<section id="services" class="co-section"{!! $at('services') !!}>
    <div class="co-wrap">
        <div class="co-head">
            <p class="co-label">Services</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="co-cards{{ count($services) % 3 !== 0 && count($services) % 2 === 0 ? ' co-cards--two' : '' }}">
            @foreach($services as $s)
            <li>
                <span class="co-cards__mark" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $s['name']), 0, 1)) }}</span>
                <h3>{{ $s['name'] }}</h3>
                @if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif
                @if($txt($s['price_text'] ?? null))<span class="co-price">{{ $s['price_text'] }}</span>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($book || $cta)
<section class="co-section co-section--tight"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="co-wrap">
        <div class="co-band">
            <div>
                @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Let's talk</h2>@endif
                @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
            </div>
            @if($book)<a class="co-button co-button--white co-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
        </div>
    </div>
</section>
@endif

@if($team)
<section id="team" class="co-section"{!! $at('team') !!}>
    <div class="co-wrap">
        <div class="co-head">
            <p class="co-label">Our team</p>
            @if($txt($b['team']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['team']['heading'] }}</h2>@endif
        </div>
        <ul class="co-team">
            @foreach($team as $m)
            <li><span class="co-team__face" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $m['name']), 0, 1)) }}</span><div><h3>{{ $m['name'] }}</h3>@if($txt($m['role'] ?? null))<p class="co-team__role">{{ $m['role'] }}</p>@endif@if($txt($m['description'] ?? null))<p>{{ $m['description'] }}</p>@endif</div></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="co-section co-section--card"{!! $at('about') !!}>
    <div class="co-wrap co-about{{ $aboutImg ? '' : ' co-about--text' }}">
        @if($aboutImg)<img class="co-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
        <div class="co-about__body">
            <p class="co-label">About</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
            @php $creds = array_values(array_filter([$txt($facts['licence_number'] ?? null), $txt($facts['insurance'] ?? null), $txt($facts['service_area'] ?? null) ? 'Serving '.$facts['service_area'] : null])); @endphp
            @if($creds)<ul class="co-creds">@foreach($creds as $c)<li>{{ $c }}</li>@endforeach</ul>@endif
        </div>
    </div>
</section>
@endif

@if($shown)
<section class="co-section co-section--tight"{!! $at('gallery') !!} aria-label="Photos">
    <div class="co-wrap">
        @if($txt($b['gallery']['heading'] ?? null))<h2 class="co-gallery__head"{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        <div class="co-gallery">@foreach($shown as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="co-section"{!! $at('faq') !!}>
    <div class="co-wrap co-faqwrap">
        <div class="co-head">
            <p class="co-label">FAQ</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="co-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="co-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

<section id="contact" class="co-section co-section--card"{!! $at('contact') !!}>
    <div class="co-wrap co-contact">
        <div class="co-contact__where">
            <p class="co-label">Contact</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
        </div>
        <div class="co-contact__card">
            @if($phone)<p><span>Phone</span><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><span>Email</span><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
            @if($openHours)
            <table class="co-hours">
                @foreach($openHours as $h)
                <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
                @endforeach
            </table>
            @endif
        </div>
    </div>
</section>
</div>
