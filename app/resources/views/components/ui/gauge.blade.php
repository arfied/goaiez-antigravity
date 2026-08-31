@props([
    'score' => null,
    'sentence' => null,
    'delta' => null,
    'label' => 'Overall',
])

@php
    use App\Enums\SignalState;

    $state = SignalState::fromScore($score === null ? null : (int) $score);
    $measured = $state !== SignalState::Unknown;
    $sentence ??= $state->sentence();

    // The arc: 240 degrees, from 210 down to -30, so it opens at the bottom and
    // reads left to right like every dial anyone has used.
    $centre = 100;
    $radius = 80;
    $sweep = 240;
    $start = 210;

    $point = static function (float $degrees, float $r) use ($centre): array {
        $rad = deg2rad($degrees);

        // SVG's y grows downward, so sin is subtracted rather than added.
        return [$centre + $r * cos($rad), $centre - $r * sin($rad)];
    };

    [$arcStartX, $arcStartY] = $point($start, $radius);
    [$arcEndX, $arcEndY] = $point($start - $sweep, $radius);

    // The machined tick scale of `29` §5.3 — every 5, longer every 25.
    $ticks = [];

    for ($value = 0; $value <= 100; $value += 5) {
        $major = $value % 25 === 0;
        $degrees = $start - ($value / 100) * $sweep;

        [$x1, $y1] = $point($degrees, $radius + 6);
        [$x2, $y2] = $point($degrees, $radius + ($major ? 15 : 11));

        $ticks[] = compact('x1', 'y1', 'x2', 'y2', 'major');
    }
@endphp

{{--
    The Status Instrument (`29` §5.3) — the signature element, reused by the
    public audit page, the dashboard and every report.

    "Sub-3-second glanceability is the acceptance test", which decides three
    things that would otherwise look like preferences:

    THE NUMBER IS THE LARGEST THING and it is set in Archivo Expanded with
    tabular figures, so it does not reflow as it animates or change width
    between 88 and 100.

    THE SENTENCE IS NEVER A METRIC. §5.3 says so outright. "Everything is
    running" is readable in the time a percentage is still being interpreted,
    and it is the half of the instrument that survives being colour-blind.

    A NULL SCORE IS NOT A ZERO. Row 2 slice E returns null when nothing could be
    measured (decision 227), and an empty dial reads as nought out of a hundred —
    the worst possible score — for what is actually our failure to look. Null
    renders a dash, a grey track, and a sentence that says so.

    Motion: one 400ms sweep on load, per the note in `resources/css/app.css`.
    prefers-reduced-motion is honoured globally there rather than here.
--}}

<figure {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3']) }}>
    <div class="relative w-full max-w-[18rem]">
        <svg
            viewBox="0 0 200 165"
            class="w-full"
            role="img"
            aria-label="{{ $label }}: {{ $measured ? $score.' out of 100' : 'not checked' }}. {{ $state->label() }}."
        >
            {{-- Ticks first, so the arc sits over them. --}}
            <g stroke="currentColor" class="text-ink-3" stroke-linecap="round">
                @foreach ($ticks as $tick)
                    <line
                        x1="{{ round($tick['x1'], 2) }}"
                        y1="{{ round($tick['y1'], 2) }}"
                        x2="{{ round($tick['x2'], 2) }}"
                        y2="{{ round($tick['y2'], 2) }}"
                        stroke-width="{{ $tick['major'] ? 2 : 1 }}"
                        opacity="{{ $tick['major'] ? 0.9 : 0.45 }}"
                    />
                @endforeach
            </g>

            {{--
                pathLength="100" normalises the arc so stroke-dasharray takes the
                score directly. Without it every change to the radius or the
                sweep would need the arc length recomputed by hand, and the fill
                would be quietly wrong rather than obviously broken.
            --}}
            <path
                d="M {{ round($arcStartX, 2) }} {{ round($arcStartY, 2) }}
                   A {{ $radius }} {{ $radius }} 0 1 1 {{ round($arcEndX, 2) }} {{ round($arcEndY, 2) }}"
                fill="none"
                stroke="var(--color-gauge-track)"
                stroke-width="14"
                stroke-linecap="round"
                pathLength="100"
            />

            @if ($measured)
                <path
                    d="M {{ round($arcStartX, 2) }} {{ round($arcStartY, 2) }}
                       A {{ $radius }} {{ $radius }} 0 1 1 {{ round($arcEndX, 2) }} {{ round($arcEndY, 2) }}"
                    fill="none"
                    stroke="{{ $state->strokeVar() }}"
                    stroke-width="14"
                    stroke-linecap="round"
                    pathLength="100"
                    {{--
                        A dash covering the whole normalised path, then an equal
                        gap, offset back by however much of the arc is unearned.
                        Positions 0 to $score are drawn and nothing else is,
                        which is why the number and the arc cannot disagree.
                    --}}
                    stroke-dasharray="100 100"
                    style="stroke-dashoffset: {{ 100 - (int) $score }}; animation: goaiez-gauge-sweep 400ms ease-out 1"
                />
            @endif

            {{-- The reading. Centred in the arc's opening, not the viewBox. --}}
            <text
                x="100"
                y="108"
                text-anchor="middle"
                class="font-display fill-ink"
                style="font-size: 46px; font-weight: 600; font-variant-numeric: tabular-nums; letter-spacing: -0.02em"
            >{{ $measured ? (int) $score : '—' }}</text>

            @if ($measured)
                <text
                    x="100"
                    y="128"
                    text-anchor="middle"
                    class="fill-ink-3"
                    style="font-size: 13px; font-weight: 500; letter-spacing: 0.06em"
                >OUT OF 100</text>
            @endif
        </svg>
    </div>

    {{--
        The icon and the word are the non-colour half of the signal, and they
        are why the arc is allowed to be coloured at all.
    --}}
    <div class="flex items-center gap-2 text-sm font-semibold {{ $state->textClass() }}">
        <span aria-hidden="true">{{ $state->icon() }}</span>
        <span>{{ $state->label() }}</span>
    </div>

    <figcaption class="max-w-[22rem] text-center text-base text-ink">
        {{ $sentence }}

        @if ($delta !== null && $measured)
            {{-- The delta line. Signed, so the direction reads without the colour. --}}
            <span class="mt-1 block font-mono text-sm text-ink-2 tabular-nums">
                {{ $delta > 0 ? '+' : '' }}{{ $delta }} since last check
            </span>
        @endif
    </figcaption>
</figure>
