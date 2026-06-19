<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\ClaudeSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * Per-model price in USD per 1,000,000 tokens: [input, output].
     * Longest matching prefix wins; unknown models fall back to DEFAULT_PRICE.
     */
    private const PRICING = [
        'claude-fable-5' => [10.0, 50.0],
        'claude-opus-4-8' => [5.0, 25.0],
        'claude-opus-4-7' => [5.0, 25.0],
        'claude-opus-4-6' => [5.0, 25.0],
        'claude-opus-4-5' => [5.0, 25.0],
        'claude-opus-4' => [15.0, 75.0],
        'claude-sonnet-4-6' => [3.0, 15.0],
        'claude-sonnet-4-5' => [3.0, 15.0],
        'claude-sonnet-4' => [3.0, 15.0],
        'claude-haiku-4-5' => [1.0, 5.0],
        'claude-3-5-haiku' => [0.8, 4.0],
        'claude-3-haiku' => [0.25, 1.25],
    ];

    private const DEFAULT_PRICE = [3.0, 15.0];

    /**
     * Send a single-turn chat completion to Anthropic.
     *
     * Returns a normalised array:
     *   ['ok' => bool, 'content' => string|array|null, 'raw' => array|null, 'error' => string|null]
     *
     * Never throws — callers check `ok` and degrade gracefully when the
     * AI is unavailable so the audit UI still renders heuristic findings.
     */
    public function chat(string $systemPrompt, string $userPrompt, ?int $maxTokens = null, int $timeout = 30, ?string $context = null): array
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

            $this->logUsage($response->json('model') ?? $config->model, $response->json('usage') ?? [], $context);

            return [
                'ok' => true,
                'content' => $text,
                'raw' => $response->json(),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Claude API exception: '.$e->getMessage());

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

    /**
     * Record token usage and estimated cost for a successful call. Wrapped
     * so a logging failure never breaks the AI feature that called us.
     */
    private function logUsage(string $model, array $usage, ?string $context): void
    {
        try {
            $input = (int) ($usage['input_tokens'] ?? 0);
            $output = (int) ($usage['output_tokens'] ?? 0);
            $cacheCreate = (int) ($usage['cache_creation_input_tokens'] ?? 0);
            $cacheRead = (int) ($usage['cache_read_input_tokens'] ?? 0);

            [$inPrice, $outPrice] = self::priceFor($model);
            // Input billed at 1x, cache writes ~1.25x, cache reads ~0.1x.
            $cost = (
                $input * $inPrice
                + $cacheCreate * $inPrice * 1.25
                + $cacheRead * $inPrice * 0.1
                + $output * $outPrice
            ) / 1_000_000;

            AiUsageLog::create([
                'user_id' => auth()->id(),
                'model' => $model,
                'context' => $context,
                'input_tokens' => $input,
                'output_tokens' => $output,
                'cache_creation_input_tokens' => $cacheCreate,
                'cache_read_input_tokens' => $cacheRead,
                'cost_usd' => round($cost, 6),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI usage logging failed: '.$e->getMessage());
        }
    }

    /** USD per 1M tokens [input, output] for a model id (longest prefix wins). */
    private static function priceFor(string $model): array
    {
        $best = null;
        $bestLen = -1;
        foreach (self::PRICING as $prefix => $price) {
            if (str_starts_with($model, $prefix) && strlen($prefix) > $bestLen) {
                $best = $price;
                $bestLen = strlen($prefix);
            }
        }

        return $best ?? self::DEFAULT_PRICE;
    }
}
