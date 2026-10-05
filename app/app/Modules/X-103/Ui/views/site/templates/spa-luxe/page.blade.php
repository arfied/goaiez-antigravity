{{-- Spa Luxe — day spas, massage, med-spas, wellness: dark and gold, a full photograph and a quiet menu. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $hero = $b['hero'] ?? null;
    $h1 = ($hero ? $txt($hero['headline'] ?? null) : null) ?? ($name !== '' ? $name : null);
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
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
    $navLinks = $pages !== [] ? $pages : array_map(static fn (string $id, string $label): array => ['label' => $label, 'href' => '#'.$id, 'current' => false], array_keys($nav), array_values($nav));
@endphp
<div class="sl" id="top">
<header class="sl-nav">
    <div class="sl-wrap sl-nav__row">
        <a class="sl-brand" href="#top">{{ $name }}</a>
        <nav class="sl-nav__links" aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        @if($book)<a class="sl-button sl-nav__book" href="{{ $book }}">{{ $bookHref !== null ? 'Book' : ($heroHref !== null ? $hero['cta_label'] : 'Call') }}</a>@endif
        <details class="sl-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if(! $hero && $h1 !== null)<h1 style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0">{{ $h1 }}</h1>@endif
@if($hero)
<section class="sl-hero{{ $heroImg ? '' : ' sl-hero--text' }}"{!! $at('hero') !!}>
    @if($heroImg)<img class="sl-hero__img" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">@endif
    <div class="sl-wrap sl-hero__body">
        <span class="sl-rule" aria-hidden="true"></span>
        @if($h1 !== null)<h1{!! $f('headline') !!}>{{ $h1 }}</h1>@endif
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

@if($videos)
<section id="video" class="sl-section"{!! (count($videos) === 1 ? $videos[0]['at'] : '') !!}>
    <div class="sl-wrap sl-narrow">
        <div class="sl-head">
            <p class="sl-kicker">Video</p>
            <h2{!! count($videos) === 1 ? $f('name') : '' !!}>{{ count($videos) === 1 ? $videos[0]['name'] : 'Videos' }}</h2>
        </div>
        <div id="videos-x176" class="sl-videos" style="display:grid;gap:1.5rem">
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

@if($form)
<section id="message" class="sl-section"{!! $at('form') !!}>
    <div class="sl-wrap">
        <div class="sl-head">
            <p class="sl-kicker">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="sl-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="sl-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="sl-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="sl-button sl-form__send">Send</button>
        </form>
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
