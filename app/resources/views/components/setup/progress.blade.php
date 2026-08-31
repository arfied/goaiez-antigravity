@props(['current'])

{{--
    Step indicator. NOT COLOUR-ONLY (`22`): each step carries its number and its
    name, and the current one is additionally marked with visible text rather
    than a hue alone, so it survives both a colourblind reader and a monochrome
    print.
--}}
<nav aria-label="Setup progress" class="mb-8">
    <p class="text-sm text-ink-2">
        Step {{ $current->position() }} of {{ count(\App\Enums\WizardStep::ordered()) }} —
        <span class="font-medium text-ink">{{ $current->label() }}</span>
    </p>
    <ol class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-ink-2">
        @foreach (\App\Enums\WizardStep::ordered() as $step)
            <li @if ($step === $current) aria-current="step" class="font-medium text-ink" @endif>
                {{ $step->position() }}. {{ $step->label() }}@if ($step === $current) <span class="sr-only">(current step)</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
