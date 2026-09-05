@props([
    'businessName',
    'jsonLd' => null,
])

{{--
    The shell for a tenant's own public review hub.

    A DELIBERATE SIBLING OF components/feedback/layout, NOT A REUSE OF IT. The
    two pages share an audience and every constraint below, and they differ in
    the two things a shell decides: this one carries a <title> that has to name
    the business *and* say what the page is (a review hub found in a search
    result is useless titled with a bare business name), and it carries a
    JSON-LD block that page has no use for. Reaching for the feedback shell would
    have meant adding two slots to a file whose whole argument is that it is
    narrow.

    THERE IS NO JAVASCRIPT ON THIS PAGE. Not "no framework" — none at all. The
    page is read-only, so there is nothing for scripting to add, and every
    assertion a test makes about the rendered markup is then an assertion about
    what a visitor actually sees.

    NO THIRD-PARTY REQUESTS, on the feedback page's own acceptance criterion
    (FPR-01) and for the same reason: this is a business's customers' words on a
    page we host, and a third-party asset here is us introducing a tracker to
    somebody else's customers on somebody else's behalf. @fonts self-hosts at
    build time, so it is not a third party. A feature test asserts the rendered
    markup references no external host.

    UNBRANDED, and that is a visible gap rather than a finished state — the same
    one components/feedback/layout records. No column in the schema holds a
    tenant's logo or colours, and adding one now would repeat what decision 311
    removed.

    ⛔ NOINDEX, AND THIS IS THE DECISION ON THIS PAGE MOST LIKELY TO BE READ AS
    AN OVERSIGHT (decision 6549). A review hub is a surface whose *point* is to
    be found, so `noindex` looks like a mistake. It is not. Three reasons, and
    only the first is inherited:

      1. The slug is the feedback page's slug. That page is noindex precisely so
         that a crawler finding one shared link cannot publish a directory of
         which businesses use this platform — and `/r/{slug}` is the same slug in
         the same directory, so indexing it would defeat that from the other
         side.

      2. The page's list is what the owner approved for display and the rating
         above it is over every review received. Both numbers are honest and the
         page says which is which — but Google's own rich-results policy requires
         an aggregate rating to be supported by review content *on the page*, and
         on a location where the owner has declined a review it is not. Publishing
         it for indexing is therefore a policy question, not a switch.

      3. The FTC's 2024 Rule on Consumer Reviews and Testimonials is live, and a
         freshly-signed tenant with three five-star reviews earning a rich-result
         snippet on their own brand query is the pattern it is about. That is the
         owner's risk to accept, in writing, not an agent's to default into.

    Flipping it is one attribute and the aggregate underneath is already the
    truthful one, which is the point of building it this way round: the
    compliance property is proven before any traffic arrives.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">

    <title>{{ __('review_hub.title', ['business' => $businessName]) }}</title>

    @if ($jsonLd !== null)
        {{--
            ⛔ Rendered with `{!! !!}` because it is JSON, not because it is
            trusted. json_encode() with JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|
            JSON_HEX_QUOT — applied where the string is built, in the view below
            — escapes `<`, `>`, `&`, `'` and `"` into \uXXXX sequences, so a
            business named `</script><script>` cannot close this element. Blade's
            own escaping would corrupt the JSON instead of protecting it.
        --}}
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endif

    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-paper font-sans text-base text-ink">
    <main class="mx-auto w-full max-w-2xl px-4 py-10 sm:py-16">
        <header class="mb-8">
            <p class="font-display text-2xl font-semibold tracking-tight">{{ $businessName }}</p>
        </header>

        {{ $slot }}
    </main>
</body>
</html>
