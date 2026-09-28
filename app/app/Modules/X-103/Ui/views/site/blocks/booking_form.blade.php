<div class="site-block site-block-booking">
    <h2>{{ $block['heading'] }}</h2>
    <form method="post" action="{{ rtrim($context['form_action_base'], '/') }}/book">
        <div style="margin-bottom: 1rem;">
            <label style="display: block; font-weight: bold; margin-bottom: 0.25rem;" for="booking-name">Name</label>
            <input type="text" name="name" id="booking-name" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-ink);">
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label style="display: block; font-weight: bold; margin-bottom: 0.25rem;" for="booking-phone">Phone</label>
            <input type="tel" name="phone" id="booking-phone" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-ink);">
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label style="display: block; font-weight: bold; margin-bottom: 0.25rem;" for="booking-service">Service</label>
            <input type="text" name="service" id="booking-service" value="{{ $block['service'] ?? '' }}" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-ink);">
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label style="display: block; font-weight: bold; margin-bottom: 0.25rem;" for="booking-date">Preferred Date</label>
            <input type="date" name="preferred_date" id="booking-date" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-ink);">
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label style="display: block; font-weight: bold; margin-bottom: 0.25rem;" for="booking-email">Email</label>
            <input type="email" name="email" id="booking-email" style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-ink);">
        </div>

        <button type="submit" style="padding: 0.5rem 1rem; background: var(--color-ink); color: var(--color-canvas); border: none; cursor: pointer;">
            {{ $block['label'] ?? 'Request a time' }}
        </button>
        <p style="margin-top: 0.5rem; font-size: 0.875rem;"><i>Nothing is booked until we confirm the time with you.</i></p>
    </form>
</div>
