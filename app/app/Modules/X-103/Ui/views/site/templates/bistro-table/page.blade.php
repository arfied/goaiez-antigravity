{{-- Bistro Table — restaurants, bistros and trattorias: cream paper and deep green, a printed menu with dotted leaders. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $hero = $b['hero'] ?? null;
    $h1 = ($hero ? $txt($hero['headline'] ?? null) : null) ?? ($name !== '' ? $name : null);
    $heroImg = $hero ? $img($hero['image_path'] ?? null) : null;
    $heroHref = $hero && $txt($hero['cta_label'] ?? null) ? $link($hero['cta_url'] ?? null) : null;
    $about = isset($b['about']) && $txt($b['about']['text'] ?? null) ? $b['about'] : null;
    $menu = array_values(array_filter((array) ($b['services']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['name'] ?? null)));
    $reviews = array_values(array_filter((array) ($b['reviews_strip']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['text'] ?? null)));
    $faqs = array_values(array_filter((array) ($b['faq']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['question'] ?? null) && $txt($i['answer'] ?? null)));
    $gallery = array_values(array_filter((array) ($b['gallery']['items'] ?? []), fn ($i) => is_array($i) && $img($i['image_path'] ?? null)));
    $booking = $b['booking_button'] ?? null;
    $bookHref = $booking && $txt($booking['label'] ?? null) ? $link($booking['url'] ?? null) : null;
    $book = $bookHref ?? $heroHref ?? $tel;
    $bookLabel = $bookHref !== null ? $booking['label'] : ($heroHref !== null ? $hero['cta_label'] : ($phone ? 'Call '.$phone : null));
    $cta = isset($b['cta_band']) && $txt($b['cta_band']['heading'] ?? null) ? $b['cta_band'] : null;
    $shown = count($gallery) >= 3 ? array_slice($gallery, 0, min(6, intdiv(count($gallery), 3) * 3)) : $gallery;
    $nav = array_filter(['menu' => $menu ? 'Menu' : null, 'story' => $about ? 'Our story' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Hours & visit']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
    $navLinks = $pages !== [] ? $pages : array_map(static fn (string $id, string $label): array => ['label' => $label, 'href' => '#'.$id, 'current' => false], array_keys($nav), array_values($nav));
@endphp
<div class="bt" id="top">
<header class="bt-nav">
    <div class="bt-wrap bt-nav__row">
        <a class="bt-brand" href="#top">{{ $name }}</a>
        <nav class="bt-nav__links" aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        @if($book)<a class="bt-button bt-nav__book" href="{{ $book }}">{{ $bookHref !== null || $heroHref !== null ? $bookLabel : 'Call' }}</a>@endif
        <details class="bt-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if(! $hero && $h1 !== null)<h1 style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0">{{ $h1 }}</h1>@endif
@if($hero)
<section class="bt-hero{{ $heroImg ? '' : ' bt-hero--plain' }}"{!! $at('hero') !!}>
    @if($heroImg)<img class="bt-hero__img" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">@endif
    <div class="bt-wrap bt-hero__body">
        <p class="bt-flourish" aria-hidden="true">— ✦ —</p>
        @if($h1 !== null)<h1{!! $f('headline') !!}>{{ $h1 }}</h1>@endif
        @if($txt($hero['subline'] ?? null))<p class="bt-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
        <div class="bt-actions">
            @if($heroHref)<a class="bt-button bt-button--light bt-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
            @if($menu)<a class="bt-button bt-button--line bt-button--lg" href="#menu">See the menu</a>@endif
        </div>
    </div>
</section>
@endif

@if($address || $phone || $hours)
<div class="bt-info">
    <div class="bt-wrap bt-info__row">
        @if($address)<p><span class="bt-label">Find us</span>{{ $address }}</p>@endif
        @if($hours)
        @php $first = collect($hours)->first(fn ($h) => is_array($h) && $txt($h['day'] ?? null) && $txt($h['close'] ?? null)); @endphp
        @if($first)<p><span class="bt-label">Hours</span>{{ $first['day'] }} {{ $first['open'] ?? '' }} – {{ $first['close'] }} · <a href="#visit">all hours</a></p>@endif
        @endif
        @if($phone)<p><span class="bt-label">Call</span><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
    </div>
</div>
@endif

@if($menu)
<section id="menu" class="bt-section bt-section--card"{!! $at('services') !!}>
    <div class="bt-wrap bt-narrow">
        <div class="bt-head">
            <p class="bt-flourish" aria-hidden="true">— ✦ —</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@else<h2>Menu</h2>@endif
        </div>
        <ul class="bt-menulist">
            @foreach($menu as $m)
            <li>
                <div class="bt-menulist__line"><h3>{{ $m['name'] }}</h3>@if($txt($m['price_text'] ?? null))<span class="bt-dots" aria-hidden="true"></span><span class="bt-price">{{ $m['price_text'] }}</span>@endif</div>
                @if($txt($m['description'] ?? null))<p>{{ $m['description'] }}</p>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($shown)
<section class="bt-gallery"{!! $at('gallery') !!} aria-label="Photos">
    <div class="bt-wrap">
        @if($txt($b['gallery']['heading'] ?? null))<h2 class="bt-gallery__head"{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        <div class="bt-gallery__grid">@foreach($shown as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="bt-section"{!! $at('reviews_strip') !!}>
    <div class="bt-wrap">
        <div class="bt-head">
            <p class="bt-label">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="bt-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <figure>
                @if($stars > 0)<p class="bt-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <blockquote><p>“{{ $r['text'] }}”</p></blockquote>
                @if($txt($r['author'] ?? null))<figcaption>{{ $r['author'] }}@if($txt($r['source'] ?? null)) · {{ $r['source'] }}@endif</figcaption>@endif
            </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="bt-band"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="bt-wrap bt-band__inner">
        @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Come and see us</h2>@endif
        @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        @if($book)<a class="bt-button bt-button--light bt-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="story" class="bt-section"{!! $at('about') !!}>
    <div class="bt-wrap bt-about{{ $aboutImg ? '' : ' bt-about--text' }}">
        @if($aboutImg)<figure class="bt-about__img"><img src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy"></figure>@endif
        <div class="bt-about__body">
            <p class="bt-label">Our story</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
            @if($txt($facts['years_in_business'] ?? null))<p class="bt-since">Serving guests for {{ $facts['years_in_business'] }} years</p>@endif
        </div>
    </div>
</section>
@endif

@if($videos)
<section id="video" class="bt-section"{!! (count($videos) === 1 ? $videos[0]['at'] : '') !!}>
    <div class="bt-wrap bt-narrow">
        <div class="bt-head">
            <p class="bt-label">Video</p>
            <h2{!! count($videos) === 1 ? $f('name') : '' !!}>{{ count($videos) === 1 ? $videos[0]['name'] : 'Videos' }}</h2>
        </div>
        <div id="videos-x176" class="bt-videos" style="display:grid;gap:1.5rem">
            @foreach($videos as $video)
            <div class="video-item" data-name="{{ $video['name'] }}" data-url="{{ $video['contentUrl'] }}"{!! count($videos) > 1 ? $video['at'] : '' !!}>
@include('x-103::site.partials.video-player', ['video' => $video, 'preview' => $preview])
                @if(count($videos) > 1)<p>{{ $video['name'] }}</p>@endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="bt-section"{!! $at('faq') !!}>
    <div class="bt-wrap bt-narrow">
        <div class="bt-head">
            <p class="bt-label">Good to know</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="bt-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="bt-q" data-question="{{ trim((string) $q['question']) }}">
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
<section id="message" class="bt-section"{!! $at('form') !!}>
    <div class="bt-wrap">
        <div class="bt-head">
            <p class="bt-label">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="bt-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="bt-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="bt-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="bt-button bt-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="visit" class="bt-section bt-section--card"{!! $at('contact') !!}>
    <div class="bt-wrap bt-visit">
        <div class="bt-visit__where">
            <p class="bt-label">Visit</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($hours)
        <div class="bt-visit__hours">
            <p class="bt-label">Hours</p>
            <table class="bt-hours">
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
