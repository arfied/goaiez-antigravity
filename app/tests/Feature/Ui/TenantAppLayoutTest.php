<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use Tests\TestCase;

final class TenantAppLayoutTest extends TestCase
{
    public function test_tenant_app_layout_renders_without_500(): void
    {
        config(['app.allow_public_signup' => true]);

        $this->get('/signup')
            ->assertOk()
            ->assertSee('bg-paper', false)
            ->assertSee('text-ink', false);
    }
}
