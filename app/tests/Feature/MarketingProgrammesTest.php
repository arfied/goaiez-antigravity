<?php

test('GET /affiliates renders deal terms', function (): void {
    $this->get(route('affiliates'))
        ->assertOk()
        ->assertSee('40%')
        ->assertSee('90 days');
});

test('GET /agencies renders discount terms', function (): void {
    $this->get(route('agencies'))
        ->assertOk()
        ->assertSee('40%')
        ->assertSee('25%');
});
