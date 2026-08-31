{{--
    GoAiEzBot's disclosure page — the `+URL` in `RobotsPolicy::USER_AGENT`.

    ⚠️ EVERY CLAIM ON THIS PAGE IS A CLAIM ABOUT CODE THAT EXISTS, and that is
    the whole standard for editing it. A webmaster reads this to decide whether
    to block us; a sentence here that overstates what the crawler does is not
    marketing copy, it is a misrepresentation made to somebody deciding about
    their own server. Each number below is pinned in `BotPageTest` against the
    constant it came from, so a change to the gateway that makes a sentence here
    false reddens the build.

    The `User-agent` token is rendered from `RobotsPolicy::userAgentToken()`, not
    typed. It is the one string a reader copies into their own robots.txt, and a
    hand-typed copy that drifts produces a rule matching nothing.

    NO AUDIENCE-SPLITTING AND NO CTA. `29` §2 forbids cloaking, and the spirit of
    that rule applies to the page as much as the fetch: this is addressed to a
    person who found our name in their access log, and turning it into a funnel
    is how a disclosure stops being read as one. The only link out is the one a
    webmaster might actually want.
--}}

<x-marketing.layout
    title="GoAiEzBot"
    description="What GoAiEzBot is, what it fetches, and how to block it."
>
    <article class="mx-auto w-full max-w-2xl px-4 py-16">
        <h1 class="font-display text-3xl font-semibold tracking-tight text-ink">GoAiEzBot</h1>

        <p class="mt-4 text-lg text-ink-2">
            You are probably here because you found this name in your server logs. This page
            says what it was doing there and how to stop it.
        </p>

        <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">How to identify it</h2>

        <p class="mt-3 text-base text-ink-2">
            Every request sends this exact User-Agent header:
        </p>

        <pre class="mt-3 overflow-x-auto rounded-[--radius-card] border border-rule bg-card px-4 py-3 font-mono text-sm text-ink"><code>{{ $userAgent }}</code></pre>

        <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">How to block it</h2>

        <p class="mt-3 text-base text-ink-2">
            Add this to your <span class="font-mono text-sm">robots.txt</span>. It takes effect
            within an hour, which is how long a copy of your
            <span class="font-mono text-sm">robots.txt</span> is kept before being fetched again.
        </p>

        <pre class="mt-3 overflow-x-auto rounded-[--radius-card] border border-rule bg-card px-4 py-3 font-mono text-sm text-ink"><code>User-agent: {{ $token }}
Disallow: /</code></pre>

        <p class="mt-3 text-base text-ink-2">
            There is no setting anywhere in our product that overrides this, for any customer,
            on any plan. The column that records whether a source honours
            <span class="font-mono text-sm">robots.txt</span> carries a database constraint
            pinning it true, so the value cannot be changed to <em>no</em> by a support ticket
            or a configuration edit.
        </p>

        <p class="mt-3 text-base text-ink-2">
            If we cannot read your <span class="font-mono text-sm">robots.txt</span> at all —
            it times out, returns an error, or is too large to parse — we treat that as a block
            and do not fetch. A <span class="font-mono text-sm">robots.txt</span> that returns
            404 is treated as permission, which is what the standard says it means.
        </p>

        <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">What it fetches, and why</h2>

        <p class="mt-3 text-base text-ink-2">
            GO AI EZ runs a free check of a local business's online presence. Part of that check
            reads the business's own public homepage to see whether the name, address and phone
            number shown there match the ones on their Google listing — a mismatch is one of the
            most common reasons a business is hard to find.
        </p>

        <p class="mt-3 text-base text-ink-2">
            So a fetch of your site happens because somebody asked us to check that business.
            We do not crawl the open web, and we do not build an index.
        </p>

        <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">What it does not do</h2>

        <ul class="mt-3 space-y-2 text-base text-ink-2">
            <li>
                <strong class="text-ink">It does not follow links.</strong>
                One request fetches one page. There is no queue of discovered URLs, so a fetch
                of your homepage cannot become a fetch of anything else on your site.
            </li>
            <li>
                <strong class="text-ink">It does not run JavaScript.</strong>
                It is a plain HTTP GET. No headless browser, no rendering, no requests for your
                scripts, stylesheets, images or fonts.
            </li>
            <li>
                <strong class="text-ink">It does not log in, submit forms, or send anything.</strong>
                GET only. It never posts, and it carries no cookies from a previous request.
            </li>
            <li>
                <strong class="text-ink">It does not identify itself as a browser.</strong>
                The header above is the only one it ever sends. Serving us different content
                than you serve a person is something you are free to do, but you do not have to
                guess who we are to do it.
            </li>
        </ul>

        <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">How politely</h2>

        <ul class="mt-3 space-y-2 text-base text-ink-2">
            <li>Each site has a request budget, and once it is spent no further request is made until it refills.</li>
            <li>A request is abandoned after 10 seconds. We follow at most 3 redirects and read at most 2 MB.</li>
            <li>
                When a site returns errors, we back off on a ladder — 6 hours, then 24, then 72 —
                before trying again. A site that is having a bad day does not get retried into a
                worse one.
            </li>
        </ul>

        {{--
            Rendered only when a mailbox is actually configured. Printing an
            address nobody monitors would rebuild, one paragraph further down,
            the exact defect this page was created to close — see
            config/fetch.php.
        --}}
        @if ($contactEmail = config('fetch.contact_email'))
            <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">If you would rather talk to somebody</h2>

            <p class="mt-3 text-base text-ink-2">
                Email <a href="mailto:{{ $contactEmail }}" class="text-ink underline underline-offset-2 focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none">{{ $contactEmail }}</a>
                with the log lines and we will tell you what was fetched and when. Blocking in
                <span class="font-mono text-sm">robots.txt</span> is faster and does not need us
                to do anything.
            </p>
        @endif
    </article>
</x-marketing.layout>
