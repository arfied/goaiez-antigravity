<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">All screens</h1>
        <p class="mt-1 text-base text-ink-2">
            Every feature we have built, sorted by family.
        </p>
    </div>

    @foreach ($families as $family => $items)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">{{ $family }}</h2>
            <ul class="mt-3 space-y-3">
                @foreach ($items as $item)
                    <li class="flex flex-col gap-0.5 border-b border-rule pb-3 last:border-0 last:pb-0">
                        <a href="{{ route($item->route) }}" class="text-base text-link hover:underline">
                            {{ $item->label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
