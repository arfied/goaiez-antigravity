<div class="site-block site-block-form {{ $band ?? '' }}">
    <div class="site-block__inner">
        <form method="post" action="{{ rtrim($context['form_action_base'], '/') }}/forms/{{ $block['definition_id'] }}" class="card">
            @foreach($block['fields'] as $field)
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: bold; margin-bottom: 0.25rem;" for="form-{{ $block['definition_id'] }}-{{ $field['name'] }}">{{ $field['label'] }}</label>
                    @if($field['type'] === 'textarea')
                        <textarea name="{{ $field['name'] }}" id="form-{{ $block['definition_id'] }}-{{ $field['name'] }}" @if(in_array($field['name'], $block['required'] ?? [])) required @endif style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-ink);"></textarea>
                    @else
                        <input type="{{ $field['type'] }}" name="{{ $field['name'] }}" id="form-{{ $block['definition_id'] }}-{{ $field['name'] }}" @if(in_array($field['name'], $block['required'] ?? [])) required @endif style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-ink);">
                    @endif
                </div>
            @endforeach

            <input type="text" name="{{ $block['honeypot'] }}" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">

            <button type="submit" class="site-cta site-cta--primary" style="padding: 0.5rem 1rem; background: var(--color-ink); color: var(--color-canvas); border: none; cursor: pointer;">
                Submit
            </button>
        </form>
    </div>
</div>
