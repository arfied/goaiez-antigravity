{{-- Corner Boutique — boutiques, gift shops, florists, bakeries that sell online: serif, warm, products as the centrepiece. A frozen layout: the AI fills the blocks, never this markup. --}}
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
    $nav = array_filter(['shop' => $products ? 'Shop' : null, 'story' => $about ? 'Our story' : null, 'reviews' => $reviews ? 'Kind words' : null, 'visit' => 'Visit us']);
    $form = is_array($b['form'] ?? null) && is_array($b['form']['fields'] ?? null) && $txt($b['form']['definition_id'] ?? null) && $formBase !== '' ? $b['form'] : null;
    $navLinks = $pages !== [] ? $pages : array_map(static fn (string $id, string $label): array => ['label' => $label, 'href' => '#'.$id, 'current' => false], array_keys($nav), array_values($nav));
@endphp
<div class="cb" id="top">
<header class="cb-nav">
    <div class="cb-wrap cb-nav__row">
        <nav class="cb-nav__links" aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
        <a class="cb-brand" href="#top">{{ $name }}</a>
        <div class="cb-nav__end">
            @if($products)<a class="cb-button cb-button--small" href="#shop">Shop now</a>@endif
            <details class="cb-menu">
                <summary aria-label="Menu"><span></span><span></span></summary>
                <nav aria-label="Sections">@foreach($navLinks as $navLink)<a href="{{ $navLink['href'] }}"@if($navLink['current']) aria-current="page"@endif>{{ $navLink['label'] }}</a>@endforeach</nav>
            </details>
        </div>
    </div>
</header>

@if($hero)
<section class="cb-hero{{ $heroImg ? '' : ' cb-hero--text' }}"{!! $at('hero') !!}>
    <div class="cb-wrap cb-hero__grid">
        <div class="cb-hero__body">
            <h1{!! $f('headline') !!}>{{ $hero['headline'] }}</h1>
            @if($txt($hero['subline'] ?? null))<p{!! $f('subline') !!}>{{ $hero['subline'] }}</p>@endif
            <div class="cb-actions">
                @if($products)<a class="cb-button" href="#shop">Browse the shop</a>@endif
                @if($heroHref)<a class="cb-link" href="{{ $heroHref }}"{!! $f('cta_label') !!}>{{ $hero['cta_label'] }}</a>@endif
            </div>
        </div>
        @if($heroImg)<figure class="cb-hero__media"><img src="{{ $heroImg }}" alt="{{ $txt($hero['image_alt'] ?? null) ?? '' }}"></figure>@endif
    </div>
</section>
@endif

@if($products)
<section id="shop" class="cb-section"{!! $at('products') !!}>
    <div class="cb-wrap">
        <div class="cb-head">
            <p class="cb-script">the shop</p>
            @if($txt($b['products']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['products']['heading'] }}</h2>@endif
        </div>
        <ul class="cb-products">
            @foreach($products as $p)
            @php $pImg = $img($p['image_path'] ?? null); $pHref = $link($p['url'] ?? null); @endphp
            <li class="cb-product">
                @if($pHref)<a class="cb-product__link" href="{{ $pHref }}">@endif
                <div class="cb-product__media">@if($pImg)<img src="{{ $pImg }}" alt="{{ $txt($p['image_alt'] ?? null) ?? $p['name'] }}" loading="lazy">@else<span aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $p['name']), 0, 1)) }}</span>@endif</div>
                <h3>{{ $p['name'] }}</h3>
                @if($txt($p['price_text'] ?? null))<p class="cb-product__price">{{ $p['price_text'] }}</p>@endif
                @if($pHref)</a>@endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($reviews)
<section id="reviews" class="cb-section"{!! $at('reviews_strip') !!}>
    <div class="cb-wrap">
        <div class="cb-head">
            <p class="cb-script">kind words</p>
            @if($txt($b['reviews_strip']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['reviews_strip']['heading'] }}</h2>@endif
        </div>
        <div class="cb-reviews">
            @foreach(array_slice($reviews, 0, 3) as $r)
            @php $stars = is_numeric($r['rating'] ?? null) ? max(0, min(5, (int) round((float) $r['rating']))) : 0; @endphp
            <blockquote>
                @if($stars > 0)<p class="cb-stars" role="img" aria-label="{{ $stars }} out of 5 stars">{{ str_repeat('★', $stars) }}</p>@endif
                <p>“{{ $r['text'] }}”</p>
                @if($txt($r['author'] ?? null))<cite>{{ $r['author'] }}</cite>@endif
            </blockquote>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($about)
