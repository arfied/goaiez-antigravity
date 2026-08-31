@props([
    'columns',
    'rows',
    'sortColumn' => '',
    'sortDirection' => 'asc',
    'empty' => 'Nothing here yet.',
    'emptyAction' => '',
    'emptyActionTarget' => '',
    'emptyActionHref' => '',
])

{{--
    The one table markup in the application. Every Ops Console screen renders
    through this; none of them writes a <table>.

    Accessibility is not decoration here: sort controls are real buttons with
    aria-sort so a screen reader announces the current order, and the caption
    gives the table a name. WCAG 2.2 AA, works at 320px (`29` §2 rule 48).

    ⚠️ THE EMPTY BRANCH TAKES AN ACTION BECAUSE IT HAD NO WAY TO OFFER ONE.
    `29` §5.7 settles the tone in four words — "empty states are invitations" —
    and this component supplied a default sentence and nothing to press, so
    every Ops empty state was a report on a query no matter what its call site
    wanted. `empty` and `empty-action` are now what the screen-states lint reads
    for, and `empty-action=""` is how a screen says there is genuinely nothing
    the reader can do rather than forgetting to say anything.
--}}

<div class="overflow-x-auto rounded-[--radius-card] border border-rule bg-card shadow-[--shadow-card]">
    <table class="w-full min-w-[40rem] border-collapse text-left">
        <caption class="sr-only">{{ $attributes->get('caption', 'Results') }}</caption>

        <thead class="bg-paper/60 border-b border-rule">
            <tr>
                @foreach ($columns as $column)
                    @php
                        $isSorted = $sortColumn === $column->key;
                        $ariaSort = $isSorted ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none';
                    @endphp
                    <th
                        scope="col"
                        @if ($column->isSortable()) aria-sort="{{ $ariaSort }}" @endif
                        class="px-4 py-3.5 text-xs font-bold uppercase tracking-wider text-ink-2 {{ $column->isNumeric() ? 'text-right tabular-nums' : '' }}"
                    >
                        @if ($column->isSortable())
                            <button
                                type="button"
                                wire:click="sortBy('{{ $column->key }}')"
                                class="inline-flex items-center gap-1.5 rounded-[--radius-control] hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2"
                            >
                                <span>{{ $column->label }}</span>
                                <span aria-hidden="true" class="text-ink-3 text-xs">
                                    {{ $isSorted ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕' }}
                                </span>
                            </button>
                        @else
                            {{ $column->label }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody class="divide-y divide-rule">
            @forelse ($rows as $row)
                <tr wire:key="row-{{ $row->getKey() }}" class="hover:bg-paper/40 transition-colors">
                    @foreach ($columns as $column)
                        <td class="px-4 py-3.5 text-sm text-ink {{ $column->isNumeric() ? 'text-right font-mono tabular-nums' : '' }}">
                            {{ $column->render($row) }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="px-4 py-12 text-center">
                        <p class="text-sm text-ink-2">{{ $empty }}</p>

                        @if ($emptyAction !== '')
                            <div class="mt-3 flex justify-center">
                                @if ($emptyActionHref !== '')
                                    <x-ui.button :href="$emptyActionHref" variant="secondary" size="default">
                                        {{ $emptyAction }}
                                    </x-ui.button>
                                @else
                                    <x-ui.button
                                        variant="secondary"
                                        size="default"
                                        wire:click="{{ $emptyActionTarget }}"
                                        wire:loading.attr="disabled"
                                        wire:target="{{ $emptyActionTarget }}"
                                    >
                                        {{ $emptyAction }}
                                    </x-ui.button>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($rows->hasPages())
    <div class="mt-4">{{ $rows->links() }}</div>
@endif
