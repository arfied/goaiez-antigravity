{{--
    Where an owner lands after holding a page from their email.

    ⚠️ OUTCOME LANGUAGE (`22`). It names what happened to their business rather
    than what the system did — not "hold recorded", which describes our row.

    ⚠️ NO TENANT DATA AND NO PAGE TITLE, AND THAT IS THE SIGNED-URL DECISION
    ARRIVING IN THE MARKUP. Whoever fetched this URL proved only that they hold
    a signature; a corporate mail scanner renders this too. The page it refers
    to is named in the email they already have, so repeating it here would put a
    tenant's own page title on a surface with no session behind it.

    ⚠️ `noindex`. A signed one-off URL has nothing to gain from a crawler, and
    `robots` is the cheap half of keeping it out of a search result.
--}}
<x-marketing.layout title="We are holding that page" :noindex="true"
    description="Your page is waiting for you.">
    <div class="mx-auto max-w-xl px-6 py-24 text-center">
        <h1 class="font-display text-3xl font-semibold tracking-tight">We are holding that page</h1>

        <p class="mt-4 text-ink-2">
            It will not go on your website until you say so. Nothing else has changed, and the rest of
            your account carries on as normal.
        </p>

        <p class="mt-4 text-ink-2">You can close this page.</p>
    </div>
</x-marketing.layout>
