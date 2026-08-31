@props(['sections'])

{{--
    Read-only record layout. Renders only what a DetailSection named — nothing
    iterates the model's attributes, because the first model that reaches is
    usually the one holding credentials.

    <dl> rather than a table: these are label/value pairs, not tabular data, and
    a screen reader should hear them that way.
--}}

<div class="space-y-6">
    @foreach ($sections as $section)
        <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
            <h2 class="font-display text-lg font-semibold text-ink">{{ $section['title'] }}</h2>

            @if ($section['description'])
                <p class="mt-1 text-sm text-ink-2">{{ $section['description'] }}</p>
            @endif

            <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach ($section['entries'] as $entry)
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-sm text-ink-3">{{ $entry['label'] }}</dt>
                        <dd class="text-base text-ink {{ $entry['numeric'] ? 'font-mono tabular-nums' : '' }}">
                            {{ $entry['value'] ?? '—' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
