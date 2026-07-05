<?php

namespace App\Services;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\Kadun;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use App\Models\User;

/**
 * Matches field-canvassing sentiment rows (voter_name, no IC) to the DPPR roll
 * and writes kecenderungan_politik + voter_color onto data_pengundi. Generalized
 * from ImportCulaanSegamat: any Johor constituency (scoped from the CSV), CSV
 * input, sentiment -> kecenderungan mapping, and create-from-roll (sumber=import).
 *
 * Matching is scoped by Parlimen (constituency_code) and narrowed by DUN (parsed
 * from operation_name) to reduce name collisions. A name with 0 roll rows in
 * scope is reported (tidak_dijumpai); >1 is reported ambiguous (taksah) and never
 * guessed.
 */
class CulaanMatchService
{
    /** CSV sentiment (lowercased) -> SISDA kecenderungan_politik canonical value. */
    private const SENTIMENT_MAP = [
        'government' => 'BARISAN NASIONAL (BN/PN)',
        'opposition' => 'PAKATAN HARAPAN (PH/BN)',
    ];

    private const TIDAK_PASTI = 'TIDAK PASTI';

    private array $bandarByCode = []; // 'P140' => 'SEGAMAT'
    private array $kadunByCode = [];  // 'N01'  => 'BULOH KASAP'