@php $aboutImg = $img($about['image_path'] ?? null); @endphp
<section id="story" class="cb-section cb-section--tint"{!! $at('about') !!}>
    <div class="cb-wrap cb-story{{ $aboutImg ? '' : ' cb-story--text' }}">
        @if($aboutImg)<img class="cb-story__img" src="{{ $aboutImg }}" alt="{{ $txt($about['image_alt'] ?? null) ?? '' }}" loading="lazy">@endif
        <div class="cb-story__body">
            <p class="cb-script">our story</p>
            @if($txt($about['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $about['heading'] }}</h2>@endif
            <p{!! $f('text') !!}>{{ $about['text'] }}</p>
        </div>
    </div>
</section>
@endif

@if($gallery)
<section class="cb-section cb-section--flush"{!! $at('gallery') !!}>
    <div class="cb-wrap">
        @if($txt($b['gallery']['heading'] ?? null))<h2 class="cb-center"{!! $f('heading') !!}>{{ $b['gallery']['heading'] }}</h2>@endif
        <div class="cb-gallery">@foreach(array_slice($gallery, 0, 5) as $g)<img src="{{ $img($g['image_path']) }}" alt="{{ $txt($g['alt'] ?? null) ?? '' }}" loading="lazy">@endforeach</div>
    </div>
</section>
@endif

@if($cta)
<section class="cb-cta"{!! $at('cta_band') !!}>
    <div class="cb-wrap cb-cta__inner">
        <h2{!! $f('heading') !!}>{{ $cta['heading'] }}</h2>
        @if($txt($cta['text'] ?? null))<p{!! $f('text') !!}>{{ $cta['text'] }}</p>@endif
        @if($ctaHref)<a class="cb-button" href="{{ $ctaHref }}"{!! $f('label') !!}>{{ $cta['label'] }}</a>@endif
    </div>
</section>
@endif

@if($videos)
<section id="video" class="cb-section"{!! (count($videos) === 1 ? $videos[0]['at'] : '') !!}>
    <div class="cb-wrap cb-narrow">
        <div class="cb-head">
            <p class="cb-script">video</p>
            <h2{!! count($videos) === 1 ? $f('name') : '' !!}>{{ count($videos) === 1 ? $videos[0]['name'] : 'Videos' }}</h2>
        </div>
        <div id="videos-x176" class="cb-videos" style="display:grid;gap:1.5rem">
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
<section id="faq" class="cb-section"{!! $at('faq') !!}>
    <div class="cb-wrap cb-narrow">
        <div class="cb-head">
            <p class="cb-script">good to know</p>
            @if($txt($b['faq']['heading'] ?? null))<h2{!! $f('heading') !!}>{{ $b['faq']['heading'] }}</h2>@endif
        </div>
        <div id="faq-x176" class="cb-faq">
            @foreach($b['faq']['items'] as $qi => $q)
            @if(is_array($q) && $txt($q['question'] ?? null) && $txt($q['answer'] ?? null))
            <details class="cb-q" data-question="{{ trim((string) $q['question']) }}">
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
<section id="message" class="cb-section"{!! $at('form') !!}>
    <div class="cb-wrap">
        <div class="cb-head">
            <p class="cb-script">Message</p>
            <h2>Send us a message</h2>
        </div>
        <form class="cb-form" method="post" action="{{ $formBase }}/forms/{{ $form['definition_id'] }}">
            @foreach($form['fields'] as $field)
            @if(is_array($field) && $txt($field['name'] ?? null))
            @php $fieldId = 'form-'.$form['definition_id'].'-'.$field['name']; $fieldType = in_array($field['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'date'], true) ? $field['type'] : 'text'; @endphp
            <label class="cb-form__field" for="{{ $fieldId }}"><span>{{ $txt($field['label'] ?? null) ?? $field['name'] }}</span>@if(($field['type'] ?? null) === 'textarea')<textarea id="{{ $fieldId }}" name="{{ $field['name'] }}" rows="4"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif></textarea>@else<input id="{{ $fieldId }}" name="{{ $field['name'] }}" type="{{ $fieldType }}"@if(in_array($field['name'], (array) ($form['required'] ?? []), true)) required @endif>@endif</label>
            @endif
            @endforeach
            @if($txt($form['honeypot'] ?? null))<input class="cb-form__trap" type="text" name="{{ $form['honeypot'] }}" tabindex="-1" autocomplete="off" aria-hidden="true">@endif
            <button type="submit" class="cb-button cb-form__send">Send</button>
        </form>
    </div>
</section>
@endif

<section id="visit" class="cb-section cb-section--tint cb-visit"{!! $at('contact') !!}>
    <div class="cb-wrap cb-visit__grid">
        <div>
            <p class="cb-script">visit us</p>
            @if($name !== '')<h2>{{ $name }}</h2>@endif
            @if($address)<p>{{ $address }}</p>@endif
        </div>
        @if($hours)
        <table class="cb-hours">
            @foreach($hours as $h)
            @if(is_array($h) && $txt($h['day'] ?? null))
            <tr><th scope="row">{{ $h['day'] }}</th><td>{{ $txt($h['close'] ?? null) === null ? 'Closed' : ($h['open'] ?? '').' – '.$h['close'] }}</td></tr>
            @endif
            @endforeach
        </table>
        @endif
        @if($phone || $email)
        <div class="cb-visit__contact">
            @if($phone)<p><a href="{{ $tel }}">{{ $phone }}</a></p>@endif
            @if($email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        </div>
        @endif
    </div>
</section>
</div>
