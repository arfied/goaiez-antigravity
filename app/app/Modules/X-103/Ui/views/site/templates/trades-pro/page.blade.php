{{-- Trades Pro — plumbers, HVAC, electricians, roofers. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $icon = function (string $name): string {
        $n = strtolower($name);
        $paths = [
            'drop' => '<path d="M12 2.7C8.5 7 6 10.3 6 13.5a6 6 0 0 0 12 0c0-3.2-2.5-6.5-6-10.8z"/>',
            'flame' => '<path d="M12 22c4 0 7-2.7 7-6.6 0-3.1-2-5.7-3.6-7.4.1 1.6-.6 3.1-1.9 3.6C13.6 8.4 12 5 9 2c.4 3-1.7 5.1-3 6.9C4.8 10.6 5 12.6 5 15.4 5 19.3 8 22 12 22z"/>',
            'bolt' => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
            'snow' => '<path d="M12 2v20M4.9 6.5l14.2 11M19.1 6.5 4.9 17.5M9 4l3 2 3-2M9 20l3-2 3 2"/>',
            'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/>',
            'roof' => '<path d="M2 13 12 4l10 9"/><path d="M6 10v10h12V10M10 20v-5h4v5"/>',
            'wrench' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z"/>',
        ];
        $pick = 'wrench';
        foreach (['flame' => ['heat', 'furnace', 'boiler', 'gas'], 'drop' => ['leak', 'water', 'pipe', 'drip', 'drain', 'sewer', 'clog'],
            'bolt' => ['electric', 'wiring', 'panel', 'light', 'outlet', 'ev '], 'snow' => ['cool', 'air con', 'a/c', 'ac ', 'hvac'],
            'roof' => ['roof', 'gutter', 'shingle'], 'home' => ['install', 'fixture', 'faucet', 'toilet', 'remodel', 'repipe', 'repiping']] as $key => $words) {
            foreach ($words as $w) {
                if (str_contains($n, $w)) { $pick = $key; break 2; }
            }
        }
        return '<svg class="tp-icon" viewBox="0 0 24 24" aria-hidden="true">'.$paths[$pick].'</svg>';
    };
    $check = '<svg class="tp-tick" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
    $phoneSvg = '<svg class="tp-ico-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>';
    $pinSvg = '<svg class="tp-ico-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>';
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
    $nav = array_filter(['services' => $services ? 'Services' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Reviews' : null, 'faq' => $faqs ? 'FAQ' : null, 'contact' => 'Contact']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
@endphp
<div class="tp" id="top">
@if($trust || $phone)
<div class="tp-top">
    <div class="tp-wrap tp-top__row">
        <p class="tp-top__trust">@foreach(array_slice($trust, 0, 2) as $item)<span>{!! $check !!}{{ $item }}</span>@endforeach</p>
        @if($phone)<a class="tp-top__phone" href="{{ $tel }}">{!! $phoneSvg !!}{{ $phone }}</a>@endif
    </div>
</div>
@endif
<header class="tp-nav">
    <div class="tp-wrap tp-nav__row">
        <a class="tp-brand" href="#top">@if($name !== '')<span class="tp-brand__mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span><span>{{ $name }}</span>@endif</a>
        <nav class="tp-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($phone)<a class="tp-button tp-button--primary tp-nav__call" href="{{ $tel }}" aria-label="Call {{ $phone }}">{!! $phoneSvg !!}<span>{{ $phone }}</span></a>@endif
        <details class="tp-menu">
            <summary aria-label="Menu"><span></span><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="tp-hero{{ $heroImg ? '' : ' tp-hero--plain' }}"{!! $at('hero') !!}>
    @if($heroImg)<img class="tp-hero__img" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">@endif
    <div class="tp-wrap tp-hero__body">
        @if($area)<p class="tp-eyebrow tp-eyebrow--light">{!! $pinSvg !!}Serving {{ $area }}</p>@endif
        <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
        @if($txt($hero['subline'] ?? null))<p class="tp-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
        <div class="tp-actions">
            @if($heroHref)<a class="tp-button tp-button--primary tp-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
            @if($phone)<a class="tp-button tp-button--ghost tp-button--lg" href="{{ $tel }}">{!! $phoneSvg !!}Call {{ $phone }}</a>@endif
        </div>
        @if($trust)<ul class="tp-hero__trust">@foreach($trust as $item)<li>{!! $check !!}{{ $item }}</li>@endforeach</ul>@endif
    </div>
</section>
@endif

@if($stats)
<section class="tp-stats"{!! $at('stats') !!}>
    <div class="tp-wrap"><ul class="tp-stats__card">@foreach($stats as $s)<li><strong>{{ $s['value'] }}</strong>@if($txt($s['label'] ?? null))<span>{{ $s['label'] }}</span>@endif</li>@endforeach</ul></div>
</section>
@endif

@if($services)
<section id="services" class="tp-section"{!! $at('services') !!}>
    <div class="tp-wrap">
        <div class="tp-head">
            <p class="tp-eyebrow">Services</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="tp-services">
            @foreach($services as $s)
            <li class="tp-service">
                {!! $icon((string) $s['name']) !!}
                <h3>{{ $s['name'] }}</h3>
                @if($txt($s['description'] ?? null))<p>{{ $s['description'] }}</p>@endif
                @if($txt($s['price_text'] ?? null))<span class="tp-price">{{ $s['price_text'] }}</span>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="tp-section"{!! $at('reviews_strip') !!}>
    <div class="tp-wrap">
        <div class="tp-head">
            <p class="tp-eyebrow">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="tp-reviews">
            @foreach($reviews as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <blockquote class="tp-review">
                @if($stars > 0)<p class="tp-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}<span>{{ str_repeat('★', 5 - $stars) }}</span></p>@endif
                <p>{{ $r['text'] }}</p>
                @if($txt($r['author'] ?? null))<cite>{{ $r['author'] }}@if($txt($r['source'] ?? null))<span> · {{ $r['source'] }}</span>@endif</cite>@endif
            </blockquote>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="tp-section tp-section--tint"{!! $at('about') !!}>
    <div class="tp-wrap tp-about{{ $aboutImg ? '' : ' tp-about--text' }}">
        @if($aboutImg)<figure class="tp-about__media"><img src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy"></figure>@endif
        <div class="tp-about__body">
            <p class="tp-eyebrow">About us</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
            @if($trust)<ul class="tp-checks">@foreach($trust as $item)<li>{!! $check !!}{{ $item }}</li>@endforeach</ul>@endif
        </div>
    </div>
</section>
@endif

@if($gallery)
<section id="work" class="tp-section tp-section--tint"{!! $at('gallery') !!}>
    <div class="tp-wrap">
        <div class="tp-head">
            <p class="tp-eyebrow">Our work</p>
            @if($txt($b['gallery']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        </div>
        <div class="tp-gallery">@foreach(array_slice($gallery, 0, 6) as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($area)
<section class="tp-area">
    <div class="tp-wrap tp-area__row">{!! $pinSvg !!}<p><strong>Where we work</strong>{{ $area }}</p></div>
</section>
@endif

@if($faqs)
<section id="faq" class="tp-section"{!! $at('faq') !!}>
    <div class="tp-wrap tp-faq">
        <div class="tp-head">
            <p class="tp-eyebrow">FAQ</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="tp-faq__list">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="tp-q" data-question="{{ trim((string) $q['question']) }}">
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
<section class="tp-cta"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="tp-wrap tp-cta__row">
        <div>
            @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Call us today</h2>@endif
            @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        </div>
        <div class="tp-actions">
            @if($phone)<a class="tp-button tp-button--primary tp-button--lg" href="{{ $tel }}">{!! $phoneSvg !!}{{ $phone }}</a>@endif
            @if($ctaHref)<a class="tp-button tp-button--ghost tp-button--lg" href="{{ $ctaHref }}"{!! $f('label') !!}>{{ $cta['label'] }}</a>@endif
        </div>
    </div>
</section>
@endif

@if($form)
<section id="message" class="tp-section"{!! $at('form') !!}>
    <div class="tp-wrap">
        <div class="tp-head">
            <p class="tp-eyebrow">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="tp-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="tp-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="tp-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="tp-button tp-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="contact" class="tp-section tp-contact"{!! $at('contact') !!}>
    <div class="tp-wrap tp-contact__grid">
        <div>
            <p class="tp-eyebrow">Contact</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            <ul class="tp-contact__list">
                @if($phone)<li><span>Phone</span><a href="{{ $tel }}">{{ $phone }}</a></li>@endif
                @if($email)<li><span>Email</span><a href="mailto:{{ $email }}">{{ $email }}</a></li>@endif
                @if($address)<li><span>Address</span>{{ $address }}</li>@endif
                @if($facts['licence_number'] ?? null)<li><span>Licence</span>{{ $facts['licence_number'] }}</li>@endif
            </ul>
        </div>
        @if($hours)
        <div>
            <p class="tp-eyebrow">Hours</p>
            <table class="tp-hours">
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
