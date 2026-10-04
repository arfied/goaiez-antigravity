{{-- Maker Market — small shops, makers, boutiques, gift and home stores. A frozen layout: the AI fills the blocks, never this markup. --}}
@php
    $hero = $b['hero'] ?? null;
    $heroImg = $hero ? $img($hero['image_path'] ?? null) : null;
    $heroHref = $hero && $txt($hero['cta_label'] ?? null) ? $link($hero['cta_url'] ?? null) : null;
    $about = isset($b['about']) && $txt($b['about']['text'] ?? null) ? $b['about'] : null;
    $products = array_values(array_filter((array) ($b['products']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['name'] ?? null)));
    $reviews = array_values(array_filter((array) ($b['reviews_strip']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['text'] ?? null)));
    $faqs = array_values(array_filter((array) ($b['faq']['items'] ?? []), fn ($i) => is_array($i) && $txt($i['question'] ?? null) && $txt($i['answer'] ?? null)));
    $gallery = array_values(array_filter((array) ($b['gallery']['items'] ?? []), fn ($i) => is_array($i) && $img($i['image_path'] ?? null)));
    $cta = isset($b['cta_band']) && $txt($b['cta_band']['heading'] ?? null) ? $b['cta_band'] : null;
    $ctaHref = $cta && $txt($cta['label'] ?? null) ? $link($cta['url'] ?? null) : null;
    $nav = array_filter(['shop' => $products ? 'Shop' : null, 'story' => $about ? 'Our story' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Visit']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
@endphp
<div class="mm" id="top">
<header class="mm-nav">
    <div class="mm-wrap mm-nav__row">
        <a class="mm-brand" href="#top">{{ $name }}</a>
        <nav class="mm-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($products)<a class="mm-button mm-button--ink mm-nav__shop" href="#shop">Shop</a>@endif
        <details class="mm-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="mm-hero{{ $heroImg ? '' : ' mm-hero--text' }}"{!! $at('hero') !!}>
    @if($heroImg)<img class="mm-hero__img" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">@endif
    <div class="mm-wrap mm-hero__wrap">
        <div class="mm-hero__card">
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="mm-actions">
                @if($heroHref)<a class="mm-button mm-button--primary" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
                @if($products)<a class="mm-button mm-button--line" href="#shop">Browse the shop</a>@endif
            </div>
        </div>
    </div>
</section>
@endif

@if($products)
<section id="shop" class="mm-section"{!! $at('products') !!}>
    <div class="mm-wrap">
        <div class="mm-head">
            <p class="mm-eyebrow">Shop</p>
            @if($txt($b['products']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['products']['heading'] }}</h2>@endif
        </div>
        <ul class="mm-products">
            @foreach($products as $p)
            @php $pImg = $img($p['image_path'] ?? null); $pHref = $link($p['url'] ?? null); @endphp
            <li class="mm-product">
                @if($pHref)<a class="mm-product__link" href="{{ $pHref }}">@endif
                <div class="mm-product__media">@if($pImg)<img src="{{ $pImg }}" alt="{{ $txt($p['image_alt'] ?? null) ?? $p['name'] }}" loading="lazy">@else<span aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $p['name']), 0, 1)) }}</span>@endif</div>
                <div class="mm-product__body">
                    <h3>{{ $p['name'] }}</h3>
                    @if($txt($p['price_text'] ?? null))<p class="mm-product__price">{{ $p['price_text'] }}</p>@endif
                    @if($txt($p['description'] ?? null))<p class="mm-product__desc">{{ $p['description'] }}</p>@endif
                </div>
                @if($pHref)</a>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="mm-section"{!! $at('reviews_strip') !!}>
    <div class="mm-wrap">
        <div class="mm-head">
            <p class="mm-eyebrow">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="mm-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <blockquote class="mm-review">
                @if($stars > 0)<p class="mm-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <p>{{ $r['text'] }}</p>
                @if($txt($r['author'] ?? null))<cite>{{ $r['author'] }}@if($txt($r['source'] ?? null)) · {{ $r['source'] }}@endif</cite>@endif
            </blockquote>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="story" class="mm-section mm-section--tint"{!! $at('about') !!}>
    <div class="mm-wrap mm-story{{ $aboutImg ? '' : ' mm-story--text' }}">
        @if($aboutImg)<img class="mm-story__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
        <div class="mm-story__body">
            <p class="mm-eyebrow">Our story</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
    </div>
</section>
@endif

@if($gallery)
<section class="mm-gallery-section"{!! $at('gallery') !!}>
    @if($txt($b['gallery']['heading'] ?? null))<div class="mm-wrap"><h2 class="mm-gallery-section__title"{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2></div>@endif
    <div class="mm-strip">@foreach(array_slice($gallery, 0, 6) as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
</section>
@endif

@if($faqs)
<section id="faq" class="mm-section mm-section--tint"{!! $at('faq') !!}>
    <div class="mm-wrap mm-faq">
        <div class="mm-head mm-head--left">
            <p class="mm-eyebrow">Help</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="mm-faq__list">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="mm-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

@if($cta)
<section class="mm-cta"{!! $at('cta_band') !!}>
    <div class="mm-wrap mm-cta__inner">
        <h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>
        @if($txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        @if($ctaHref)<a class="mm-button mm-button--primary" href="{{ $ctaHref }}"{!! $f('label') !!}>{{ $cta['label'] }}</a>@endif
    </div>
</section>
@endif

@if($form)
<section id="message" class="mm-section"{!! $at('form') !!}>
    <div class="mm-wrap">
        <div class="mm-head">
            <p class="mm-eyebrow">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="mm-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="mm-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="mm-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="mm-button mm-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="visit" class="mm-section mm-visit"{!! $at('contact') !!}>
    <div class="mm-wrap mm-visit__grid">
        <div>
            <p class="mm-eyebrow">Visit</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p class="mm-visit__address">{{ $address }}</p>@endif
        </div>
        @if($hours)
        <div>
            <h3>Shop hours</h3>
            <table class="mm-hours">
                @foreach($hours as $h)
                @if(is_array($h) && $txt($h['day'] ?? null))
                <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
                @endif
                @endforeach
            </table>
        </div>
        @endif
        @if($phone || $email)
        <div>
            <h3>Get in touch</h3>
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @endif
    </div>
</section>
</div>
