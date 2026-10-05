{{-- Garage Pro — auto repair shops, tyre and brake centres, mechanics: steel grey and racing red, an angled photo, numbered services. A frozen layout: the AI fills the blocks, never this markup. --}}
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
    $trust = array_values(array_filter([$txt($facts['insurance'] ?? null), $txt($facts['licence_number'] ?? null), $txt($facts['years_in_business'] ?? null) ? $facts['years_in_business'].' years in business' : null]));
    $openHours = array_values(array_filter($hours, fn ($h) => is_array($h) && $txt($h['day'] ?? null)));
    $shown = count($gallery) >= 3 ? array_slice($gallery, 0, min(6, intdiv(count($gallery), 3) * 3)) : $gallery;
    $nav = array_filter(['services' => $services ? 'Services' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Contact']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
    $navLinks = $pages !== [] ? $pages : array_map(static fn (string $id, string $label): array => ['label' => $label, 'href' => '#'.$id, 'current' => false], array_keys($nav), array_values($nav));
@endphp
<div class="gp" id="top">
@if($openHours || $address)
<div class="gp-top">
    <div class="gp-wrap gp-top__row">
        @if($openHours)<p>{{ $openHours[0]['day'] }} {{ $txt($openHours[0]['close'] ?? null) === null ? 'closed' : ($openHours[0]['open'] ?? '').'–'.$openHours[0]['close'] }}</p>@endif
        @if($address)<p class="gp-top__where">{{ $address }}</p>@endif
    </div>
</div>
@endif
<header class="gp-nav">
    <div class="gp-wrap gp-nav__row">
        <a class="gp-brand" href="#top"><span class="gp-brand__bar" aria-hidden="true"></span>{{ $name }}</a>
        <nav class="gp-nav__links" aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        @if($phone)<a class="gp-button gp-nav__call" href="{{ $tel }}">{{ $phone }}</a>@endif
        <details class="gp-menu">
            <summary aria-label="Menu"><span></span><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="gp-hero{{ $heroImg ? '' : ' gp-hero--plain' }}"{!! $at('hero') !!}>
    <div class="gp-hero__body">
        <div class="gp-hero__inner">
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p class="gp-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="gp-actions">
                @if($heroHref)<a class="gp-button gp-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
                @if($phone)<a class="gp-button gp-button--line gp-button--lg" href="{{ $tel }}">Call {{ $phone }}</a>@endif
            </div>
            @if($trust)<ul class="gp-trust">@foreach($trust as $t)<li>{{ $t }}</li>@endforeach</ul>@endif
        </div>
    </div>
    @if($heroImg)<div class="gp-hero__media"><img src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}"></div>@endif
</section>
@endif

@if($stats)
<section class="gp-stats"{!! $at('stats') !!}>
    <ul class="gp-wrap gp-stats__row">@foreach(array_slice($stats, 0, 4) as $s)<li><strong>{{ $s['value'] }}</strong>@if($txt($s['label'] ?? null))<span>{{ $s['label'] }}</span>@endif</li>@endforeach</ul>
</section>
@endif

@if($services)
<section id="services" class="gp-section"{!! $at('services') !!}>
    <div class="gp-wrap">
        <div class="gp-head">
            <p class="gp-label">Services</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ol class="gp-services{{ count($services) % 3 !== 0 && count($services) % 2 === 0 ? ' gp-services--two' : '' }}">
            @foreach($services as $si => $s)
            <li>
                <span class="gp-num" aria-hidden="true">{{ str_pad((string) ($si + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <h3>{{ $s['name'] }}</h3>
                @if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif
                @if($txt($s['price_text'] ?? null))<span class="gp-price">{{ $s['price_text'] }}</span>@endif
            </li>
            @endforeach
        </ol>
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="gp-section"{!! $at('reviews_strip') !!}>
    <div class="gp-wrap">
        <div class="gp-head">
            <p class="gp-label">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="gp-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <figure>
                @if($stars > 0)<p class="gp-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <blockquote><p>{{ $r['text'] }}</p></blockquote>
                @if($txt($r['author'] ?? null))<figcaption>{{ $r['author'] }}@if($txt($r['source'] ?? null)) · {{ $r['source'] }}@endif</figcaption>@endif
            </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="gp-band"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="gp-wrap gp-band__inner">
        <div>
            @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Book your car in</h2>@endif
            @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        </div>
        @if($book)<a class="gp-button gp-button--white gp-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="gp-section gp-section--dark"{!! $at('about') !!}>
    <div class="gp-wrap gp-about{{ $aboutImg ? '' : ' gp-about--text' }}">
        @if($aboutImg)<img class="gp-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
        <div class="gp-about__body">
            <p class="gp-label gp-label--light">About the shop</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
            @if($txt($facts['service_area'] ?? null))<p class="gp-area">Serving {{ $facts['service_area'] }}</p>@endif
        </div>
    </div>
</section>
@endif

@if($shown)
<section class="gp-section gp-section--tight"{!! $at('gallery') !!} aria-label="Photos">
    <div class="gp-wrap">
        @if($txt($b['gallery']['heading'] ?? null))<h2 class="gp-gallery__head"{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        <div class="gp-gallery">@foreach($shown as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($videos)
<section id="video" class="gp-section"{!! (count($videos) === 1 ? $videos[0]['at'] : '') !!}>
    <div class="gp-wrap gp-narrow">
        <div class="gp-head">
            <p class="gp-label">Video</p>
            <h2{!! count($videos) === 1 ? $f('name') : '' !!}>{{ count($videos) === 1 ? $videos[0]['name'] : 'Videos' }}</h2>
        </div>
        <div id="videos-x176" class="gp-videos" style="display:grid;gap:1.5rem">
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
<section id="faq" class="gp-section"{!! $at('faq') !!}>
    <div class="gp-wrap gp-narrow">
        <div class="gp-head">
            <p class="gp-label">Questions</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="gp-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="gp-q" data-question="{{ trim((string) $q['question']) }}">
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
<section id="message" class="gp-section"{!! $at('form') !!}>
    <div class="gp-wrap">
        <div class="gp-head">
            <p class="gp-label">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="gp-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="gp-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="gp-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="gp-button gp-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="visit" class="gp-section gp-section--card"{!! $at('contact') !!}>
    <div class="gp-wrap gp-visit">
        <div class="gp-visit__where">
            <p class="gp-label">Contact</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p class="gp-visit__phone"><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($openHours)
        <table class="gp-hours">
            @foreach($openHours as $h)
            <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
            @endforeach
        </table>
        @endif
    </div>
</section>
</div>
