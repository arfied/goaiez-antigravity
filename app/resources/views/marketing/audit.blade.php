{{--
    A shared audit result (`29` §6.1's `/audit/{token}`).

    Server-rendered and `noindex`. Decision 192 made these link-shareable, never
    listed, and pruned after 90 days — so the controls are the unguessable token,
    the expiry and this meta tag, rather than a tenant boundary. The token being
    secret is not on its own a reason for a crawler to keep quiet about a page it
    was handed, so the page says so itself.

    Same partial as the home page, deliberately (decision 258). If the audit is
    still running when someone opens the link, the markup ships with
    `data-pending="true"` and the same poller finishes it.
--}}

<x-marketing.layout
    :title="$audit->name_snapshot ? 'What Google shows for '.$audit->name_snapshot : 'Your free audit'"
    description="A free check of a Google Business Profile, its reviews and its website."
    :noindex="true"
>
    <section
        class="mx-auto w-full max-w-3xl px-4 py-16"
        data-audit
        data-result-url="{{ route('audit.result', ['token' => '__TOKEN__']) }}"
    >
        <h1 class="text-center font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            What Google shows your customers
        </h1>

        <div data-audit-result-target class="mt-10">
            @include('marketing.partials.result', ['audit' => $audit])
        </div>

        <p class="mt-12 text-center text-base text-ink-2">
            Want your own? <a
                href="{{ route('home') }}"
                class="font-semibold text-ink underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
            >Check your business</a> — it is free and takes about twenty seconds.
        </p>
    </section>
</x-marketing.layout>
