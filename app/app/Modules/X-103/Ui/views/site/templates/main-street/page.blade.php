{{-- Main Street — tax preparers, real estate agents, tutors, photographers, local agencies and studios: warm cream and violet, a friendly centred welcome, a photo in an arch. A frozen layout: the AI fills the blocks, never this markup. --}}
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
    $nav = array_filter(['services' => $services ? 'Services' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Kind words' : null, 'visit' => 'Visit']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
@endphp
<div class="ms" id="top">
<header class="ms-nav">
    <div class="ms-wrap ms-nav__row">
        <a class="ms-brand" href="#top">{{ $name }}</a>
        <nav class="ms-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($book)<a class="ms-button ms-nav__book" href="{{ $book }}">{{ $bookHref !== null ? 'Book' : $bookLabel }}</a>@endif
        <details class="ms-menu">
            <summary aria-label="Menu"><span></span><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="ms-hero"{!! $at('hero') !!}>
    <div class="ms-wrap ms-hero__grid{{ $heroImg ? '' : ' ms-hero__grid--text' }}">
        <div class="ms-hero__body">
            @if($address)<p class="ms-hero__where">{{ $address }}</p>@endif
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p class="ms-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="ms-actions">
                @if($heroHref)<a class="ms-button ms-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
                @if($phone)<a class="ms-button ms-button--soft ms-button--lg" href="{{ $tel }}">{{ $phone }}</a>@endif
            </div>
        </div>
        @if($heroImg)<div class="ms-hero__media"><img src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}"></div>@endif
    </div>
</section>
@endif

@if($services)
<section id="services" class="ms-section"{!! $at('services') !!}>
    <div class="ms-wrap">
        <div class="ms-head">
            <p class="ms-label">Services</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="ms-services{{ count($services) % 3 !== 0 && count($services) % 2 === 0 ? ' ms-services--two' : '' }}">
            @foreach($services as $si => $s)
            <li class="ms-services__item ms-tone{{ $si % 3 }}">
                <h3>{{ $s['name'] }}</h3>
                @if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif
                @if($txt($s['price_text'] ?? null))<span class="ms-price">{{ $s['price_text'] }}</span>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($stats)
<section class="ms-section ms-section--tight"{!! $at('stats') !!}>
    <ul class="ms-wrap ms-stats">@foreach(array_slice($stats, 0, 4) as $s)<li><strong>{{ $s['value'] }}</strong>@if($txt($s['label'] ?? null))<span>{{ $s['label'] }}</span>@endif</li>@endforeach</ul>
</section>
@endif

@if($reviews)
<section id="reviews" class="ms-section"{!! $at('reviews_strip') !!}>
    <div class="ms-wrap">
        <div class="ms-head ms-head--center">
            <p class="ms-label">Kind words</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="ms-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <figure>
                <span class="ms-quote" aria-hidden="true">“</span>
                <blockquote><p>{{ $r['text'] }}</p></blockquote>
                @if($stars > 0)<p class="ms-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                @if($txt($r['author'] ?? null))<figcaption>{{ $r['author'] }}@if($txt($r['source'] ?? null)) <span>· {{ $r['source'] }}</span>@endif</figcaption>@endif
            </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="ms-band"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="ms-wrap ms-band__inner">
        @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Come and say hello</h2>@endif
        @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        @if($book)<a class="ms-button ms-button--white ms-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
    </div>
</section>
@endif

@if($team)
<section id="team" class="ms-section"{!! $at('team') !!}>
    <div class="ms-wrap">
        <div class="ms-head ms-head--center">
            <p class="ms-label">Meet the team</p>
            @if($txt($b['team']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['team']['heading'] }}</h2>@endif
        </div>
        <ul class="ms-team">
            @foreach($team as $m)
            <li><span class="ms-team__face" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $m['name']), 0, 1)) }}</span><div><h3>{{ $m['name'] }}</h3>@if($txt($m['role'] ?? null))<p class="ms-team__role">{{ $m['role'] }}</p>@endif@if($txt($m['description'] ?? null))<p>{{ $m['description'] }}</p>@endif</div></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="ms-section ms-section--card"{!! $at('about') !!}>
    <div class="ms-wrap ms-about{{ $aboutImg ? '' : ' ms-about--text' }}">
        @if($aboutImg)<img class="ms-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
        <div class="ms-about__body">
            <p class="ms-label">About</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
    </div>
</section>
@endif

@if($shown)
<section class="ms-section ms-section--tight"{!! $at('gallery') !!} aria-label="Photos">
    <div class="ms-wrap">
        @if($txt($b['gallery']['heading'] ?? null))<h2 class="ms-gallery__head"{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        <div class="ms-gallery">@foreach($shown as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="ms-section"{!! $at('faq') !!}>
    <div class="ms-wrap ms-narrow">
        <div class="ms-head ms-head--center">
            <p class="ms-label">Questions</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="ms-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="ms-q" data-question="{{ trim((string) $q['question']) }}">
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
<section id="message" class="ms-section"{!! $at('form') !!}>
    <div class="ms-wrap">
        <div class="ms-head">
            <p class="ms-label">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="ms-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="ms-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="ms-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="ms-button ms-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="visit" class="ms-section ms-section--card"{!! $at('contact') !!}>
    <div class="ms-wrap ms-visit">
        <div class="ms-visit__card">
            <p class="ms-label">Visit</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p class="ms-visit__phone"><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($openHours)
        <div class="ms-visit__card">
            <p class="ms-label">Opening hours</p>
            <table class="ms-hours">
                @foreach($openHours as $h)
                <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
                @endforeach
            </table>
        </div>
        @endif
    </div>
</section>
</div>
