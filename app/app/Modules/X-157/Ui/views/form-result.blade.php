<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $heading }}</title></head><body style="font-family: system-ui, sans-serif; max-width: 36rem; margin: 3rem auto; padding: 0 1rem;">
<h1>{{ $heading }}</h1>
<p>{{ $body }}</p>
@if(!empty($missing))<ul>@foreach($missing as $m)<li>{{ $m }}</li>@endforeach</ul>@endif
<p><a href="{{ $backUrl }}">{{ $backLabel }}</a></p>
</body></html>
