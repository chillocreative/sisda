<?php

namespace Tests\Unit;

use App\Models\AiUsageLog;
use App\Models\ClaudeSetting;
use App\Services\ClaudeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * chatWithWebSearch()'s search loop resumes across up to 4 turns when
 * Claude returns stop_reason "pause_turn". Before this fix, the whole loop
 * lived under ONE try/catch: a wall-clock deadline exceeded broke the loop
 * gracefully (falling through to logUsage() + a partial ok:true), but an
 * HTTP timeout/connection exception on turn 2+ was caught by the OUTER
 * catch, which threw away every bit of text/citations/usage accumulated
 * from earlier turns and returned ok:false with content:null — even though
 * real narrative and real token spend already existed.
 *
 * This is the reported production symptom: "Analisa" reports "3 carian
 * web" (searches completed) yet renders the deterministic fallback with
 * no narrative, and ai_usage_logs never gets a row because logUsage()
 * sits after the loop and the catch never reaches it.
 */
class ClaudeServiceChatWithWebSearchTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ClaudeService
    {
        return app(ClaudeService::class);
    }

    private function activateClaude(): void
    {
        ClaudeSetting::create([
            'api_key' => 'sk-ant-test-key',
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 4096,
            'is_active' => true,
        ]);
    }

    private function pauseTurnResponse(): array
    {
        return [
            'id' => 'msg_turn_1',
            'model' => 'claude-sonnet-4-6',
            'stop_reason' => 'pause_turn',
            'usage' => [
                'input_tokens' => 500,
                'output_tokens' => 200,
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens' => 0,
                'server_tool_use' => ['web_search_requests' => 1],
            ],
            'content' => [
                ['type' => 'text', 'text' => 'Analisa awal: keputusan PRU15 menunjukkan peningkatan pengundi.'],
                ['type' => 'server_tool_use', 'id' => 'srvtool_1', 'name' => 'web_search', 'input' => ['query' => 'PRU15 Juasseh']],
                [
                    'type' => 'web_search_tool_result',
                    'tool_use_id' => 'srvtool_1',
                    'content' => [
                        ['type' => 'web_search_result', 'title' => 'Sumber Rasmi SPR', 'url' => 'https://example.com/spr-a'],
                    ],
                ],
            ],
        ];
    }

    public function test_timeout_on_resume_turn_salvages_prior_turn_text_and_still_returns_ok(): void
    {
        $this->activateClaude();

        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;

            if ($callCount === 1) {
                return Http::response($this->pauseTurnResponse(), 200);
            }

            // Simulate the second turn blowing the per-call HTTP timeout —
            // Laravel throws ConnectionException for this, not a normal response.
            throw new ConnectionException('cURL error 28: Operation timed out after 70000 milliseconds');
        });

        $result = $this->service()->chatWithWebSearch(
            systemPrompt: 'Anda pembantu analisa pilihan raya.',
            userPrompt: 'Bandingkan senario PRU.',
            context: 'analisa_comparison',
        );

        $this->assertSame(2, $callCount, 'Second turn must actually have been attempted (resume happened).');

        $this->assertTrue($result['ok'], 'A timeout mid-loop must not discard the narrative already gathered.');
        $this->assertNotNull($result['content']);
        $this->assertStringContainsString('Analisa awal', $result['content']);

        $this->assertSame(1, $result['searches']);
        $this->assertCount(1, $result['citations']);
        $this->assertSame('https://example.com/spr-a', $result['citations'][0]['url']);

        $this->assertArrayHasKey('partial', $result, 'Caller needs to be able to tell the loop ended early.');
        $this->assertTrue($result['partial']);

        // The real token spend from the completed first turn must be recorded,
        // even though the second turn failed — this is the ai_usage_logs gap
        // reported in production.
        $this->assertSame(1, AiUsageLog::where('context', 'analisa_comparison')->count());
        $log = AiUsageLog::where('context', 'analisa_comparison')->first();
        $this->assertSame(500, $log->input_tokens);
        $this->assertSame(200, $log->output_tokens);
    }

    public function test_timeout_on_very_first_turn_with_nothing_accumulated_returns_ok_false(): void
    {
        $this->activateClaude();

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 70000 milliseconds');
        });

        $result = $this->service()->chatWithWebSearch(
            systemPrompt: 'Anda pembantu analisa pilihan raya.',
            userPrompt: 'Bandingkan senario PRU.',
            context: 'analisa_comparison',
        );

        $this->assertFalse($result['ok'], 'Genuinely nothing gathered must still report failure, not a fake success.');
        $this->assertNull($result['content']);
        $this->assertSame([], $result['citations']);
        $this->assertSame(0, AiUsageLog::where('context', 'analisa_comparison')->count());
    }
}
