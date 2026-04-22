<?php

namespace App\Services;

use App\Models\EditHistory;
use App\Models\User;
use App\Models\UserActivityAlert;
use App\Models\UserLoginLog;
use App\Models\UserPageView;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserLogAlertService
{
    private const WHATSAPP_HOURLY_CAP_PER_USER = 3;
    private const ALERT_SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public function __construct(
        protected SuspiciousActivityDetector $detector,
        protected ClaudeService $claude,
    ) {}

    /**
     * Runs a full scan → score → alert cycle. Safe to call from the
     * scheduler or the "Analyze Now" button — dedupe keeps repeats cheap.
     *
     * @return array{findings:int, new_alerts:int, whatsapp_sent:int}
     */
    public function analyzeAndAlert(?CarbonImmutable $since = null, ?CarbonImmutable $until = null, ?int $scopeUserId = null): array
    {
        $until ??= CarbonImmutable::now();
        $since ??= $until->subMinutes(30);

        $findings = $this->detector->scan($since, $until);

        if ($scopeUserId !== null) {
            $findings = $findings->where('user_id', $scopeUserId)->values();
        }

        $newAlerts = 0;
        $waSent = 0;

        foreach ($findings as $finding) {
            $alert = $this->persistFinding($finding);
            if (! $alert || ! $alert->wasRecentlyCreated) {
                continue;
            }
            $newAlerts++;

            $this->analyzeWithClaude($alert);

            if ($this->shouldSendWhatsapp($alert)) {
                if ($this->sendWhatsapp($alert)) {
                    $waSent++;
                }
            }
        }

        return [
            'findings' => $findings->count(),
            'new_alerts' => $newAlerts,
            'whatsapp_sent' => $waSent,
        ];
    }

    private function persistFinding(array $finding): ?UserActivityAlert
    {
        try {
            return UserActivityAlert::firstOrCreate(
                [
                    'user_id' => $finding['user_id'],
                    'rule_hash' => $finding['rule_hash'],
                    'window_start' => $finding['window_start'],
                ],
                [
                    'rule_code' => $finding['rule_code'],
                    'severity' => $finding['severity_hint'] ?? 'low',
                    'verdict' => null,
                    'summary' => null,
                    'details' => ['facts' => $finding['facts'] ?? []],
                    'window_end' => $finding['window_end'],
                    'whatsapp_status' => 'skipped',
                ],
            );
        } catch (\Throwable $e) {
            Log::error('Failed to persist activity finding: ' . $e->getMessage(), ['finding' => $finding]);
            return null;
        }
    }

    private function analyzeWithClaude(UserActivityAlert $alert): void
    {
        $payload = $this->buildClaudePayload($alert);
        if (! $payload) {
            return;
        }

        $system = 'You are a security analyst for SISDA, a Malaysian voter-data management system. '
            . 'Evaluate the supplied user activity window and decide if it indicates data exfiltration, '
            . 'unauthorised access, or policy abuse. Respond ONLY with a JSON object matching the schema. '
            . 'Use Bahasa Malaysia for the "verdict", "summary", "reasons", and "recommended_action" fields. '
            . 'Schema: {"severity":"low|medium|high|critical","verdict":"string","summary":"string",'
            . '"reasons":["string"],"recommended_action":"string","confidence":0.0}.';

        $result = $this->claude->chat($system, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $details = $alert->details ?? [];
        $details['claude_raw'] = $result['raw'] ?? null;
        $details['claude_error'] = $result['error'] ?? null;
        $details['payload'] = $payload;

        if ($result['ok']) {
            $parsed = $this->claude->extractJson($result['content']);
            if ($parsed) {
                $severity = in_array(($parsed['severity'] ?? null), self::ALERT_SEVERITIES, true)
                    ? $parsed['severity']
                    : $alert->severity;

                $alert->fill([
                    'severity' => $severity,
                    'verdict' => substr((string) ($parsed['verdict'] ?? ''), 0, 255) ?: null,
                    'summary' => (string) ($parsed['summary'] ?? ''),
                    'details' => array_merge($details, ['claude_parsed' => $parsed]),
                ])->save();
                return;
            }

            $details['claude_parse_error'] = 'could_not_extract_json';
            $details['claude_text'] = $result['content'];
        }

        // Fallback — AI disabled, errored, or returned unparseable text.
        $alert->fill([
            'summary' => $this->fallbackSummary($alert),
            'details' => $details,
        ])->save();
    }

    private function buildClaudePayload(UserActivityAlert $alert): ?array
    {
        $user = $alert->user_id ? User::with('bandar:id,nama')->find($alert->user_id) : null;

        $logins = UserLoginLog::query()
            ->where(function ($q) use ($alert) {
                $q->where('user_id', $alert->user_id);
                if ($alert->user_id === null) {
                    $q->orWhereNull('user_id');
                }
            })
            ->whereBetween('created_at', [$alert->window_start->copy()->subHour(), $alert->window_end])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['event', 'ip', 'user_agent', 'created_at', 'email_attempted']);

        $views = UserPageView::query()
            ->where('user_id', $alert->user_id)
            ->whereBetween('created_at', [$alert->window_start->copy()->subHour(), $alert->window_end])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['route_name', 'method', 'ip', 'created_at']);

        $edits = EditHistory::query()
            ->where('user_id', $alert->user_id)
            ->whereBetween('created_at', [$alert->window_start->copy()->subHour(), $alert->window_end])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['model_type', 'model_id', 'action', 'changes', 'created_at']);

        $distinctIps = UserPageView::query()
            ->where('user_id', $alert->user_id)
            ->whereBetween('created_at', [$alert->window_start->copy()->subHour(), $alert->window_end])
            ->whereNotNull('ip')
            ->distinct()
            ->count('ip');

        $distinctUas = UserPageView::query()
            ->where('user_id', $alert->user_id)
            ->whereBetween('created_at', [$alert->window_start->copy()->subHour(), $alert->window_end])
            ->whereNotNull('user_agent')
            ->distinct()
            ->count('user_agent');

        // Strict PII policy — no ICs, names, phones, or raw change values
        // leave the server. Only column names, counts, routes, ids.
        return [
            'user' => $user ? [
                'id' => $user->id,
                'role' => $user->role,
                'bandar' => optional($user->bandar)->nama,
            ] : ['id' => null, 'role' => null, 'bandar' => null],
            'window' => [
                'start' => $alert->window_start->toIso8601String(),
                'end' => $alert->window_end->toIso8601String(),
            ],
            'heuristics_triggered' => [
                [
                    'code' => $alert->rule_code,
                    'facts' => $alert->details['facts'] ?? [],
                ],
            ],
            'logins_sample' => $logins->map(fn ($r) => [
                'ts' => optional($r->created_at)->toIso8601String(),
                'event' => $r->event,
                'ip' => $r->ip,
                'ua' => substr((string) $r->user_agent, 0, 200),
                'email_attempted' => $r->email_attempted ? '<redacted>' : null,
            ])->all(),
            'page_views_sample' => $views->map(fn ($r) => [
                'ts' => optional($r->created_at)->toIso8601String(),
                'route' => $r->route_name,
                'method' => $r->method,
                'ip' => $r->ip,
            ])->all(),
            'edits_sample' => $edits->map(fn ($r) => [
                'ts' => optional($r->created_at)->toIso8601String(),
                'model_type' => $r->model_type,
                'model_id' => $r->model_id,
                'action' => $r->action,
                'fields_changed' => array_keys($r->changes ?? []),
            ])->all(),
            'distinct_ips' => $distinctIps,
            'distinct_user_agents' => $distinctUas,
        ];
    }

    private function fallbackSummary(UserActivityAlert $alert): string
    {
        $facts = $alert->details['facts'] ?? [];
        $factsJson = json_encode($facts, JSON_UNESCAPED_UNICODE);
        return "Amaran heuristik '{$alert->rule_code}' dicetuskan. Butiran: {$factsJson}.";
    }

    private function shouldSendWhatsapp(UserActivityAlert $alert): bool
    {
        if (! in_array($alert->severity, ['high', 'critical'], true)) {
            return false;
        }

        // Rolling 1-hour cap per user.
        $recent = UserActivityAlert::query()
            ->where('user_id', $alert->user_id)
            ->where('whatsapp_status', 'sent')
            ->where('whatsapp_sent_at', '>=', CarbonImmutable::now()->subHour())
            ->count();

        return $recent < self::WHATSAPP_HOURLY_CAP_PER_USER;
    }

    private function sendWhatsapp(UserActivityAlert $alert): bool
    {
        $user = $alert->user_id ? User::with('bandar:id,nama')->find($alert->user_id) : null;

        $vars = [
            'severity' => strtoupper($alert->severity),
            'verdict' => $alert->verdict ?: ('Aktiviti mencurigakan: ' . $alert->rule_code),
            'rule_code' => $alert->rule_code,
            'pengguna' => $user?->name ?? '-',
            'peranan' => $user?->role ?? '-',
            'parlimen' => optional($user?->bandar)->nama ?? '-',
            'tempoh_mula' => $alert->window_start->toDateTimeString(),
            'tempoh_tamat' => $alert->window_end->toDateTimeString(),
            'summary' => $alert->summary ?: '-',
            'tindakan' => $alert->details['claude_parsed']['recommended_action'] ?? '-',
            'pautan' => rtrim(config('app.url'), '/') . '/user-log',
        ];

        $sent = WhatsappService::notifyAdminTemplate('sys_admin_security_alert', $vars, 'user_log_alert');

        $alert->update([
            'whatsapp_status' => $sent ? 'sent' : 'failed',
            'whatsapp_sent_at' => $sent ? now() : null,
        ]);

        return $sent;
    }
}
