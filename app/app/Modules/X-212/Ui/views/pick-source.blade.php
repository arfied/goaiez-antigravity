<div>
    <h2>Import from another system</h2>
    <label>
        Source
        <select wire:model="sourceSystem">
            @foreach (\App\Modules\X212\Ui\PickSource::SOURCES as $key => $label)
                <option value="{{ $key }}">
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </label>
    <label>
        Paste the export (first line is the header — phone or email is required per row)
        <textarea wire:model="csv"></textarea>
    </label>
    @error('csv')
        <div>{{ $message }}</div>
    @enderror
    @if (session('status'))
        <div>{{ session('status') }}</div>
    @endif
    <div>
        <button type="button" wire:click="dryRun">Run a dry run</button>
        <span>A dry run checks the file and imports nothing.</span>
    </div>
    <a href="{{ route('x-212.dryrun-preview') }}">See dry runs &rarr;</a>
</div>
