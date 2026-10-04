{{-- Spa Luxe — day spas, massage, med-spas, wellness: dark and gold, a full photograph and a quiet menu. A frozen layout: the AI fills the blocks, never this markup. --}}
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
    $quote = $reviews[0] ?? null;
    $nav = array_filter(['menu' => $services ? 'Menu' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Visit']);
@endphp
<div class="sl" id="top">
<header class="sl-nav">
    <div class="sl-wrap sl-nav__row">
        <a class="sl-brand" href="#top">{{ $name }}</a>
        <nav class="sl-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($book)<a class="sl-button sl-nav__book" href="{{ $book }}">{{ $bookHref !== null ? 'Book' : ($heroHref !== null ? $hero['cta_label'] : 'Call') }}</a>@endif
        <details class="sl-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="sl-hero{{ $heroImg ? '' : ' sl-hero--text' }}"{!! $at('hero') !!}>
    @if($heroImg)<img class="sl-hero__img" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">@endif
    <div class="sl-wrap sl-hero__body">
        <span class="sl-rule" aria-hidden="true"></span>
        <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
        @if($txt($hero['subline'] ?? null))<p{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
        @if($heroHref)<a class="sl-button sl-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@elseif($book)<a class="sl-button sl-button--lg" href="{{ $book }}">{{ $bookLabel }}</a>@endif
    </div>
</section>
@endif

@if($services)
<section id="menu" class="sl-section sl-section--card"{!! $at('services') !!}>
    <div class="sl-wrap">
        <div class="sl-head">
            <p class="sl-kicker">The menu</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="sl-list">
            @foreach($services as $s)
            <li>
                <div class="sl-list__row"><h3>{{ $s['name'] }}</h3>@if($txt($s['price_text'] ?? null))<span>{{ $s['price_text'] }}</span>@endif</div>
                @if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($gallery)
<section class="sl-gallery"{!! $at('gallery') !!} aria-label="Photos">
    @foreach(array_slice($gallery, 0, 4) as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach
</section>
@endif

@if($quote)
<section id="reviews" class="sl-section sl-quote"{!! $at('reviews_strip') !!}>
    <div class="sl-wrap sl-quote__inner">
        @if($txt($b['reviews_strip']['heading'] ?? null))<p class="sl-kicker"{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</p>@endif
        <blockquote>
            <p>“{{ $quote['text'] }}”</p>
            @if($txt($quote['author'] ?? null))<cite>{{ $quote['author'] }}@if($txt($quote['source'] ?? null)) · {{ $quote['source'] }}@endif</cite>@endif
        </blockquote>
        @if(count($reviews) > 1)
        <ul class="sl-quote__more">
            @foreach(array_slice($reviews, 1, 3) as $r)<li>“{{ \Illuminate\Support\Str::limit(trim((string) $r['text']), 140) }}”@if($txt($r['author'] ?? null))<span>{{ $r['author'] }}</span>@endif</li>@endforeach
        </ul>
        @endif
    </div>
</section>
@endif

@if($book || $cta)
<section class="sl-book"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="sl-wrap sl-book__inner">
        @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Reserve your time</h2>@endif
        @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        @if($book)<a class="sl-button sl-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
    </div>
</section>
@endif

@if($team)
<section id="team" class="sl-section"{!! $at('team') !!}>
    <div class="sl-wrap">
        <div class="sl-head">
            <p class="sl-kicker">Our team</p>
            @if($txt($b['team']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['team']['heading'] }}</h2>@endif
        </div>
        <ul class="sl-team">
            @foreach($team as $m)
            <li><span class="sl-team__face" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $m['name']), 0, 1)) }}</span><div><h3>{{ $m['name'] }}</h3>@if($txt($m['role'] ?? null))<p class="sl-team__role">{{ $m['role'] }}</p>@endif@if($txt($m['description'] ?? null))<p>{{ $m['description'] }}</p>@endif</div></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="sl-section"{!! $at('about') !!}>
    <div class="sl-wrap sl-about{{ $aboutImg ? '' : ' sl-about--text' }}">
        <div class="sl-about__body">
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <span class="sl-rule" aria-hidden="true"></span>
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
        @if($aboutImg)<img class="sl-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="sl-section"{!! $at('faq') !!}>
    <div class="sl-wrap sl-narrow">
        <div class="sl-head">
            <p class="sl-kicker">Before you visit</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="sl-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="sl-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

<section id="visit" class="sl-section sl-section--card sl-visit"{!! $at('contact') !!}>
    <div class="sl-wrap sl-visit__grid">
        <div>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            <span class="sl-rule" aria-hidden="true"></span>
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($hours)
        <table class="sl-hours">
            <caption>Hours</caption>
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
