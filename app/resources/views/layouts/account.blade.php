@props(['title' => 'GO AI EZ - Power Dashboard', 'maxWidth' => 'max-w-7xl'])
<x-account.layout :title="$title" :maxWidth="$maxWidth">
    {{ $slot }}
</x-account.layout>
