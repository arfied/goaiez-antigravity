<div>
    <h1>Directory Memberships</h1>
    @foreach ($memberships as $membership)
        <div>
            {{ $membership->directory_name }}
            @if($membership->is_noindex)
                (Google can't see this)
            @endif
        </div>
    @endforeach
</div>
