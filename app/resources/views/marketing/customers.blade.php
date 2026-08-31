{{--
    Customer stories (CC-2 §2.8).

    ⛔ THERE ARE NO STORIES AND THE PAGE SAYS SO. Decision 260 kept the reference
    site's three invented testimonials and its animated counters off the home page,
    and `29` §6.1 requires proof to be real platform aggregates with k>=8 cohorts.
    There are no customers yet, so there is nothing to render — and CC-2 §2.8
    supplies the empty state for exactly this state of the world.

    ⛔ NO TABLE WAS INVENTED TO COUNT (decision 5199). A `case_studies` table with
    no writer would render "0 stories" today and would be one of this codebase's
    sixteen writerless controls tomorrow. When a signed study exists, the slice that
    publishes it brings its own store — and this page's empty state is what it
    replaces.

    ⚠️ THE FOOTER IS THE LOAD-BEARING SENTENCE AND IT IS VERBATIM. Written
    permission, no composites — the rule that has to be true before the first story
    is written, rather than remembered when it is.
--}}

<x-marketing.layout
    title="Real customers. Real numbers. Their words."
    description="Named businesses, dated results and written permission — no composites, and nothing published before it is signed."
>
    <section class="mx-auto w-full max-w-3xl px-4 pt-16 pb-8 text-center">
        <h1 class="font-display text-4xl font-semibold tracking-tight text-balance text-ink sm:text-5xl">
            Real customers. Real numbers. Their words.
        </h1>
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-16" aria-labelledby="stories">
        <h2 id="stories" class="sr-only">Customer stories</h2>

        <x-ui.empty-state
            heading="First stories land after launch — every one named, dated, and signed."
            action="Run the free check on your own business"
            :href="route('home')"
            icon="◇"
        >
            Until then, the honest proof is the one you can run yourself: type your business
            name and see what Google shows your customers.
        </x-ui.empty-state>

        {{-- CC-2 §2.8's footer, VERBATIM. --}}
        <p class="mt-10 text-base text-ink-2">
            Every story here is published with written permission — no composites, ever.
        </p>
    </section>
</x-marketing.layout>
