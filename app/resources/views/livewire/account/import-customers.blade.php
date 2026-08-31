{{--
    Import your customers (`29` §11.2 row 5).

    COLOUR IS NOT THE SIGNAL (`22`). The one state that stops this page working —
    no published attestation wording — says so in words, under its own heading,
    with the form absent rather than disabled. A greyed-out button reads as a bug
    in our page; a sentence naming what is missing reads as the truth.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Bring your customers in</h1>
        <p class="mt-1 text-base text-ink-2">
            Upload the customer list you already have and we will start asking them
            for reviews. Anyone who has told us to stop is left alone.
        </p>
    </div>

    @if ($statement === null)
        {{--
            ⚠️ THE WAITING STATE, NAMED RATHER THAN HIDDEN. The Ops registry
            screen lists a withheld figure under "Waiting on a decision" instead
            of defaulting it (decision 502); this is the same move, for wording
            instead of a price. An owner who sees an empty page files a ticket;
            an owner who reads this knows it is not theirs to fix.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Not ready yet</h2>
            <p class="mt-2 text-base text-ink-2">
                Importing a list means telling us these are your own customers, and the
                exact wording of that has not been published yet. Until it is, there is
                nothing here for you to agree to and nothing you can import.
            </p>
            <p class="mt-3 text-base text-ink-2">
                Nothing is wrong with your account, and you do not need to do anything.
            </p>
        </div>
    @else
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <form wire:submit="import" class="space-y-5">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Your customer list</span>
                    <input
                        type="file"
                        wire:model="file"
                        accept=".csv,text/csv,text/plain"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                    {{--
                        THE STATE COLUMN IS NAMED BEFORE THE UPLOAD, NOT AFTER
                        IT (2682). The parser has read a `state` column since
                        1594 and this sentence never mentioned it, so a tenant
                        following the instructions exactly produced a file that
                        is now refused. Saying "we read name, email and phone"
                        while requiring a fourth column is the failure the
                        refusal message would then have to explain.
                    --}}
                    <span class="text-sm text-ink-2">
                        A CSV with a first row naming the columns. We read
                        <span class="font-mono">name</span>,
                        <span class="font-mono">email</span>,
                        <span class="font-mono">phone</span> and
                        <span class="font-mono">state</span>; anything else is ignored.
                        Every row needs a <span class="font-mono">state</span> —
                        the two-letter code for where that customer lives, like
                        <span class="font-mono">FL</span>. It decides when it is
                        legal to text them, and it is not something we can work
                        out from a phone number.
                    </span>
                    {{--
                        THE SIZE IS NAMED BEFORE THE PICKER, NOT AFTER IT (9981).
                        `import()` carries a `file.max` message saying this, and
                        it cannot fire: `max:2048` is 2,097,152 bytes, which is
                        exactly this deployment's `upload_max_filesize`, so PHP
                        turns the file away one byte before the rule is reached.
                        Until an upload has been tried there is nothing else on
                        this page that would tell somebody the limit exists.
                    --}}
                    <span class="text-sm text-ink-2">
                        Up to 2MB. If your list is bigger, split it and import each part.
                    </span>
                </label>

                <div wire:loading wire:target="file" class="text-base text-ink-2">Reading your file…</div>

                {{--
                    The statement itself, in full, beside the box that agrees to
                    it. Never a link to it and never a summary: the record this
                    produces claims the owner affirmed these words, so these words
                    are what has to be on the screen.
                --}}
                <fieldset class="rounded-[--radius-control] border border-rule p-4">
                    <legend class="px-1 text-sm font-medium text-ink-2">
                        {{ $statement->doc_type->title() }} (version {{ $statement->version }})
                    </legend>

                    {{--
                        Escaped and `whitespace-pre-line`, exactly as
                        `legal/document.blade.php` renders the same column. Not a
                        style choice: rendering it as markdown or raw HTML would
                        make an admin-authored field an injection surface on an
                        authenticated page, and the two renderings of one
                        document would disagree about what it says.
                    --}}
                    <div class="whitespace-pre-line text-base text-ink">{{ $statement->body }}</div>

                    <label class="mt-4 flex items-start gap-2">
                        {{-- Unchecked by default. Never add `checked`. --}}
                        <input type="checkbox" wire:model="attested" class="mt-1.5" />
                        <span class="text-base text-ink">I agree to the above.</span>
                    </label>
                </fieldset>

                @error('attested')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                @error('file')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <x-ui.button type="submit">
                    <span wire:loading.remove wire:target="import">Import customers</span>
                    <span wire:loading wire:target="import">Importing…</span>
                </x-ui.button>
            </form>
        </div>

        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">What happens to this list</h2>
            <ul class="mt-2 space-y-1 text-base text-ink-2">
                <li>Anyone already on the list is filled in, never overwritten.</li>
                <li>A row with no email and no phone number is skipped — there is no way to reach them.</li>
                <li>If any row is missing its state, nothing is imported and we tell you how many — so you fix the file rather than lose the list.</li>
                <li>Anyone who has asked us to stop stays stopped, whatever the file says.</li>
                <li>We keep a record of this agreement, who made it and when.</li>
            </ul>
        </div>
    @endif
</div>
