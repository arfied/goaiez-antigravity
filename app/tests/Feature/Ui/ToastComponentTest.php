<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ToastComponentTest extends TestCase
{
    public function test_the_toast_renders_the_message_with_an_alert_role_and_nothing_when_empty(): void
    {
        $rendered = Blade::render('<x-ui.toast kind="error" :message="$m" />', ['m' => 'Distinctive toast 4937']);
        $this->assertStringContainsString('Distinctive toast 4937', $rendered);
        $this->assertStringContainsString('role="alert"', $rendered);
        $this->assertStringContainsString('x-show="open"', $rendered);

        $empty = Blade::render('<x-ui.toast kind="error" :message="$m" />', ['m' => null]);
        $this->assertSame('', trim($empty));

        $success = Blade::render('<x-ui.toast kind="success" :message="$m" />', ['m' => 'Done']);
        $this->assertStringContainsString('role="status"', $success);
    }
}
