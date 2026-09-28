<?php

namespace Tests\Feature;

use Tests\TestCase;

class ChatScriptTest extends TestCase
{
    public function test_chat_script_is_served(): void
    {
        $response = $this->get('/chat.js');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

        $content = $response->getContent();
        $this->assertStringContainsString('/api/chat/', $content);
        $this->assertStringNotContainsString('innerHTML', $content);
    }

    public function test_the_served_chat_script_gates_the_details_form_on_consent_and_starts_a_session_on_reveal(): void
    {
        $response = $this->get('/chat.js');
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('Tick "You may contact me about this" first', $content);
        $this->assertStringContainsString('ensureSession(', $content);
        $this->assertTrue(substr_count($content, 'consentCheckbox.checked') >= 2);
    }
}
