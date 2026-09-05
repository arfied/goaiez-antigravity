<div {{ $attributes->merge(['class' => 'border border-rule rounded-[--radius-panel] bg-card overflow-hidden']) }}>
    <ul class="divide-y divide-rule">
        {{ $slot }}
    </ul>
</div>
