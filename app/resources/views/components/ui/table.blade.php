<table {{ $attributes }}>
    @isset($header)
        <thead>{{ $header }}</thead>
    @endisset
    <tbody>{{ $body ?? '' }}</tbody>
</table>
