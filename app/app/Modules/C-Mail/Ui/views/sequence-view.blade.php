<div>
    <h2>Warm-up sequence</h2>

    @if($domains->isEmpty())
        <div>
            <p>Add a sending domain first</p>
            <a href="{{ route('c-mail.dns-card') }}">Add a sending domain first</a>
        </div>
    @else
        <div>
            <select wire:model="mailDomainId">
                <option value=""></option>
                @foreach($domains as $d)
                    <option value="{{ $d->id }}">{{ $d->domain_name }}</option>
                @endforeach
            </select>
            @error('mailDomainId') <span>{{ $message }}</span> @enderror

            @if (session('status'))
                <div>{{ session('status') }}</div>
            @endif

            <button type="button" wire:click="startWarmup">Start warm-up</button>
        </div>

        @foreach($domains as $d)
            @if($calendars->has($d->id))
                @php $cal = $calendars->get($d->id); @endphp
                <div>
                    <h3>{{ $d->domain_name }}</h3>
                    <p>today is day {{ $cal->current_day }} &middot; allowance {{ $cal->daily_allowance }} &middot; sent today {{ $cal->sent_today }} @if($cal->is_warmed) &middot; warmed @endif</p>
                    <table>
                        <thead>
                            <tr>
                                <th>day</th>
                                <th>min</th>
                                <th>max</th>
                                <th>quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($cal->schedule)
                                @foreach($cal->schedule as $day => $data)
                                    <tr>
                                        <td>{{ $day }}</td>
                                        <td>{{ $data['min'] }}</td>
                                        <td>{{ $data['max'] }}</td>
                                        <td>{{ $data['quantity'] }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach
    @endif

    <a href="{{ route('c-mail.warmup-calendars-per') }}">Domain warm-up &rarr;</a>
</div>
