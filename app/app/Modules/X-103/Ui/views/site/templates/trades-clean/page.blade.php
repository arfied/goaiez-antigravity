{{-- Trades Clean — plumbers, HVAC, electricians, cleaners: light and plain, built around the phone number. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $tick = '<svg class="tc-tick" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
    $phoneSvg = '<svg class="tc-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>';
    $hero = $b['hero'] ?? null;
    $heroImg = $hero ? $img($hero['image_path'] ?? null) : null;
    $heroHref = $hero && $txt($hero['cta_label'] ?? null) ? $link($hero['cta_url'] ?? null) : null;
    $services = array_values(array_filter((array) ($b['services']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['name'] ?? null)));
    $reviews = array_values(array_filter((array) ($b['reviews_strip']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['text'] ?? null)));
    $stats = array_values(array_filter((array) ($b['stats']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['value'] ?? null)));
    $faqs = array_values(array_filter((array) ($b['faq']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['question'] ?? null) && $txt($i['answer'] ?? null)));
    $gallery = array_values(array_filter((array) ($b['gallery']['items'] ?? []), fn ($i) => is_array($i) && $img($i['image_path'] ?? null)));
    $about = isset($b['about']) && $txt($b['about']['text'] ?? null) ? $b['about'] : null;
    $cta = isset($b['cta_band']) && $txt($b['cta_band']['heading'] ?? null) ? $b['cta_band'] : null;
    $ctaHref = $cta && $txt($cta['label'] ?? null) ? $link($cta['url'] ?? null) : null;
    $trust = array_values(array_filter([$facts['insurance'] ?? null, $facts['licence_number'] ?? null, isset($facts['years_in_business']) ? $facts['years_in_business'].' years in business' : null]));
    $area = $facts['service_area'] ?? null;
    $nav = array_filter(['services' => $services ? 'Services' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Reviews' : null, 'contact' => 'Contact']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
    $navLinks = $pages !== [] ? $pages : array_map(static fn (string $id, string $label): array => ['label' => $label, 'href' => '#'.$id, 'current' => false], array_keys($nav), array_values($nav));
@endphp
<div class="tc" id="top">
<header class="tc-nav">
    <div class="tc-wrap tc-nav__row">
        <a class="tc-brand" href="#top">{{ $name }}</a>
        <nav class="tc-nav__links" aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        @if($phone)<a class="tc-nav__phone" href="{{ $tel }}" aria-label="Call {{ $phone }}">{!! $phoneSvg !!}<span>{{ $phone }}</span></a>@endif
        <details class="tc-menu">
            <summary aria-label="Menu"><span></span><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="tc-hero{{ $heroImg ? '' : ' tc-hero--text' }}"{!! $at('hero') !!}>
    <div class="tc-wrap tc-hero__grid">
        <div class="tc-hero__body">
            @if($area)<p class="tc-pill">Serving {{ $area }}</p>@endif
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p class="tc-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            @if($phone)
            <a class="tc-callcard" href="{{ $tel }}">{!! $phoneSvg !!}<span><small>Call us</small><strong>{{ $phone }}</strong></span></a>
            @endif
            @if($heroHref)<a class="tc-button tc-button--line" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
            @if($trust)<ul class="tc-trust">@foreach($trust as $t)<li>{!! $tick !!}{{ $t }}</li>@endforeach</ul>@endif
        </div>
        @if($heroImg)<figure class="tc-hero__media"><img src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}"></figure>@endif
    </div>
</section>
@endif

@if($stats)
<section class="tc-stats"{!! $at('stats') !!}>
    <ul class="tc-wrap tc-stats__row">@foreach($stats as $s)<li><strong>{{ $s['value'] }}</strong>@if($txt($s['label'] ?? null))<span>{{ $s['label'] }}</span>@endif</li>@endforeach</ul>
</section>
@endif

@if($services)
<section id="services" class="tc-section"{!! $at('services') !!}>
    <div class="tc-wrap">
        <div class="tc-head">
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="tc-services">
            @foreach($services as $s)
            <li>
                <div class="tc-services__top">{!! $tick !!}<h3>{{ $s['name'] }}</h3>@if($txt($s['price_text'] ?? null))<span>{{ $s['price_text'] }}</span>@endif</div>
                @if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="tc-section tc-section--soft"{!! $at('reviews_strip') !!}>
    <div class="tc-wrap">
        <div class="tc-head">@if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif</div>
        <div class="tc-reviews">
            @foreach(array_slice($reviews, 0, 6) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <blockquote class="tc-review">
                @if($stars > 0)<p class="tc-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <p>“{{ $r['text'] }}”</p>
                @if($txt($r['author'] ?? null))<cite>{{ $r['author'] }}@if($txt($r['source'] ?? null)) · {{ $r['source'] }}@endif</cite>@endif
            </blockquote>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="tc-section tc-section--soft"{!! $at('about') !!}>
    <div class="tc-wrap tc-about{{ $aboutImg ? '' : ' tc-about--text' }}">
        <div class="tc-about__body">
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
        @if($aboutImg)<img class="tc-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
    </div>
</section>
@endif

@if($gallery)
<section class="tc-section"{!! $at('gallery') !!}>
    <div class="tc-wrap">
        <div class="tc-head">@if($txt($b['gallery']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@else<h2>Our work</h2>@endif</div>
        <div class="tc-gallery">@foreach(array_slice($gallery, 0, 6) as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($videos)
<section id="video" class="tc-section"{!! (count($videos) === 1 ? $videos[0]['at'] : '') !!}>
    <div class="tc-wrap tc-narrow">
        <div class="tc-head"><h2{!! count($videos) === 1 ? $f('name') : '' !!}>{{ count($videos) === 1 ? $videos[0]['name'] : 'Videos' }}</h2></div>
        <div id="videos-x176" class="tc-videos" style="display:grid;gap:1.5rem">
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
<section id="faq" class="tc-section"{!! $at('faq') !!}>
    <div class="tc-wrap tc-narrow">
        <div class="tc-head">@if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif</div>
        <div id="faq-x176" class="tc-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="tc-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

@if($cta || $phone)
<section class="tc-cta"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="tc-wrap tc-cta__inner">
        @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Need a hand?</h2>@endif
        @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        <div class="tc-cta__actions">
            @if($phone)<a class="tc-button tc-button--white" href="{{ $tel }}">{!! $phoneSvg !!}{{ $phone }}</a>@endif
            @if($ctaHref)<a class="tc-button tc-button--ghost" href="{{ $ctaHref }}"{!! $f('label') !!}>{{ $cta['label'] }}</a>@endif
        </div>
    </div>
</section>
@endif

@if($form)
<section id="message" class="tc-section"{!! $at('form') !!}>
    <div class="tc-wrap">
        <div class="tc-head">
            <h2>Send us a message</h2>
        </div>
        <form class="tc-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="tc-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="tc-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="tc-button tc-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="contact" class="tc-section"{!! $at('contact') !!}>
    <div class="tc-wrap tc-contact">
        <div>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
            @if($facts['licence_number'] ?? null)<p class="tc-muted">{{ $facts['licence_number'] }}</p>@endif
        </div>
        @if($hours)
        <table class="tc-hours">
            <caption>Opening hours</caption>
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
