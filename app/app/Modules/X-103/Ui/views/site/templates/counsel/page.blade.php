{{-- Counsel — accountants, law firms, financial advisers, consultants: ivory and navy, a brass rule, credentials up front. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $hero = $b['hero'] ?? null;
    $heroImg = $hero ? $img($hero['image_path'] ?? null) : null;
    $heroHref = $hero && $txt($hero['cta_label'] ?? null) ? $link($hero['cta_url'] ?? null) : null;
    $stats = array_values(array_filter((array) ($b['stats']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['value'] ?? null)));
    $about = isset($b['about']) && $txt($b['about']['text'] ?? null) ? $b['about'] : null;
    $services = array_values(array_filter((array) ($b['services']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['name'] ?? null)));
    $reviews = array_values(array_filter((array) ($b['reviews_strip']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['text'] ?? null)));
    $faqs = array_values(array_filter((array) ($b['faq']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['question'] ?? null) && $txt($i['answer'] ?? null)));
    $booking = $b['booking_button'] ?? null;
    $bookHref = $booking && $txt($booking['label'] ?? null) ? $link($booking['url'] ?? null) : null;
    $book = $bookHref ?? $heroHref ?? $tel;
    $bookLabel = $bookHref !== null ? $booking['label'] : ($heroHref !== null ? $hero['cta_label'] : ($phone ? 'Call '.$phone : null));
    $cta = isset($b['cta_band']) && $txt($b['cta_band']['heading'] ?? null) ? $b['cta_band'] : null;
    $creds = array_values(array_filter([$txt($facts['licence_number'] ?? null), $txt($facts['insurance'] ?? null), $txt($facts['years_in_business'] ?? null) ? 'Established '.$facts['years_in_business'].' years' : null]));
    $openHours = array_values(array_filter($hours, fn ($h) => is_array($h) && $txt($h['day'] ?? null)));
    $nav = array_filter(['services' => $services ? 'Services' : null, 'firm' => $about ? 'The firm' : null, 'clients' => $reviews ? 'Clients' : null, 'contact' => 'Contact']);
@endphp
<div class="cn">
<header class="cn-nav">
    <div class="cn-wrap cn-nav__row">
        <a class="cn-brand" href="#top">{{ $name }}</a>
        <nav class="cn-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($phone)<a class="cn-nav__call" href="{{ $tel }}">{{ $phone }}</a>@endif
        <details class="cn-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section id="top" class="cn-hero"{!! $at('hero') !!}>
    <div class="cn-wrap cn-hero__grid{{ $heroImg ? '' : ' cn-hero__grid--text' }}">
        <div class="cn-hero__body">
            <span class="cn-rule" aria-hidden="true"></span>
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p class="cn-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="cn-actions">
                @if($heroHref)<a class="cn-button cn-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
                @if($services)<a class="cn-link" href="#services">Our services →</a>@endif
            </div>
        </div>
        @if($heroImg)<figure class="cn-hero__media"><img src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}"></figure>@endif
    </div>
    @if($creds)<div class="cn-wrap"><ul class="cn-creds">@foreach($creds as $c)<li>{{ $c }}</li>@endforeach</ul></div>@endif
</section>
@endif

@if($stats)
<section class="cn-stats"{!! $at('stats') !!}>
    <ul class="cn-wrap cn-stats__row">@foreach(array_slice($stats, 0, 4) as $s)<li><strong>{{ $s['value'] }}</strong>@if($txt($s['label'] ?? null))<span>{{ $s['label'] }}</span>@endif</li>@endforeach</ul>
</section>
@endif

@if($services)
<section id="services" class="cn-section"{!! $at('services') !!}>
    <div class="cn-wrap cn-split">
        <div class="cn-split__head">
            <p class="cn-label">Services</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="cn-services">
            @foreach($services as $s)
            <li>
                <div class="cn-services__top"><h3>{{ $s['name'] }}</h3>@if($txt($s['price_text'] ?? null))<span class="cn-fee">{{ $s['price_text'] }}</span>@endif</div>
                @if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="firm" class="cn-section cn-section--card"{!! $at('about') !!}>
    <div class="cn-wrap cn-about{{ $aboutImg ? '' : ' cn-about--text' }}">
        <div class="cn-about__body">
            <p class="cn-label">The firm</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
            @if($txt($facts['service_area'] ?? null))<p class="cn-area">Serving {{ $facts['service_area'] }}</p>@endif
        </div>
        @if($aboutImg)<img class="cn-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
    </div>
</section>
@endif

@if($reviews)
<section id="clients" class="cn-section"{!! $at('reviews_strip') !!}>
    <div class="cn-wrap cn-split">
        <div class="cn-split__head">
            <p class="cn-label">Clients</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="cn-quotes">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <figure>
                <blockquote><p>“{{ $r['text'] }}”</p></blockquote>
                <figcaption>@if($txt($r['author'] ?? null)){{ $r['author'] }}@endif @if($stars > 0)<span class="cn-stars" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</span>@endif @if($txt($r['source'] ?? null))<span class="cn-source">{{ $r['source'] }}</span>@endif</figcaption>
            </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="cn-band"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="cn-wrap cn-band__inner">
        <div>
            @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Talk to us</h2>@endif
            @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        </div>
        @if($book)<a class="cn-button cn-button--light cn-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="cn-section"{!! $at('faq') !!}>
    <div class="cn-wrap cn-split">
        <div class="cn-split__head">
            <p class="cn-label">Questions</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="cn-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="cn-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

<section id="contact" class="cn-section cn-section--card"{!! $at('contact') !!}>
    <div class="cn-wrap cn-contact">
        <div class="cn-contact__where">
            <p class="cn-label">Contact</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
        </div>
        <div class="cn-contact__lines">
            @if($phone)<p><span>Telephone</span><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><span>Email</span><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
            @if($openHours)
            <table class="cn-hours">
                @foreach($openHours as $h)
                <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
                @endforeach
            </table>
            @endif
        </div>
    </div>
</section>
</div>
