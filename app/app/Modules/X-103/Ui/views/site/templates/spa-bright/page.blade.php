{{-- Spa Bright — day spas, facials, massage, wellness and beauty studios: light, soft colour, rounded cards. A frozen layout: the AI fills the blocks, never this markup. --}}
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
    $nav = array_filter(['treatments' => $services ? 'Treatments' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Visit']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
    $navLinks = $pages !== [] ? $pages : array_map(static fn (string $id, string $label): array => ['label' => $label, 'href' => '#'.$id, 'current' => false], array_keys($nav), array_values($nav));
@endphp
<div class="sb" id="top">
<header class="sb-nav">
    <div class="sb-wrap sb-nav__row">
        <a class="sb-brand" href="#top"><span class="sb-brand__dot" aria-hidden="true"></span>{{ $name }}</a>
        <nav class="sb-nav__links" aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        @if($book)<a class="sb-button sb-nav__book" href="{{ $book }}">{{ $bookHref !== null ? 'Book' : ($heroHref !== null ? $hero['cta_label'] : 'Call') }}</a>@endif
        <details class="sb-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="sb-hero"{!! $at('hero') !!}>
    <div class="sb-wrap sb-hero__body">
        <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
        @if($txt($hero['subline'] ?? null))<p{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
        <div class="sb-actions">
            @if($heroHref)<a class="sb-button sb-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@elseif($book)<a class="sb-button sb-button--lg" href="{{ $book }}">{{ $bookLabel }}</a>@endif
            @if($services)<a class="sb-button sb-button--soft sb-button--lg" href="#treatments">Treatments</a>@endif
        </div>
    </div>
    @if($heroImg)
    <div class="sb-wrap">
        <figure class="sb-hero__media">
            <img src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">
            @if($services)<figcaption class="sb-chips">@foreach(array_slice($services, 0, 3) as $s)<span>{{ $s['name'] }}</span>@endforeach</figcaption>@endif
        </figure>
    </div>
    @endif
</section>
@endif

@if($services)
<section id="treatments" class="sb-section"{!! $at('services') !!}>
    <div class="sb-wrap">
        <div class="sb-head">
            <p class="sb-tag">Treatments</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="sb-cards">
            @foreach($services as $s)
            <li class="sb-card">
                <h3>{{ $s['name'] }}</h3>
                @if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif
                @if($txt($s['price_text'] ?? null))<span class="sb-card__price">{{ $s['price_text'] }}</span>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($gallery)
<section class="sb-section"{!! $at('gallery') !!}>
    <div class="sb-wrap">
        @if($txt($b['gallery']['heading'] ?? null))<div class="sb-head"><h2{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2></div>@endif
        @php $shown = count($gallery) >= 4 ? array_slice($gallery, 0, min(8, intdiv(count($gallery), 4) * 4)) : $gallery; @endphp
        <div class="sb-gallery">@foreach($shown as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="sb-section sb-section--tint"{!! $at('reviews_strip') !!}>
    <div class="sb-wrap">
        <div class="sb-head">
            <p class="sb-tag">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="sb-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <blockquote>
                @if($stars > 0)<p class="sb-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <p>{{ $r['text'] }}</p>
                @if($txt($r['author'] ?? null))<cite><span aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $r['author']), 0, 1)) }}</span>{{ $r['author'] }}</cite>@endif
            </blockquote>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="sb-book"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="sb-wrap">
        <div class="sb-book__card">
            @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Book a little time for you</h2>@endif
            @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
            @if($book)<a class="sb-button sb-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
        </div>
    </div>
</section>
@endif

@if($team)
<section id="team" class="sb-section"{!! $at('team') !!}>
    <div class="sb-wrap">
        <div class="sb-head">
            <p class="sb-tag">Our team</p>
            @if($txt($b['team']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['team']['heading'] }}</h2>@endif
        </div>
        <ul class="sb-team">
            @foreach($team as $m)
            <li><span class="sb-team__face" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $m['name']), 0, 1)) }}</span><div><h3>{{ $m['name'] }}</h3>@if($txt($m['role'] ?? null))<p class="sb-team__role">{{ $m['role'] }}</p>@endif@if($txt($m['description'] ?? null))<p>{{ $m['description'] }}</p>@endif</div></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="sb-section sb-section--tint"{!! $at('about') !!}>
    <div class="sb-wrap sb-about{{ $aboutImg ? '' : ' sb-about--text' }}">
        <div class="sb-about__body">
            <p class="sb-tag">About us</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
        @if($aboutImg)<img class="sb-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
    </div>
</section>
@endif

@if($videos)
<section id="video" class="sb-section"{!! (count($videos) === 1 ? $videos[0]['at'] : '') !!}>
    <div class="sb-wrap sb-narrow">
        <div class="sb-head">
            <p class="sb-tag">Video</p>
            <h2{!! count($videos) === 1 ? $f('name') : '' !!}>{{ count($videos) === 1 ? $videos[0]['name'] : 'Videos' }}</h2>
        </div>
        <div id="videos-x176" class="sb-videos" style="display:grid;gap:1.5rem">
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
<section id="faq" class="sb-section"{!! $at('faq') !!}>
    <div class="sb-wrap sb-narrow">
        <div class="sb-head">
            <p class="sb-tag">Good to know</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="sb-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="sb-q" data-question="{{ trim((string) $q['question']) }}">
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
<section id="message" class="sb-section"{!! $at('form') !!}>
    <div class="sb-wrap">
        <div class="sb-head">
            <p class="sb-tag">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="sb-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="sb-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="sb-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="sb-button sb-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="visit" class="sb-section sb-section--tint"{!! $at('contact') !!}>
    <div class="sb-wrap sb-visit">
        <div class="sb-visit__card">
            <p class="sb-tag">Visit</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($hours)
        <div class="sb-visit__card">
            <p class="sb-tag">Hours</p>
            <table class="sb-hours">
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
