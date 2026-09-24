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
}
