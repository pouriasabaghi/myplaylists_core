<?php

namespace App\Services;

use App\Interfaces\AiInterface;
use Illuminate\Support\Facades\Http;

class GeminiService implements AiInterface
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('app.ai_api_key');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    }

    public function generateContent(string $prompt): string
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}?key={$this->apiKey}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                ]);

        if ($response->failed()) {
            throw new \Exception('AI Service request failed: ' . $response->body());
        }

        return $response->json('candidates.0.content.parts.0.text');
    }
}