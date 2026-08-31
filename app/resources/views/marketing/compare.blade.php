@php
    use App\Enums\MarketingCapability;
@endphp

{{--
    The honest table (`29` §6.1's /compare/seo-company, CC-2 §2.4).

    ⛔ EVERY COMPETITOR CELL IS A `[VERIFY-AT-PUBLISH]` PLACEHOLDER AND RENDERS AS
    "Checking". Nobody has read another company's public pricing page for this
    table, and a plausible cross in a rival's column is a claim about somebody
    else's product that we would have invented — which is the same failure as
    decision 260's fabricated testimonials with the sign flipped. The cells fill at
    publish under the thirty-day rule the footer states, and the footer is what
    makes "Checking" honest rather than lazy.

    ⚠️ OUR COLUMN IS `MarketingCapability`, WHICH IS THE SAME SOURCE `/features`
    RENDERS. A table with its own opinion about what we ship is how a comparison
    page ends up claiming a store nobody built.

    ⚠️ A DARK CAPABILITY STILL GETS A ROW HERE, WHERE IT GETS NO SECTION ON
    `/features` — and the asymmetry is the point of the two pages. A features page
    lists what we do; a comparison table earns its name by also listing what we do
    not.
--}}

<x-marketing.layout
    title="GOAIEZ vs the alternatives — the honest table"
    description="What we do, what we do not do yet, and what the alternatives do — checked against their own published pricing, or it says so."
>
    <section class="mx-auto w-full max-w-3xl px-4 pt-16 pb-8 text-center">
        <h1 class="font-display text-4xl font-semibold tracking-tight text-balance text-ink sm:text-5xl">
            The honest table.
        </h1>

        <p class="mx-auto mt-5 max-w-xl text-lg text-ink-2">
            Our column is what the product does today. Their column is what their own
            published pricing says — and where we have not checked recently, it says so.
        </p>
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-16">
        <table class="w-full border-collapse text-left">
            <caption class="sr-only">
                What GO AI EZ does compared with the alternatives
            </caption>

            <thead>
                <tr class="border-b border-rule-strong">
                    <th scope="col" class="py-3 pr-3 text-base font-semibold text-ink">What it does</th>
                    <th scope="col" class="px-3 py-3 text-base font-semibold text-ink">{{ config('app.name') }}</th>
                    <th scope="col" class="py-3 pl-3 text-base font-semibold text-ink">The alternatives</th>
                </tr>
            </thead>

            {{--
                Two of the seven rows carry a null capability. They are facts about
                how we bill rather than things the product does, so there is no flag
                to read: they are true of every plan on `/pricing` and they are the
                same sentence that page makes.
            --}}
            <tbody>
                @foreach ([
                    ['60-second missed-call text-back', MarketingCapability::TextBack->value],
                    ['Quotes only from your price list', MarketingCapability::FrontDesk->value],
                    ['Unhappy-review private catch', MarketingCapability::Reviews->value],
                    ['Cancel in about a minute', null],
                    ['Month-to-month, no lock-in', null],
                    ['Online store included', MarketingCapability::Commerce->value],
                    ['Ready-made campaigns included', MarketingCapability::Campaigns->value],
                ] as [$label, $key])
                    @php($ours = $key === null || $capabilities[$key])

                    <tr class="border-b border-rule align-top">
                        <th scope="row" class="py-4 pr-3 text-base font-normal text-ink">{{ $label }}</th>

                        <td class="px-3 py-4 text-base text-ink-2">
                            {{-- Never colour alone: the word carries the answer (`22`). --}}
                            <span aria-hidden="true">{{ $ours ? '✓' : '–' }}</span>
                            {{ $ours ? 'Yes' : 'Not yet' }}
                        </td>

                        <td class="py-4 pl-3 text-base text-ink-3">
                            {{-- [VERIFY-AT-PUBLISH] — see the file header. --}}
                            Checking
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- CC-2 §2.4's footer, VERBATIM. --}}
        <p class="mt-6 text-base text-ink-2">
            Every competitor cell is checked against their public pricing within the last 30 days — or it says 'checking'.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button :href="route('start')">Start free</x-ui.button>
            <x-ui.button :href="route('pricing')" variant="secondary">See the prices</x-ui.button>
        </div>
    </section>
</x-marketing.layout>
