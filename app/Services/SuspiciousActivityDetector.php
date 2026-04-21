<?php

namespace App\Services;

use App\Models\EditHistory;
use App\Models\UserLoginLog;
use App\Models\UserPageView;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SuspiciousActivityDetector
{
    public const RULES = [
        'VIEW_FLOOD' => ['window' => 600, 'threshold' => 100, 'severity_hint' => 'high'],
        'IP_HOP' => ['window' => 300, 'threshold' => 2, 'severity_hint' => 'high'],
        'OFF_HOURS' => ['window' => null, 'threshold' => 20, 'severity_hint' => 'medium'],
        'BULK_EDIT' => ['window' => 900, 'threshold' => 50, 'severity_hint' => 'critical'],
        'MASS_IC_LOOKUP' => ['window' => 600, 'threshold' => 30, 'severity_hint' => 'high'],
        'EXPORT_ABUSE' => ['window' => 3600, 'threshold' => 3, 'severity_hint' => 'critical'],
        'FAILED_LOGIN_BURST' => ['window' => 900, 'threshold' => 5, 'severity_hint' => 'medium'],
    ];

    /**
     * Scan recent activity and return raw findings. Each finding carries
     * a rule_hash bucketed to a 5-minute window so dedupe is natural.
     */
    public function scan(CarbonImmutable $since, CarbonImmutable $until): Collection
    {
        $findings = collect();

        $findings = $findings->merge($this->detectViewFlood($since, $until));
        $findings = $findings->merge($this->detectIpHop($since, $until));
        $findings = $findings->merge($this->detectOffHours($since, $until));
        $findings = $findings->merge($this->detectBulkEdit($since, $until));
        $findings = $findings->merge($this->detectMassIcLookup($since, $until));
        $findings = $findings->merge($this->detectExportAbuse($since, $until));
        $findings = $findings->merge($this->detectFailedLoginBurst($since, $until));

        return $findings->values();
    }

    private function detectViewFlood(CarbonImmutable $since, CarbonImmutable $until): Collection
    {
        $rows = UserPageView::query()
            ->selectRaw('user_id, COUNT(*) as hits, MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->whereBetween('created_at', [$since, $until])
            ->whereHas('user', fn ($q) => $q->whereIn('role', ['user', 'super_user']))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= ?', [self::RULES['VIEW_FLOOD']['threshold']])
            ->get();

        return $rows->map(fn ($r) => $this->buildFinding(
            'VIEW_FLOOD',
            $r->user_id,
            CarbonImmutable::parse($r->first_seen),
            CarbonImmutable::parse($r->last_seen),
            ['hits' => (int) $r->hits, 'threshold' => self::RULES['VIEW_FLOOD']['threshold']],
        ));
    }

    private function detectIpHop(CarbonImmutable $since, CarbonImmutable $until): Collection
    {
        $rows = UserPageView::query()
            ->selectRaw('user_id, COUNT(DISTINCT ip) as distinct_ips, MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->whereBetween('created_at', [$since, $until])
            ->whereNotNull('ip')
            ->whereHas('user', fn ($q) => $q->whereIn('role', ['user', 'super_user']))
            ->groupBy('user_id')
            ->havingRaw('COUNT(DISTINCT ip) >= ?', [self::RULES['IP_HOP']['threshold']])
            ->get();

        return $rows->map(function ($r) {
            $ips = UserPageView::where('user_id', $r->user_id)
                ->whereBetween('created_at', [$r->first_seen, $r->last_seen])
                ->distinct()
                ->pluck('ip')
                ->filter()
                ->take(10)
                ->values()
                ->all();

            return $this->buildFinding(
                'IP_HOP',
                $r->user_id,
                CarbonImmutable::parse($r->first_seen),
                CarbonImmutable::parse($r->last_seen),
                ['distinct_ips' => (int) $r->distinct_ips, 'sample_ips' => $ips],
            );
        });
    }

    private function detectOffHours(CarbonImmutable $since, CarbonImmutable $until): Collection
    {
        $appTz = config('app.timezone', 'UTC');
        $myt = 'Asia/Kuala_Lumpur';

        $rows = UserPageView::query()
            ->selectRaw('user_id, COUNT(*) as hits, MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->whereBetween('created_at', [$since, $until])
            ->whereHas('user', fn ($q) => $q->whereIn('role', ['user', 'super_user']))
            ->groupBy('user_id')
            ->get();

        return $rows->filter(function ($r) use ($appTz, $myt) {
            $offHourHits = UserPageView::where('user_id', $r->user_id)
                ->whereBetween('created_at', [$r->first_seen, $r->last_seen])
                ->get()
                ->filter(function ($view) use ($appTz, $myt) {
                    $localHour = (int) $view->created_at->copy()->setTimezone($myt)->format('H');
                    return $localHour >= 0 && $localHour < 6;
                })
                ->count();

            $r->off_hour_hits = $offHourHits;
            return $offHourHits > self::RULES['OFF_HOURS']['threshold'];
        })->map(fn ($r) => $this->buildFinding(
            'OFF_HOURS',
            $r->user_id,
            CarbonImmutable::parse($r->first_seen),
            CarbonImmutable::parse($r->last_seen),
            ['off_hour_hits' => $r->off_hour_hits, 'threshold' => self::RULES['OFF_HOURS']['threshold']],
        ));
    }

    private function detectBulkEdit(CarbonImmutable $since, CarbonImmutable $until): Collection
    {
        $rows = EditHistory::query()
            ->selectRaw('user_id, COUNT(*) as hits, MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->whereBetween('created_at', [$since, $until])
            ->whereHas('user', fn ($q) => $q->whereIn('role', ['user', 'super_user']))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= ?', [self::RULES['BULK_EDIT']['threshold']])
            ->get();

        return $rows->map(fn ($r) => $this->buildFinding(
            'BULK_EDIT',
            $r->user_id,
            CarbonImmutable::parse($r->first_seen),
            CarbonImmutable::parse($r->last_seen),
            ['hits' => (int) $r->hits, 'threshold' => self::RULES['BULK_EDIT']['threshold']],
        ));
    }

    private function detectMassIcLookup(CarbonImmutable $since, CarbonImmutable $until): Collection
    {
        $rows = UserPageView::query()
            ->selectRaw('user_id, COUNT(*) as hits, MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->whereBetween('created_at', [$since, $until])
            ->where('route_name', 'api.voter.search-ic')
            ->whereHas('user', fn ($q) => $q->whereIn('role', ['user', 'super_user']))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= ?', [self::RULES['MASS_IC_LOOKUP']['threshold']])
            ->get();

        return $rows->map(fn ($r) => $this->buildFinding(
            'MASS_IC_LOOKUP',
            $r->user_id,
            CarbonImmutable::parse($r->first_seen),
            CarbonImmutable::parse($r->last_seen),
            ['hits' => (int) $r->hits, 'threshold' => self::RULES['MASS_IC_LOOKUP']['threshold']],
        ));
    }

    private function detectExportAbuse(CarbonImmutable $since, CarbonImmutable $until): Collection
    {
        $rows = UserPageView::query()
            ->selectRaw('user_id, COUNT(*) as hits, MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->whereBetween('created_at', [$since, $until])
            ->where('route_name', 'like', '%.export')
            ->whereHas('user', fn ($q) => $q->whereIn('role', ['user', 'super_user']))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= ?', [self::RULES['EXPORT_ABUSE']['threshold']])
            ->get();

        return $rows->map(fn ($r) => $this->buildFinding(
            'EXPORT_ABUSE',
            $r->user_id,
            CarbonImmutable::parse($r->first_seen),
            CarbonImmutable::parse($r->last_seen),
            ['hits' => (int) $r->hits, 'threshold' => self::RULES['EXPORT_ABUSE']['threshold']],
        ));
    }

    private function detectFailedLoginBurst(CarbonImmutable $since, CarbonImmutable $until): Collection
    {
        $rows = UserLoginLog::query()
            ->selectRaw('user_id, ip, COUNT(*) as hits, MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->whereBetween('created_at', [$since, $until])
            ->where('event', 'login_failed')
            ->groupBy('user_id', 'ip')
            ->havingRaw('COUNT(*) >= ?', [self::RULES['FAILED_LOGIN_BURST']['threshold']])
            ->get();

        return $rows->map(fn ($r) => $this->buildFinding(
            'FAILED_LOGIN_BURST',
            $r->user_id,
            CarbonImmutable::parse($r->first_seen),
            CarbonImmutable::parse($r->last_seen),
            ['hits' => (int) $r->hits, 'ip' => $r->ip, 'threshold' => self::RULES['FAILED_LOGIN_BURST']['threshold']],
        ));
    }

    private function buildFinding(string $ruleCode, ?int $userId, CarbonImmutable $start, CarbonImmutable $end, array $facts): array
    {
        $bucket = (int) floor($start->getTimestamp() / 300) * 300;
        $hash = sha1($ruleCode . '|' . ($userId ?? 'null') . '|' . $bucket);
        $windowStart = CarbonImmutable::createFromTimestamp($bucket);

        return [
            'user_id' => $userId,
            'rule_code' => $ruleCode,
            'rule_hash' => $hash,
            'window_start' => $windowStart,
            'window_end' => $end,
            'severity_hint' => self::RULES[$ruleCode]['severity_hint'] ?? 'low',
            'facts' => $facts,
        ];
    }
}
