{{--
    The confirmation page a person reaches by clicking the footer link — T176 P21.

    ⛔ THIS PAGE UNSUBSCRIBES NOBODY. Reaching it is a GET, and a GET is what
    mailbox scanners, corporate link rewriters and browser prefetchers issue for
    every URL in every email — decision 2920 records this application already
    paying that cost once, on `/f/{slug}/to/{destination}`, where a link
    checker's HEAD minted a full click record. The button below posts; the page
    itself only asks.

    ⚠️ NO `@csrf` TOKEN, AND ITS ABSENCE IS DELIBERATE RATHER THAN AN OVERSIGHT.
    The endpoint is exempt in `bootstrap/app.php` because RFC 8058 has a mail
    client post to it unattended, with no session to hold a token. Printing one
    here would suggest the endpoint checks it, and the next reader would build on
    a guarantee that is not there. What authorises the request is the sealed
    token in the URL, and nothing else.

    ⚠️ NO JAVASCRIPT. A person who has decided to stop hearing from somebody
    should not need a working script to say so, and this page is reached from a
    mail client's browser, which may be anything at all.

    noindex: an unsubscribe URL in a search index is somebody's address in a
    search index, sealed or not.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Stop receiving these emails') }} · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-paper font-sans text-base text-ink">
    <main class="mx-auto w-full max-w-lg px-4 py-16">
        @if ($isLive)
            <h1 class="font-display text-3xl font-semibold tracking-tight">
                Stop receiving these emails
            </h1>

            <p class="mt-3 text-ink-2">
                We will stop emailing this address. Nothing else about you changes, and
                you do not need an account to do this.
            </p>

            {{--
                The verb the button carries is the verb the outcome carries —
                `22`'s rule that an action keeps its verb through the flow. It
                says what will happen, not what the system will do.
            --}}
            <form method="POST" action="{{ route('mail.unsubscribe', ['token' => $token]) }}" class="mt-8">
                <button
                    type="submit"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-ink px-5 py-3 font-medium text-paper hover:bg-ink-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                >
                    Stop these emails
                </button>
            </form>
        @else
            {{--
                ⚠️ ONE MESSAGE FOR EVERY FAILURE — forged, edited, expired and
                never-valid alike. Naming which would tell whoever is probing
                which of their guesses got closer, and it would tell a real
                person nothing they can act on either way.
            --}}
            <h1 class="font-display text-3xl font-semibold tracking-tight">
                This link no longer works
            </h1>

            <p class="mt-3 text-ink-2">
                Open the most recent email you received and use the link at the bottom of
                that one. If you would rather not wait, reply to any of our emails and
                ask us to stop.
            </p>
        @endif
    </main>
</body>
</html>
