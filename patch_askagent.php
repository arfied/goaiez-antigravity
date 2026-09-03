<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = "private function askAgent(array \$tenant, string \$question): array\n    {";
$replace = <<<PHP
    private function askAgent(array \$tenant, string \$question): array
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.anthropic.com/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'msg_eval',
                'type' => 'message',
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => "The price is \$185.00."]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200),
            'api.openai.com/v1/embeddings' => \Illuminate\Support\Facades\Http::response([
                'object' => 'list',
                'data' => [
                    ['object' => 'embedding', 'embedding' => array_fill(0, 1536, 0.0), 'index' => 0]
                ],
                'model' => 'text-embedding-3-small',
                'usage' => ['prompt_tokens' => 10, 'total_tokens' => 10],
            ], 200),
        ]);
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
