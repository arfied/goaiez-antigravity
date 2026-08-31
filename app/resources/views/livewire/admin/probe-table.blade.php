<div>
    {{--
        The error state, alongside the empty one, for the same reason the empty
        one is here: this is where the screen-state lints get something to read.
        `retry` is written out rather than left to default, because the default
        is `$refresh` and a lint that only ever sees `$refresh` cannot tell a
        working retry from a renamed one.
    --}}
    <x-ui.error-panel heading="We couldn’t load this probe" retry="reload">
        Nothing on this fixture is real. It exists so the screen-state lints have a
        call site that names a method.
    </x-ui.error-panel>

    <x-admin.table
        :columns="$this->visibleColumns()"
        :rows="$rows"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        caption="Probe"
        {{--
            The fixture the admin-table tests drive, and the one call site in
            the application that genuinely has nothing to invite anybody to
            do — so it is also where `empty-action=""` is exercised rather
            than only described.
        --}}
        empty="This probe has no rows."
        empty-action=""
    />
</div>
