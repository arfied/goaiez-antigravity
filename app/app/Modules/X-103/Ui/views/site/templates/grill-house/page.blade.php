{{-- Grill House — grills, barbecue, burger joints, pizza and taquerias: charcoal, fire orange, a menu board with big prices. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $hero = $b['hero'] ?? null;
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
    $openHours = array_values(array_filter($hours, fn ($h) => is_array($h) && $txt($h['day'] ?? null)));
    $strip = count($gallery) >= 4 ? array_slice($gallery, 0, 4) : $gallery;
    $nav = array_filter(['menu' => $menu ? 'Menu' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => $openHours ? 'Hours' : 'Contact']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
@endphp
<div class="gh" id="top">
<header class="gh-nav">
    <div class="gh-wrap gh-nav__row">
        <a class="gh-brand" href="#top">{{ $name }}</a>
        <nav class="gh-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($phone)<a class="gh-button gh-nav__call" href="{{ $tel }}">{{ $phone }}</a>@endif
        <details class="gh-menu">
            <summary aria-label="Menu"><span></span><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="gh-hero{{ $heroImg ? '' : ' gh-hero--plain' }}"{!! $at('hero') !!}>
    @if($heroImg)<img class="gh-hero__img" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">@endif
    <div class="gh-wrap gh-hero__body">
        <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
        @if($txt($hero['subline'] ?? null))<p class="gh-hero__sub"{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
        <div class="gh-actions">
            @if($heroHref)<a class="gh-button gh-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
            @if($menu)<a class="gh-button gh-button--line gh-button--lg" href="#menu">See the menu</a>@endif
        </div>
    </div>
</section>
@endif

@if($openHours || $address)
<div class="gh-ticker">
    <div class="gh-wrap gh-ticker__row">
        @foreach($openHours as $h)
        <p><strong>{{ $h['day'] }}</strong> {{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</p>
        @endforeach
        @if($address)<p class="gh-ticker__where">{{ $address }}</p>@endif
    </div>
</div>
@endif

@if($menu)
<section id="menu" class="gh-section"{!! $at('services') !!}>
    <div class="gh-wrap">
        <div class="gh-head">
            <p class="gh-tag">Menu</p>
            @if($txt($b['services']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['services']['heading'] }}</h2>@endif
        </div>
        <ul class="gh-board">
            @foreach($menu as $m)
            <li>
                <div><h3>{{ $m['name'] }}</h3>@if($txt($m['description'] ?? null))<p>{{ $m['description'] }}</p>@endif</div>
                @if($txt($m['price_text'] ?? null))<span class="gh-price">{{ $m['price_text'] }}</span>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($strip)
<section class="gh-strip"{!! $at('gallery') !!} aria-label="{{ $txt($b['gallery']['heading'] ?? null) ?? 'Photos' }}">
    @foreach($strip as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach
</section>
@endif

@if($reviews)
<section id="reviews" class="gh-section"{!! $at('reviews_strip') !!}>
    <div class="gh-wrap">
        <div class="gh-head">
            <p class="gh-tag">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="gh-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <figure>
                @if($stars > 0)<p class="gh-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <blockquote><p>{{ $r['text'] }}</p></blockquote>
                @if($txt($r['author'] ?? null))<figcaption>{{ $r['author'] }}@if($txt($r['source'] ?? null)) · {{ $r['source'] }}@endif</figcaption>@endif
            </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($book || $cta)
<section class="gh-band"{!! $cta ? $at('cta_band') : '' !!}>
    <div class="gh-wrap gh-band__inner">
        <div>
            @if($cta)<h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>@else<h2>Hungry?</h2>@endif
            @if($cta && $txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        </div>
        @if($book)<a class="gh-button gh-button--dark gh-button--lg" href="{{ $book }}"{!! $bookHref !== null ? $at('booking_button') : '' !!}>{{ $bookLabel }}</a>@endif
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="about" class="gh-about{{ $aboutImg ? '' : ' gh-about--text' }}"{!! $at('about') !!}>
    @if($aboutImg)<img class="gh-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
    <div class="gh-about__body">
        <p class="gh-tag">Our story</p>
        @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
        <p{!! $f('text') !!}>{{ $about['text'] }}</p>
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="gh-section"{!! $at('faq') !!}>
    <div class="gh-wrap gh-narrow">
        <div class="gh-head">
            <p class="gh-tag">Questions</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="gh-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="gh-q" data-question="{{ trim((string) $q['question']) }}">
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
<section id="message" class="gh-section"{!! $at('form') !!}>
    <div class="gh-wrap">
        <div class="gh-head">
            <p class="gh-tag">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="gh-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="gh-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="gh-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="gh-button gh-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="visit" class="gh-section gh-section--card"{!! $at('contact') !!}>
    <div class="gh-wrap gh-visit">
        <div class="gh-visit__where">
            <p class="gh-tag">Come by</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
            @if($phone)<p class="gh-visit__phone"><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($openHours)
        <table class="gh-hours">
            @foreach($openHours as $h)
            <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
            @endforeach
        </table>
        @endif
    </div>
</section>
</div>
