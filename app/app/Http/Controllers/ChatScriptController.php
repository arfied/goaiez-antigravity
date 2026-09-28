<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

final class ChatScriptController extends Controller
{
    private const int CACHE_SECONDS = 300;

    public function __invoke(Request $request, Vite $vite): Response
    {
        return response($this->bundle($vite), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function bundle(Vite $vite): string
    {
        try {
            return $vite->content('resources/js/chat.js');
        } catch (Throwable) {
            $source = resource_path('js/chat.js');
            $contents = is_file($source) ? file_get_contents($source) : false;

            return $contents === false ? '' : $contents;
        }
    }
}
