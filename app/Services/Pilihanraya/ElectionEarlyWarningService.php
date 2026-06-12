<?php

namespace App\Services\Pilihanraya;

use Illuminate\Support\Collection;

/**
 * Heuristic early-warning detector for the war room. Pure derived state —
 * recomputed from cached aggregates on every call, nothing persisted
 * (mirrors the SuspiciousActivityDetector rule-table shape).
 */
class ElectionEarlyWarningService
{
    public const RULES = [
        'PUTIH_DECLINE' => [
            'label' => 'Sokongan PH Menurun',
            'min_per_window' => 30,
            'drop_pts' => 5,
            'severity' => 'high',
        ],
        'HITAM_SURGE' => [
            'label' => 'Sokongan Pembangkang Meningkat',
            'min_per_window' => 30,
            'rise_pts' => 5,
            'severity' => 'high',
        ],
        'HIGH_KELABU' => [
            'label' => 'Atas Pagar Tinggi',
            'min_canvassed' => 50,
            'kelabu_pct' => 40,
            'severity' => 'medium',
        ],
        'LOW_COVERAGE' => [
            'label' => 'Liputan Culaan Rendah',
            'coverage_pct' => 10,
            'max_score' => 55,
            'severity' => 'medium',
        ],
        'STAGNANT_CANVASS' => [
            'label' => 'Culaan Tidak Aktif',
            'idle_days' => 14,
            'max_score' => 55,
            'severity' => 'low',
        ],
    ];

    public function __construct(protected ElectionAnalyticsService $analytics) {}

    public function scan(array $f): Collection
    {
        $alerts = collect();
        $seatRows = collect($this->analytics->seatScores($f)['kadun']);
        $trends = $this->analytics->trendByKadun($f);

        // Trend rules — recent 30d window vs the 30d before it. Each
        // rule applies its own min_per_window guard.
        foreach ($trends as $windows) {
            $kadun = $windows['name'] ?? '';
            $recent = $windows['recent'] ?? null;
            $prior = $windows['prior'] ?? null;
            if (! $recent || ! $prior) {
                continue;
            }

            $rule = self::RULES['PUTIH_DECLINE'];
            if ($recent['jumlah'] >= $rule['min_per_window'] && $prior['jumlah'] >= $rule['min_per_window']) {
                $drop = $prior['putih_pct'] - $recent['putih_pct'];
                if ($drop >= $rule['drop_pts']) {
                    $alerts->push($this->alert('PUTIH_DECLINE', $kadun, -$drop,
                        "Sokongan putih di {$kadun} jatuh ".round($drop, 1).' mata ('.
                        "{$prior['putih_pct']}% → {$recent['putih_pct']}%) dalam 30 hari.",
                        'Turunkan pasukan culaan dan kempen pemujukan segera di kawasan ini.'));
                }
            }

            $rule = self::RULES['HITAM_SURGE'];
            if ($recent['jumlah'] >= $rule['min_per_window'] && $prior['jumlah'] >= $rule['min_per_window']) {
                $rise = $recent['hitam_pct'] - $prior['hitam_pct'];
                if ($rise >= $rule['rise_pts']) {
                    $alerts->push($this->alert('HITAM_SURGE', $kadun, $rise,
                        "Sokongan pembangkang di {$kadun} naik ".round($rise, 1).' mata ('.
                        "{$prior['hitam_pct']}% → {$recent['hitam_pct']}%) dalam 30 hari.",
                        'Siasat punca anjakan dan kerahkan jentera penerangan.'));
                }
            }
        }

        // Snapshot rules from seat scores
        foreach ($seatRows as $seat) {
            $rule = self::RULES['HIGH_KELABU'];
            if ($seat['canvassed'] >= $rule['min_canvassed'] && $seat['kelabu_pct'] >= $rule['kelabu_pct']) {
                $alerts->push($this->alert('HIGH_KELABU', $seat['name'], $seat['kelabu_pct'],
                    "{$seat['kelabu_pct']}% pengundi diculaan di {$seat['name']} masih atas pagar ({$seat['kelabu']} orang).",
                    'Peluang pemujukan terbesar — sasarkan program turun padang dan bantuan komuniti.'));
            }

            $rule = self::RULES['LOW_COVERAGE'];
            if ($seat['roll_total'] > 0 && $seat['coverage_pct'] < $rule['coverage_pct'] && $seat['score'] <= $rule['max_score']) {
                $alerts->push($this->alert('LOW_COVERAGE', $seat['name'], $seat['coverage_pct'],
                    "Liputan culaan di {$seat['name']} hanya {$seat['coverage_pct']}% daripada {$seat['roll_total']} pengundi berdaftar.",
                    'Data tidak mencukupi untuk strategi — utamakan culaan sebelum kempen pemujukan.'));
            }
        }

        // Stagnant canvass — no recent records despite an unsafe score.
        // diffInDays(..., true) — Carbon 3 diffs are signed by default
        // and would be negative for past dates.
        $rule = self::RULES['STAGNANT_CANVASS'];
        foreach ($seatRows as $seat) {
            if ($seat['score'] > $rule['max_score'] || $seat['canvassed'] === 0) {
                continue;
            }
            $trend = $trends[ElectionAnalyticsService::nameKey($seat['name'])] ?? null;
            $lastAt = $trend['recent']['last_at'] ?? $trend['prior']['last_at'] ?? null;
            if ($lastAt === null || now()->diffInDays($lastAt, true) > $rule['idle_days']) {
                $alerts->push($this->alert('STAGNANT_CANVASS', $seat['name'], null,
                    "Tiada culaan baharu di {$seat['name']} sejak ".($lastAt ? date('d/m/Y', strtotime($lastAt)) : 'lebih 60 hari').' walaupun kerusi belum selamat.',
                    'Aktifkan semula pasukan culaan di kawasan ini.'));
            }
        }

        $severityRank = ['high' => 0, 'medium' => 1, 'low' => 2];

        return $alerts
            ->sortBy(fn ($a) => [$severityRank[$a['severity']] ?? 9, -abs($a['delta'] ?? 0)])
            ->values();
    }

    private function alert(string $code, string $kawasan, ?float $delta, string $message, string $action): array
    {
        $rule = self::RULES[$code];

        return [
            'rule_code' => $code,
            'label' => $rule['label'],
            'severity' => $rule['severity'],
            'kawasan' => $kawasan,
            'delta' => $delta !== null ? round($delta, 1) : null,
            'message' => $message,
            'recommended_action' => $action,
            'detected_at' => now()->toIso8601String(),
        ];
    }
}
