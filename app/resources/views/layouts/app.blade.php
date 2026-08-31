{{--
    The staff shell — Livewire's own default layout.

    ⚠️ **THIS FILE WAS MISSING, AND EVERY ADMIN SCREEN 500'd BECAUSE OF IT.**
    `component_layout` is `layouts::app` and the `layouts` namespace maps to
    `resources/views/layouts`, a directory that did not exist. ⚠️ **THIS
    PARAGRAPH SAID "`config/livewire.php` SETS" AND THERE IS NO SUCH FILE —
    CORRECTED 2026-08-24 (9288).** Both values are Livewire's own packaged
    defaults, unpublished, so the shell every staff screen renders in is
    selected by a file in `vendor/` — which is why *this* file's existence, and
    now its contents, are what the tests have to pin. The Setup components each
    declare `#[Layout('components.setup.layout')]`
    and render fine; **no component under `App\Livewire\Admin` declares one at
    all**, so all five fell through to a view that was not there and died with
    *"No hint path defined for [layouts]"*.

    ⚠️ Why nobody noticed: the admin tests exercise their components through
    `Livewire::test()`, which renders the component and never the layout, and
    the two tests that do issue a real GET assert `assertForbidden()` and
    `assertRedirect()` — both of which short-circuit in middleware, before
    anything renders. So the suite covered the gate thoroughly and the page not
    at all. It is decision 411's shape at the level of a whole screen: the
    assertions were real, and none of them was the one that would have failed.

    ⛔ **`28` §9.2's ROLE-FILTERED CONSOLE NAVIGATION IS HERE NOW, AND THIS
    PARAGRAPH SAID IT "BELONGS HERE EVENTUALLY" UNTIL 2026-08-24** (9288). It
    was pasted at the top of seventeen screen templates instead, one line each,
    and **three screens did not have the line** — `automation-runs`,
    `account-audit` and `staff-activity`. `LoginResponse` sends every member of
    staff to the first item the nav grants them, which is `automation-runs`, so
    on a fresh sign-in, before any Back history exists, a `super_admin`'s only
    route to any other console screen was typing a URL. **Measured over HTTP
    before the move: the landing page carried zero links to any other admin or
    support screen**, and so did the two audit explorers.

    ⚠️ **WHAT MAKES THAT UNREPRESENTABLE IS THE POSITION OF THE LINE, NOT A
    LINT.** `OwnerNavTest`'s *the navigation is rendered by the layout and
    pasted into no screen* pins the owner shell for exactly this reason and its
    comment named this file as the counterexample. A screen cannot now be built
    without a nav, because no screen renders one.

    ⚠️ **THE ARGUMENT THAT USED TO SIT ABOVE THE PASTED LINE IN
    `support/accounts.blade.php` IS THE ONE WORTH KEEPING**, and it moves here
    with the line it annotated: *without it this screen was reachable only by
    typing the URL — a door with no handle, which is decision 272's shape at the
    scale of a screen.* That comment was correct and it was in the one file that
    already had the nav; the three that did not had no comment to be correct in.

    ⚠️ The skip link comes with it (WCAG 2.2 AA). Seventeen console links now
    precede the content of every staff screen; the owner shell has had one since
    it was built and this shell never did, because until today the nav was
    inside the screen rather than ahead of it.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Staff surfaces are never indexed. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'GO AI EZ' }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-paper text-ink">
    {{-- Keyboard users reach the content without tabbing the whole nav (WCAG 2.2 AA). --}}
    <a
        href="#main"
        class="sr-only rounded-[--radius-control] bg-card px-4 py-2 focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:ring-2 focus:ring-ink"
    >Skip to content</a>

    {{--
        The grid every console screen used to declare for itself, in the one
        place it cannot be forgotten. The classes are the seventeen screens'
        own, merged with the `<main>` wrapper they sat inside — same two
        columns, same gap, same maximum width.
    --}}
    <div class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-8 lg:grid-cols-[16rem_1fr]">
        <x-admin.nav :groups="\App\Support\Admin\AdminNav::for(auth()->user())" />

        <main id="main" class="min-w-0">
            {{ $slot }}
        </main>
    </div>
    {{--
        ⛔ EVERY TOAST ON EVERY STAFF SCREEN RENDERED INTO NOTHING WITHOUT THIS,
        AND THE SYMPTOM WAS A BUTTON THAT LOOKED BROKEN — decision 5460.

        `masmerise/livewire-toaster` needs its hub in the layout. The account
        shell has had one since it was built; this one never did, so
        `Toaster::success()` and `Toaster::error()` were called correctly all
        over `app/Livewire/Admin` and delivered nowhere. What that costs is not
        cosmetic: `LegalDocuments::act()` catches the service's refusals *on
        purpose* and shows the message, because "published text is frozen"
        explains a rule that is otherwise impossible to guess — and the owner
        met exactly that, as a Publish button that did nothing, with no error
        and no log line, while the service was refusing with a paragraph saying
        precisely what to fix.

        ⚠️ NO TEST COULD HAVE CAUGHT IT AND THAT IS THE INTERESTING PART. Every
        admin test drives components through `Livewire::actingAs()->test()`,
        which runs no middleware and renders no layout (809), so the assertion
        `assertDispatched`/`Toaster` half passes while the delivery half does
        not exist. The regression test for this renders the real page over HTTP.

        ⛔ THAT SENTENCE STOPPED BEING TRUE ON 2026-08-26 AND IS KEPT AND DATED
        (10108). A test could have caught it, and now one does:
        `tests/Browser/AccountScreenTest.php` asks the RUNNING Alpine instance on
        this shell whether the hub initialised, and then puts words on the screen
        and reads them back. ⚠️ WHAT THE HTTP TEST CANNOT DO IS THE PART TO
        CARRY: `StaffToastsTest` asserts the string `toaster` is in this page's
        HTML, which is equally true of a page whose bundle never loaded — so it
        pins the markup and can never pin the delivery. A real `GET` is a
        statement about what was emitted, never about what ran.
    --}}
    <x-toaster-hub />
    @livewireScripts
</body>
</html>
