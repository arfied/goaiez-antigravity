{{-- Shop Bold — online shops, streetwear, gear, gadgets, makers with a bold brand: dark, bright yellow, products up front. A frozen layout: the AI fills the blocks, never this markup. --}}
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
    // The banner shows the shop's own product photos when it has four; otherwise the banner's photo.
    $mosaic = array_slice(array_values(array_filter($products, fn ($p) => $img($p['image_path'] ?? null) !== null)), 0, 4);
    $mosaic = count($mosaic) === 4 ? $mosaic : [];
    $nav = array_filter(['shop' => $products ? 'Shop' : null, 'about' => $about ? 'About' : null, 'reviews' => $reviews ? 'Reviews' : null, 'visit' => 'Contact']);
@endphp
<div class="sx" id="top">
<header class="sx-nav">
    <div class="sx-wrap sx-nav__row">
        <a class="sx-brand" href="#top">{{ $name }}</a>
        <nav class="sx-nav__links" aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        @if($products)<a class="sx-button sx-nav__shop" href="#shop">Shop</a>@endif
        <details class="sx-menu">
            <summary aria-label="Menu"><span></span><span></span></summary>
            <nav aria-label="Sections">@foreach($nav as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach</nav>
        </details>
    </div>
</header>

@if($hero)
<section class="sx-hero"{!! $at('hero') !!}>
    <div class="sx-wrap sx-hero__grid{{ $mosaic || $heroImg ? '' : ' sx-hero__grid--text' }}">
        <div class="sx-hero__body">
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="sx-actions">
                @if($products)<a class="sx-button sx-button--lg" href="#shop">Shop now</a>@endif
                @if($heroHref)<a class="sx-button sx-button--line sx-button--lg" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
            </div>
        </div>
        @if($mosaic)
        <div class="sx-mosaic">@foreach($mosaic as $m)<img src="{{ $img($m['image_path']) }}" alt="{{ $txt($m['image_alt'] ?? null) ?? $m['name'] }}">@endforeach</div>
        @elseif($heroImg)
        <img class="sx-hero__img" src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}">
        @endif
    </div>
</section>
@endif

@if($products)
<section id="shop" class="sx-section"{!! $at('products') !!}>
    <div class="sx-wrap">
        <div class="sx-head">
            <p class="sx-label">Shop</p>
            @if($txt($b['products']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['products']['heading'] }}</h2>@endif
        </div>
        <ul class="sx-products">
            @foreach($products as $p)
            @php $pImg = $img($p['image_path'] ?? null); $pHref = $link($p['url'] ?? null); @endphp
            <li class="sx-product">
                @if($pHref)<a class="sx-product__link" href="{{ $pHref }}">@endif
                <div class="sx-product__media">@if($pImg)<img src="{{ $pImg }}" alt="{{ $txt($p['image_alt'] ?? null) ?? $p['name'] }}" loading="lazy">@else<span aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $p['name']), 0, 1)) }}</span>@endif</div>
                <div class="sx-product__body">
                    <h3>{{ $p['name'] }}</h3>
                    @if($txt($p['price_text'] ?? null))<span class="sx-product__price">{{ $p['price_text'] }}</span>@endif
                </div>
                @if($pHref)</a>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="sx-section"{!! $at('reviews_strip') !!}>
    <div class="sx-wrap">
        <div class="sx-head">
            <p class="sx-label">Reviews</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="sx-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <blockquote>
                @if($stars > 0)<p class="sx-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
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
<section id="about" class="sx-section sx-section--card"{!! $at('about') !!}>
    <div class="sx-wrap sx-about{{ $aboutImg ? '' : ' sx-about--text' }}">
        <div class="sx-about__body">
            <p class="sx-label">About</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
        @if($aboutImg)<img class="sx-about__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
    </div>
</section>
@endif

@if($gallery)
<section class="sx-strip"{!! $at('gallery') !!} aria-label="Photos">
    @foreach(array_slice($gallery, 0, 6) as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach
</section>
@endif

@if($cta)
<section class="sx-cta"{!! $at('cta_band') !!}>
    <div class="sx-wrap sx-cta__row">
        <div>
            <h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>
            @if($txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        </div>
        @if($ctaHref)<a class="sx-button sx-button--dark sx-button--lg" href="{{ $ctaHref }}"{!! $f('label') !!}>{{ $cta['label'] }}</a>@endif
    </div>
</section>
@endif

@if($faqs)
<section id="faq" class="sx-section"{!! $at('faq') !!}>
    <div class="sx-wrap sx-faq">
        <div class="sx-head">
            <p class="sx-label">Help</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="sx-faq__list">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="sx-q" data-question="{{ trim((string) $q['question']) }}">
                <summary{!! $f('items.'.(int) $qi.'.question') !!}>{{ trim((string) $q['question']) }}</summary>
                <p{!! $f('items.'.(int) $qi.'.answer') !!}>{{ trim((string) $q['answer']) }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif

<section id="visit" class="sx-section sx-section--card"{!! $at('contact') !!}>
    <div class="sx-wrap sx-visit">
        <div>
            <p class="sx-label">Contact</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
        </div>
        <div class="sx-visit__links">
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @if($hours)
        <table class="sx-hours">
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
