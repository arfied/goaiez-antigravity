{{--
    A legal document, or an honest note that it does not exist yet.

    `29` §6.1: placeholders until counsel, per prelaunch gate 1. The honest
    placeholder states its own status; it does not approximate terms.

    ⚠️ THE BODY IS ESCAPED, NOT RENDERED AS HTML OR MARKDOWN. The text is written
    by an admin and reviewed by counsel, and neither is a reason to hand a
    stored string to the browser as markup: the review that happened was of the
    words, not of the HTML. Paragraph breaks survive through `whitespace-pre-line`
    rather than through a parser, which costs formatting and buys the guarantee
    that nothing in a legal document can execute. Rich rendering is a later
    decision that has to be made deliberately.

    ⛔ NOINDEX WHILE UNPUBLISHED **OR STILL A PLACEHOLDER** — owner ruling
    2026-08-18, decision 5386, closing 5383.

    The first half was already here and its reasoning is unchanged: a search
    result promising terms and delivering a note about their absence is worse
    than no search result. ⚠️ WHAT IT MISSED IS THE STATE PRODUCTION WAS
    ACTUALLY IN. `39`'s checklist seeds every document as a **published
    placeholder**, so `isPublished()` answered true for all thirteen while every
    body still carried counsel's brackets — `Effective: [DATE] · [COMPANY LEGAL
    NAME], a [STATE] [ENTITY TYPE]` — and the page was indexable. The banner
    below has been saying "this version is a working draft" to a crawler that
    was welcome to publish it under our own name.

    ⚠️ THE TWO CONDITIONS ARE ONE QUESTION ASKED TWICE, NOT TWO RULES: may a
    stranger who finds this in a search result rely on it? A missing document
    and a bracketed one both answer no. `is_placeholder` is the column `39`
    step 5 already makes the input to the wide-launch gate, so this reads the
    flag that exists rather than inventing a second one.

    ⚠️ A NOINDEX IS NOT A REFUSAL TO SERVE, WHICH MATTERS FOR THE TWO 10DLC
    ADDRESSES. `24` §3.1 has a carrier reviewer loading `/sms-terms` and
    `/privacy` by URL; they fetch, they do not search, so the tag costs that
    review nothing. What it stops is the page arriving in front of somebody who
    was not sent to it.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{--
        The SMS terms and the privacy policy answer at `/sms-terms` and
        `/privacy` as well as here, because those are the addresses a 10DLC
        campaign registration files and a person may retype. One document at two
        addresses is a duplicate unless it names which one is the document's own,
        and this is that name — always `/legal/{doc}`, never the requested URL.
    --}}
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @unless ($document?->isPublished() && ! $document->is_placeholder)
        <meta name="robots" content="noindex, nofollow">
    @endunless
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-paper font-sans text-base text-ink">
    <main class="mx-auto w-full max-w-2xl px-4 py-16">
        <h1 class="font-display text-3xl font-semibold tracking-tight">{{ $title }}</h1>

        @if ($document?->isPublished())
            <p class="mt-2 text-sm text-ink-2">
                Version {{ $document->version }} ·
                <time datetime="{{ $document->published_at->toDateString() }}">
                    {{ $document->published_at->format('j F Y') }}
                </time>
            </p>

            @if ($document->is_placeholder)
                {{--
                    A published placeholder is a real state, not a contradiction:
                    `39`'s checklist seeds every document as a placeholder and
                    clears the flag per document as counsel signs it off. Saying
                    so is the whole point of the column.
                --}}
                <p class="mt-6 rounded-lg bg-signal-attention/10 px-4 py-3 text-ink-2">
                    This version is a working draft published for reference. It is
                    not final.
                </p>
            @endif

            {{--
                ⛔ **`{!! !!}` IS DELIBERATE AND ITS SAFETY LIVES IN
                `LegalMarkdown`, NOT HERE** (5710). This printed
                `{{ $document->body }}` inside `whitespace-pre-line` until
                2026-08-20, so counsel's Markdown reached the reader as the
                literal characters `##` and `**`. The converter strips raw HTML
                and refuses unsafe link schemes; **do not inline
                `Str::markdown()` here** — the options are the whole of the
                safety and a template is where one of them gets dropped during
                a layout edit.
            --}}
            <div class="legal-prose mt-8 text-ink-2">{!! $bodyHtml !!}</div>
        @else
            <p class="mt-6 text-ink-2">
                This document is not final. {{ config('app.name') }} has not launched, and its
                legal terms are with counsel. This page exists so that every link in
                our consent wording resolves to something honest rather than to
                nothing.
            </p>

            <p class="mt-4 text-ink-2">
                If you need to reach us about your information before this is
                published, reply to any message you have received from us.
            </p>
        @endif
    </main>
</body>
</html>
