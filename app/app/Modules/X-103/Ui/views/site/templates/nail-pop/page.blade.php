{{-- Nail Pop — nail salons and nail-art studios: playful colour, a tilted photo grid, a menu of price pills. A frozen layout: the AI fills the blocks, never this markup. --}}
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
    $shown = count($gallery) >= 3 ? array_slice($gallery, 0, min(6, intdiv(count($gallery), 3) * 3)) : $gallery;
    $nav = array_filter(['menu' => $services ? 'Menu' : null, 'nails' => $gallery ? 'Nails' : null, 'love' => $reviews ? 'Reviews' : null, 'visit' => 'Visit']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
@endphp
<div class="np" id="top">
<header class="np-nav">
    <div class="np-wrap np-nav__row">
        <a class="np-brand" href="#top">{{ $name }}</a>
        <nav class="np-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($book)<a class="np-button np-nav__book" href="{{ $book }}">{{ $bookHref !== null ? 'Book now' : ($heroHref !== null ? $hero['cta_label'] : 'Call') }}</a>@endif
        <details class="np-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="np-hero{{ $heroImg ? '' : ' np-hero--text' }}"{!! $at('hero') !!}>
    <div class="np-wrap np-hero__grid">
        <div class="np-hero__body">
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="np-actions">
                @if($heroHref)<a class="np-button np-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@elseif($book)<a class="np-button np-button--lg" href="{{ $book }}">{{ $bookLabel }}</a>@endif
                @if($services)<a class="np-button np-button--teal np-button--lg" href="#menu">See the menu</a>@endif
            </div>
        </div>
        @if($heroImg)<div class="np-hero__media"><img src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}"></div>@endif
    </div>
</section>
@endif

@if($services)
<section id="menu" class="np-section"{!! $at('services') !!}>
    <div class="np-wrap">
        <div class="np-head">
            <p class="np-sticker">menu</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="np-menuboard">
            @foreach($services as $s)
            <li>
                <div><h3>{{ $s['name'] }}</h3>@if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif</div>
                @if($txt($s['price_text'] ?? null))<span class="np-pill">{{ $s['price_text'] }}</span>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($shown)
<section id="nails" class="np-section np-section--lilac"{!! $at('gallery') !!}>
    <div class="np-wrap">
        <div class="np-head">
            <p class="np-sticker np-sticker--teal">fresh sets</p>
            @if($txt($b['gallery']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        </div>
        <div class="np-tiles">@foreach($shown as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($reviews)
<section id="love" class="np-section np-section--lilac"{!! $at('reviews_strip') !!}>
    <div class="np-wrap">
        <div class="np-head">
            <p class="np-sticker np-sticker--teal">reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="np-bubbles">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <figure>
                <blockquote>
                    @if($stars > 0)<p class="np-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                    <p>{{ $r['text'] }}</p>
                </blockquote>
                @if($txt($r['author'] ?? null))<figcaption>{{ $r['author'] }}@if($txt($r['source'] ?? null)) · {{ $r['source'] }}@endif</figcaption>@endif
            </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="np-book"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="np-wrap np-book__inner">
        @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Ready for a new set?</h2>@endif
        @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        @if($book)<a class="np-button np-button--white np-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
    </div>
</section>
@endif

@if($team)
<section id="team" class="np-section"{!! $at('team') !!}>
    <div class="np-wrap">
        <div class="np-head">
            <p class="np-sticker">our team</p>
            @if($txt($b['team']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['team']['heading'] }}</h2>@endif
        </div>
        <ul class="np-team">
            @foreach($team as $m)
            <li><span class="np-team__face" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $m['name']), 0, 1)) }}</span><div><h3>{{ $m['name'] }}</h3>@if($txt($m['role'] ?? null))<p class="np-team__role">{{ $m['role'] }}</p>@endif@if($txt($m['description'] ?? null))<p>{{ $m['description'] }}</p>@endif</div></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="np-section"{!! $at('about') !!}>
    <div class="np-wrap np-about{{ $aboutImg ? '' : ' np-about--text' }}">
        @if($aboutImg)<img class="np-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
        <div class="np-about__body">
            <p class="np-sticker">hello</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="np-section"{!! $at('faq') !!}>
    <div class="np-wrap np-narrow">
        <div class="np-head">
            <p class="np-sticker">faq</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="np-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="np-q" data-question="{{ trim((string) $q['question']) }}">
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
<section id="message" class="np-section"{!! $at('form') !!}>
    <div class="np-wrap">
        <div class="np-head">
            <p class="np-sticker">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="np-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="np-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="np-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="np-button np-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="visit" class="np-section np-section--lilac"{!! $at('contact') !!}>
    <div class="np-wrap np-visit">
        <div class="np-visit__card">
            <p class="np-sticker">visit</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($hours)
        <div class="np-visit__card">
            <p class="np-sticker np-sticker--teal">hours</p>
            <table class="np-hours">
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
