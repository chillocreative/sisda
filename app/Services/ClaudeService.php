<?php

namespace App\Services;

use App\Models\ClaudeSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * Send a single-turn chat completion to Anthropic.
     *
     * Returns a normalised array:
     *   ['ok' => bool, 'content' => string|array|null, 'raw' => array|null, 'error' => string|null]
     *
     * Never throws — callers check `ok` and degrade gracefully when the
     * AI is unavailable so the audit UI still renders heuristic findings.
     */
    public function chat(string $systemPrompt, string $userPrompt, ?int $maxTokens = null, int $timeout = 30): array
    {
        $config = ClaudeSetting::current();

        if (! $config || ! $config->is_active || empty($config->api_key)) {
            return ['ok' => false, 'content' => null, 'raw' => null, 'error' => 'claude_disabled'];
        }

        $payload = [
            'model' => $config->model,
            'max_tokens' => $maxTokens ?? $config->max_tokens ?? 2048,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'x-api-key' => $config->api_key,
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                ])
                ->acceptJson()
                ->asJson()
                ->post(self::API_URL, $payload);

            if (! $response->successful()) {
                $error = $response->json('error.message') ?? $response->body();
                Log::error('Claude API call failed', ['status' => $response->status(), 'error' => $error]);
                return ['ok' => false, 'content' => null, 'raw' => $response->json(), 'error' => $error];
            }

            $text = $response->json('content.0.text');

            return [
                'ok' => true,
                'content' => $text,
                'raw' => $response->json(),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Claude API exception: ' . $e->getMessage());
            return ['ok' => false, 'content' => null, 'raw' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convenience: pull a JSON object out of a Claude reply, even when
     * the model wraps it in prose or code fences.
     */
    public function extractJson(?string $content): ?array
    {
        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        $trimmed = trim($content);

        // Prefer a fenced block if present.
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $trimmed, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Fall back to the first balanced {...} in the string.
        if (preg_match('/\{.*\}/s', $trimmed, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
