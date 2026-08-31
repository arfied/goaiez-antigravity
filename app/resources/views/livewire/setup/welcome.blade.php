@php
    use Illuminate\Support\Str;
@endphp

<div>
    <x-setup.progress :current="\App\Enums\WizardStep::Welcome" />

    <h1 class="font-display text-3xl font-semibold">
        @if ($businessName)
            We already found {{ $businessName }}
        @else
            Let's get you set up
        @endif
    </h1>

    @if (count($findings) > 0)
        <p class="mt-3 text-lg">
            And {{ count($findings) }} {{ Str::plural('thing', count($findings)) }} worth fixing.
        </p>
        <ul class="mt-4 space-y-2">
            {{--
                empty-state: absent because this is the first screen a new
                owner sees, and no findings is good news that needs no card.
                The block is behind `count($findings) > 0`, and the heading
                above already reads differently in that case.
            --}}
            @foreach ($findings as $finding)
                <li class="rounded-lg border border-rule p-3">
                    {{ is_array($finding) ? ($finding['sentence'] ?? '') : $finding }}
                </li>
            @endforeach
        </ul>
    @endif

    <p class="mt-6 text-base">
        Four short steps. You can stop anywhere and pick it up later.
    </p>

    <div class="mt-8">
        <x-ui.button wire:click="continue">Start</x-ui.button>
    </div>
</div>
