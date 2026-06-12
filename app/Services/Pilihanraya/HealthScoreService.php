<?php

namespace App\Services\Pilihanraya;

class HealthScoreService
{
    /**
     * Seats with fewer canvassed voters than this are statistically
     * meaningless — force them into "Berayun" with a low_data flag.
     */
    public const MIN_CANVASSED = 30;

    public const CATEGORIES = [
        'Selamat' => ['min' => 75, 'color' => '#10b981'],
        'Cenderung Kuat' => ['min' => 65, 'color' => '#34d399'],
        'Cenderung' => ['min' => 55, 'color' => '#3b82f6'],
        'Berayun' => ['min' => 45, 'color' => '#f59e0b'],
        'Kritikal' => ['min' => 35, 'color' => '#f97316'],
        'Risiko Kalah' => ['min' => 0,  'color' => '#ef4444'],
    ];

    /**
     * Deterministic 0-100 seat health score. Shares are 0..1 fractions of
     * canvassed voters; coverage is 0..1 of the registered roll. Low
     * coverage shrinks the score toward 50 (uncertainty) so a seat that
     * looks "safe" on 2% coverage never reads as safe.
     */
    public function score(float $putihShare, float $hitamShare, float $kelabuShare, float $coverage): int
    {
        $raw = 50 + 60 * ($putihShare - $hitamShare) - 10 * $kelabuShare;
        $confidence = min(1.0, $coverage / 0.30);
        $score = 50 + ($raw - 50) * $confidence;

        return (int) round(max(0, min(100, $score)));
    }

    public function category(int $score): string
    {
        foreach (self::CATEGORIES as $name => $def) {
            if ($score >= $def['min']) {
                return $name;
            }
        }

        return 'Risiko Kalah';
    }

    public function categoryColor(string $category): string
    {
        return self::CATEGORIES[$category]['color'] ?? '#94a3b8';
    }

    /**
     * Decorate a merged seat row (roll_total, covered, putih, hitam,
     * kelabu, canvassed) with shares, score, category and margin.
     */
    public function decorate(array $row): array
    {
        $canvassed = max(0, (int) ($row['canvassed'] ?? 0));
        $rollTotal = max(0, (int) ($row['roll_total'] ?? 0));
        $covered = max(0, (int) ($row['covered'] ?? 0));

        $putihShare = $canvassed > 0 ? ($row['putih'] ?? 0) / $canvassed : 0;
        $hitamShare = $canvassed > 0 ? ($row['hitam'] ?? 0) / $canvassed : 0;
        $kelabuShare = $canvassed > 0 ? ($row['kelabu'] ?? 0) / $canvassed : 0;
        $coverage = $rollTotal > 0 ? min(1, $covered / $rollTotal) : 0;

        $lowData = $canvassed < self::MIN_CANVASSED;
        $score = $lowData ? 50 : $this->score($putihShare, $hitamShare, $kelabuShare, $coverage);
        $category = $lowData ? 'Berayun' : $this->category($score);

        return array_merge($row, [
            'coverage_pct' => round($coverage * 100, 1),
            'putih_pct' => round($putihShare * 100, 1),
            'hitam_pct' => round($hitamShare * 100, 1),
            'kelabu_pct' => round($kelabuShare * 100, 1),
            'margin' => round(($putihShare - $hitamShare) * 100, 1),
            'score' => $score,
            'category' => $category,
            'category_color' => $this->categoryColor($category),
            'low_data' => $lowData,
        ]);
    }
}