    /**
     * @param  array  $rows  normalized rows from CulaanSentimentImport::read()
     * @return array  report (counts + samples + per-constituency breakdown)
     */
    public function run(array $rows, ?int $userId = null, bool $dryRun = false): array
    {
        $userId = $userId
            ?: (User::where('role', 'super_admin')->orderBy('id')->value('id') ?? User::orderBy('id')->value('id'));

        $this->loadCodeMaps();

        $norm = fn ($s) => strtoupper(trim(preg_replace('/\s+/', ' ', (string) $s)));

        $unresolved = 0;
        $resolved = [];
        $parlimenNames = [];

        // Pass 1 — resolve scope for every named row.
        foreach ($rows as $r) {
            $name = $norm($r['voter_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $parlimen = $this->resolveParlimen($r);
            if ($parlimen === null) {
                $unresolved++;

                continue;
            }
            $sentiment = strtolower(trim((string) ($r['sentiment'] ?? '')));
            $resolved[] = [
                'name' => $name,
                'parlimen' => $parlimen,
                'kadun' => $this->resolveKadun($r),
                'kp' => self::SENTIMENT_MAP[$sentiment] ?? self::TIDAK_PASTI,
                'blank' => ! isset(self::SENTIMENT_MAP[$sentiment]),
            ];
            $parlimenNames[$parlimen] = true;
        }

        // Dedup to distinct voter per (parlimen|name) — multiple visits collapse,
        // last occurrence (latest export row) wins the sentiment.
        $byKey = [];
        foreach ($resolved as $row) {
            $byKey[$row['parlimen'].'|'.$row['name']] = $row;
        }
        $distinct = array_values($byKey);

        // Only index roll rows whose name we actually need — keeps memory
        // proportional to the CSV, not to the whole Johor roll.
        $neededNames = [];
        foreach ($distinct as $row) {
            $neededNames[$row['name']] = true;
        }

        [$idxKadun, $idxParlimen] = $this->buildRollIndex(array_keys($parlimenNames), $neededNames, $norm);

        $stats = [
            'jumlah_baris' => count($rows),
            'pengundi_unik' => count($distinct),
            'matched' => 0, 'dicipta' => 0, 'dikemaskini' => 0, 'tak_berubah' => 0,
            'tidak_dijumpai' => 0, 'taksah' => 0, 'tiada_sentimen' => 0,
            'unresolved_constituency' => $unresolved,
        ];
        $samplesTidak = [];
        $samplesTaksah = [];
        $perKons = [];

        // Pass 2 — match + enrich.
        foreach ($distinct as $row) {
            $p = $row['parlimen'];
            $k = $row['kadun'];
            $n = $row['name'];
            $perKons[$p] ??= ['matched' => 0, 'dicipta' => 0, 'dikemaskini' => 0, 'tidak_dijumpai' => 0, 'taksah' => 0];

            $candidates = ($k !== null && isset($idxKadun[$p][$k][$n]))
                ? $idxKadun[$p][$k][$n]
                : ($idxParlimen[$p][$n] ?? null);

            if (! $candidates) {
                $stats['tidak_dijumpai']++;
                $perKons[$p]['tidak_dijumpai']++;
                if (count($samplesTidak) < 50) {
                    $samplesTidak[] = "{$n} | {$p}";
                }

                continue;
            }

            // Collapse the same voter appearing across several active roll
            // batches (re-uploads) — the same IC is one person, not ambiguity.
            $uniqueByIc = [];
            foreach ($candidates as $c) {
                $uniqueByIc[(string) $c->no_ic] = $c;
            }
            $uniqueByIc = array_values($uniqueByIc);

            // Genuine ambiguity: the same name belongs to >1 different person in
            // scope. Without an IC in the CSV we can't tell which was canvassed,
            // so pick the lowest-IC one deterministically — the aggregate stays
            // accurate (one contact -> one voter) — and report it.
            if (count($uniqueByIc) > 1) {
                usort($uniqueByIc, fn ($a, $b) => strcmp((string) $a->no_ic, (string) $b->no_ic));
                $stats['taksah']++;
                $perKons[$p]['taksah']++;
                if (count($samplesTaksah) < 50) {
                    $samplesTaksah[] = "{$n} | {$p} | ".count($uniqueByIc).' calon (1 dipilih)';
                }
            }

            $voter = $uniqueByIc[0];
            $stats['matched']++;
            $perKons[$p]['matched']++;
            if ($row['blank']) {
                $stats['tiada_sentimen']++;
            }

            $result = $this->applyToVoter($voter, $row['kp'], $userId, $dryRun);
            if ($result === 'created') {
                $stats['dicipta']++;
                $perKons[$p]['dicipta']++;
            } elseif ($result === 'updated') {
                $stats['dikemaskini']++;
                $perKons[$p]['dikemaskini']++;
            } else {
                $stats['tak_berubah']++;
            }
        }

        return array_merge($stats, [
            'sample_tidak_dijumpai' => $samplesTidak,
            'sample_taksah' => $samplesTaksah,
            'per_konstituensi' => $perKons,
            'dry_run' => $dryRun,
        ]);
    }

    /** Set kecenderungan + voter_color on the matched voter; create from roll if absent. */
    private function applyToVoter(object $voter, string $kp, int $userId, bool $dryRun): string
    {
        $existing = DataPengundi::where('no_ic', $voter->no_ic)
            ->whereRaw('LOWER(parlimen) = ?', [strtolower((string) $voter->parlimen)])
            ->first();

        if ($existing) {
            $color = VoterColorService::determine($existing->keahlian_parti, $kp);
            if ($existing->kecenderungan_politik === $kp && $existing->voter_color === $color) {
                return 'unchanged';
            }
            if (! $dryRun) {
                $existing->kecenderungan_politik = $kp;
                $existing->voter_color = $color;
                $existing->save();
            }

            return 'updated';
        }

        if (! $dryRun) {
            DataPengundi::create($this->buildRecord($voter, $kp, $userId));
        }

        return 'created';
    }

    /** Minimal data_pengundi row from a roll voter (all NOT-NULL columns filled). */
    private function buildRecord(object $voter, string $kp, int $userId): array
    {
        return [
            'nama' => $voter->nama,
            'no_ic' => $voter->no_ic,
            'umur' => $this->age($voter),
            'no_tel' => '-',
            'bangsa' => $voter->bangsa ?: '-',
            'alamat' => '-',
            'poskod' => '-',
            'negeri' => $voter->negeri ? ucwords(strtolower($voter->negeri)) : 'Johor',
            'bandar' => $voter->parlimen ?: '-',
            'parlimen' => $voter->parlimen,
            'kadun' => $voter->kadun,
            'daerah_mengundi' => $voter->daerah_mengundi,
            'lokaliti' => $voter->lokaliti,
            'kecenderungan_politik' => $kp,
            'voter_color' => VoterColorService::determine(null, $kp),
            'sumber' => 'import',
            'submitted_by' => $userId,
        ];
    }

    /** Age from IC birthdate, else roll tahun_lahir, else 0. */
    private function age(object $voter): int
    {
        $ic = (string) ($voter->no_ic ?? '');
        if (strlen($ic) >= 6 && ctype_digit(substr($ic, 0, 6))) {
            $yy = (int) substr($ic, 0, 2);
            $cur = (int) now()->format('y');
            $birth = $yy <= $cur ? 2000 + $yy : 1900 + $yy;
            $age = (int) now()->year - $birth;
            if ($age >= 0 && $age <= 120) {
                return $age;
            }
        }
        if (! empty($voter->tahun_lahir) && ctype_digit((string) $voter->tahun_lahir)) {
            $age = (int) now()->year - (int) $voter->tahun_lahir;
            if ($age >= 0 && $age <= 120) {
                return $age;
            }
        }

        return 0;
    }

    /** Parlimen NAME (uppercased) from constituency_code, else P.xxx code -> Bandar name. */
    private function resolveParlimen(array $r): ?string
    {
        $cc = strtoupper(trim((string) ($r['constituency_code'] ?? '')));
        if ($cc !== '') {
            return $cc;
        }
        if (preg_match('/\bP\.?\s*(\d{2,3})\b/i', (string) ($r['operation_name'] ?? ''), $m)) {
            $code = 'P'.$m[1];

            return $this->bandarByCode[$code] ?? null;
        }

        return null;
    }

    /** DUN NAME (uppercased) from operation_name "...| N.xx <NAME>", else N.xx code -> Kadun name. */
    private function resolveKadun(array $r): ?string
    {
        $op = (string) ($r['operation_name'] ?? '');
        if (preg_match('/N\.?\s*[0-9O]{1,3}\s+([^|]+)$/i', $op, $m)) {
            $name = strtoupper(trim($m[1]));
            if ($name !== '') {
                return $name;
            }
        }
        if (preg_match('/N\.?\s*([0-9O]{1,3})\b/i', $op, $m)) {
            $code = 'N'.str_pad(str_replace('O', '0', strtoupper($m[1])), 2, '0', STR_PAD_LEFT);

            return $this->kadunByCode[$code] ?? null;
        }

        return null;
    }

    /** Build in-memory name indexes over the roll, scoped to the parlimens + names present. */
    private function buildRollIndex(array $parlimenNames, array $neededNames, callable $norm): array
    {
        $idxKadun = [];
        $idxParlimen = [];
        if (empty($parlimenNames)) {
            return [$idxKadun, $idxParlimen];
        }

        $upper = array_values(array_unique(array_map('strtoupper', $parlimenNames)));
        $placeholders = implode(',', array_fill(0, count($upper), '?'));

        $query = PangkalanDataPengundi::query()
            ->whereRaw("UPPER(parlimen) IN ($placeholders)", $upper);

        $activeBatchIds = UploadBatch::activeIds();
        if (! empty($activeBatchIds)) {
            $query->whereIn('upload_batch_id', $activeBatchIds);
        }

        $query->select('id', 'no_ic', 'nama', 'kadun', 'daerah_mengundi', 'lokaliti', 'negeri', 'bangsa', 'tahun_lahir', 'parlimen')
            ->orderBy('id')
            ->chunkById(1000, function ($voters) use (&$idxKadun, &$idxParlimen, $neededNames, $norm) {
                foreach ($voters as $v) {
                    $n = $norm($v->nama);
                    if ($n === '' || ! isset($neededNames[$n])) {
                        continue;
                    }
                    $p = strtoupper(trim((string) $v->parlimen));
                    $rec = (object) [
                        'no_ic' => $v->no_ic,
                        'nama' => $v->nama,
                        'parlimen' => $v->parlimen,
                        'kadun' => $v->kadun,
                        'daerah_mengundi' => $v->daerah_mengundi,
                        'lokaliti' => $v->lokaliti,
                        'negeri' => $v->negeri,
                        'bangsa' => $v->bangsa,
                        'tahun_lahir' => $v->tahun_lahir,
                    ];
                    $idxParlimen[$p][$n][] = $rec;
                    $k = strtoupper(trim((string) $v->kadun));
                    if ($k !== '') {
                        $idxKadun[$p][$k][$n][] = $rec;
                    }
                }
            });

        return [$idxKadun, $idxParlimen];
    }

    private function loadCodeMaps(): void
    {
        $this->bandarByCode = Bandar::whereNotNull('kod_parlimen')->where('kod_parlimen', '!=', '')
            ->pluck('nama', 'kod_parlimen')->map(fn ($n) => strtoupper((string) $n))->toArray();
        $this->kadunByCode = Kadun::whereNotNull('kod_dun')->where('kod_dun', '!=', '')
            ->pluck('nama', 'kod_dun')->map(fn ($n) => strtoupper((string) $n))->toArray();
    }
}
