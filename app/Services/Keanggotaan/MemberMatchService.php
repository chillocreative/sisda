<?php

namespace App\Services\Keanggotaan;

use App\Models\DataPengundi;
use App\Models\HasilCulaan;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-references party members (and committee members) against SISDA's
 * existing voter data, keyed on no_ic:
 *
 *  - umur & jantina are derived from the IC itself (independent of any
 *    match) — first 6 digits = birth date, 12th digit parity = gender.
 *  - the DPPR/DPT voter roll (pangkalan_data_pengundi, active batches)
 *    gives kawasan / DUN / Cabang (parlimen) / bangsa. No roll match ⇒
 *    "pengundi luar kawasan".
 *  - the canvass tables (data_pengundi + hasil_culaan) give the latest
 *    voter_color; voter_color = 'hitam' ⇒ the member has been "dicula".
 *
 * Both the keanggotaan and keanggotaan_jawatankuasa tables share the same
 * cached-match columns, so the set-based sync below works for either one.
 */
class MemberMatchService
{
    /** Tables that may be synced (whitelist — names are interpolated into SQL). */
    private const SYNCABLE = ['keanggotaan', 'keanggotaan_jawatankuasa'];

    /** Age from the first 6 IC digits (YYMMDD), same pivot as the front-end forms. */
    public static function ageFromIc(string $ic): ?int
    {
        if (! preg_match('/^[0-9]{6}/', $ic)) {
            return null;
        }
        $yy = (int) substr($ic, 0, 2);
        $mm = (int) substr($ic, 2, 2);
        $dd = (int) substr($ic, 4, 2);
        $fullYear = $yy <= 25 ? 2000 + $yy : 1900 + $yy;
        if (! checkdate($mm, $dd, $fullYear)) {
            return null;
        }
        $age = (int) date('Y') - $fullYear;
        $monthNow = (int) date('n');
        $dayNow = (int) date('j');
        if ($monthNow < $mm || ($monthNow === $mm && $dayNow < $dd)) {
            $age--;
        }

        return ($age >= 0 && $age <= 150) ? $age : null;
    }

    /** Gender from the IC's 12th digit (odd = male). */
    public static function jantinaFromIc(string $ic): ?string
    {
        if (! preg_match('/^[0-9]{12}$/', $ic)) {
            return null;
        }

        return ((int) substr($ic, 11, 1)) % 2 === 1 ? 'LELAKI' : 'PEREMPUAN';
    }

    /**
     * Resolve a single IC to its cached-match column values — used when a
     * member is added or edited manually.
     */
    public function match(string $ic): array
    {
        $ic = trim($ic);

        $base = [
            'matched_kadun' => null,
            'matched_parlimen' => null,
            'matched_negeri' => null,
            'tahun_lahir' => null,
            'umur' => self::ageFromIc($ic),
            'bangsa' => null,
            'jantina' => self::jantinaFromIc($ic),
            'voter_color' => null,
            'is_dicula' => false,
            'is_pendaftaran_baru' => false,
            'status_kawasan' => 'luar_kawasan',
        ];

        if ($ic === '') {
            return $base;
        }

        // Match against the combined voter roll: active DPPR upload batches plus
        // any DPT-uploaded rows (DPT writes dpt_upload_id, not upload_batch_id).
        $activeIds = UploadBatch::activeIds();
        $hasDpt = Schema::hasColumn('pangkalan_data_pengundi', 'dpt_upload_id');
        $roll = PangkalanDataPengundi::where('no_ic', $ic)
            ->where('is_deceased', false)
            ->where(function ($q) use ($activeIds, $hasDpt) {
                $q->whereIn('upload_batch_id', $activeIds ?: [-1]);
                if ($hasDpt) {
                    $q->orWhereNotNull('dpt_upload_id');
                }
            })
            ->first();

        $color = $this->latestVoterColor($ic);
        $base['voter_color'] = $color;
        $base['is_dicula'] = $color === 'hitam';

        if (! $roll) {
            return $base;
        }

        return array_merge($base, [
            'matched_kadun' => $roll->kadun,
            'matched_parlimen' => $roll->parlimen,
            'matched_negeri' => $roll->negeri,
            'tahun_lahir' => $roll->tahun_lahir,
            'bangsa' => $roll->bangsa,
            // Prefer the roll's recorded gender, fall back to the IC.
            'jantina' => $roll->jantina ?: $base['jantina'],
            'is_pendaftaran_baru' => (bool) $roll->pendaftaran_baru,
            'status_kawasan' => 'dalam_kawasan',
        ]);
    }

    /** Latest canvass colour for an IC (data_pengundi wins over hasil_culaan). */
    private function latestVoterColor(string $ic): ?string
    {
        $dp = DataPengundi::where('no_ic', $ic)->where('is_deceased', false)
            ->orderByDesc('id')->value('voter_color');
        if ($dp !== null && $dp !== '') {
            return $dp;
        }

        return HasilCulaan::where('no_ic', $ic)->where('is_deceased', false)
            ->orderByDesc('id')->value('voter_color') ?: null;
    }

    /**
     * Recompute cached-match columns for every row in a table (optionally
     * scoped to one batch) in set-based statements. Far cheaper than
     * per-row matching for bulk imports.
     */
    public function syncTable(string $table, ?int $batchId = null): void
    {
        if (! in_array($table, self::SYNCABLE, true)) {
            throw new \InvalidArgumentException("Cannot sync table [{$table}].");
        }

        $scope = $batchId !== null ? ' WHERE batch_id = ?' : '';
        $scopeK = $batchId !== null ? ' WHERE k.batch_id = ?' : '';
        $bind = $batchId !== null ? [$batchId] : [];

        // 1. Reset roll/canvass-derived fields to the "luar kawasan" baseline.
        DB::update("
            UPDATE {$table} SET
                matched_kadun = NULL, matched_parlimen = NULL, matched_negeri = NULL,
                tahun_lahir = NULL, bangsa = NULL, voter_color = NULL,
                is_dicula = 0, is_pendaftaran_baru = 0, status_kawasan = 'luar_kawasan'
            {$scope}
        ", $bind);

        // 2. IC-derived umur & jantina (independent of any match).
        DB::update("
            UPDATE {$table} SET
                umur = CASE WHEN no_ic REGEXP '^[0-9]{6}'
                    THEN TIMESTAMPDIFF(
                        YEAR,
                        STR_TO_DATE(CONCAT(IF(CAST(SUBSTRING(no_ic,1,2) AS UNSIGNED) <= 25, '20', '19'), SUBSTRING(no_ic,1,6)), '%Y%m%d'),
                        CURDATE()
                    ) ELSE umur END,
                jantina = CASE WHEN no_ic REGEXP '^[0-9]{12}$'
                    THEN IF(MOD(CAST(SUBSTRING(no_ic,12,1) AS UNSIGNED), 2) = 1, 'LELAKI', 'PEREMPUAN')
                    ELSE jantina END
            {$scope}
        ", $bind);

        // 3. Roll match → kawasan / DUN / Cabang / bangsa / registration status.
        // Combined voter roll: active DPPR upload batches plus DPT-uploaded rows
        // (DPT writes dpt_upload_id, not upload_batch_id).
        $activeIds = UploadBatch::activeIds();
        $hasDpt = Schema::hasColumn('pangkalan_data_pengundi', 'dpt_upload_id');
        $sourceConds = [];
        $sourceBinds = [];
        if ($activeIds !== []) {
            $placeholders = implode(',', array_fill(0, count($activeIds), '?'));
            $sourceConds[] = "upload_batch_id IN ({$placeholders})";
            $sourceBinds = array_merge($sourceBinds, $activeIds);
        }
        if ($hasDpt) {
            $sourceConds[] = 'dpt_upload_id IS NOT NULL';
        }
        if ($sourceConds !== []) {
            $sourceWhere = '('.implode(' OR ', $sourceConds).')';
            DB::update("
                UPDATE {$table} k
                JOIN (
                    SELECT no_ic,
                           MAX(kadun) AS kadun, MAX(parlimen) AS parlimen, MAX(negeri) AS negeri,
                           MAX(bangsa) AS bangsa, MAX(jantina) AS jantina,
                           MAX(tahun_lahir) AS tahun_lahir, MAX(pendaftaran_baru) AS pendaftaran_baru
                      FROM pangkalan_data_pengundi
                     WHERE is_deceased = 0 AND {$sourceWhere}
                     GROUP BY no_ic
                ) p ON p.no_ic = k.no_ic
                SET k.matched_kadun = p.kadun,
                    k.matched_parlimen = p.parlimen,
                    k.matched_negeri = p.negeri,
                    k.bangsa = p.bangsa,
                    k.jantina = COALESCE(NULLIF(p.jantina, ''), k.jantina),
                    k.tahun_lahir = p.tahun_lahir,
                    k.is_pendaftaran_baru = p.pendaftaran_baru,
                    k.status_kawasan = 'dalam_kawasan'
                {$scopeK}
            ", array_merge($sourceBinds, $bind));
        }

        // 4. Canvass match → latest voter_color, "dicula" = hitam.
        DB::update("
            UPDATE {$table} k
            JOIN (
                SELECT no_ic, voter_color FROM (
                    SELECT no_ic, voter_color,
                           ROW_NUMBER() OVER (PARTITION BY no_ic ORDER BY created_at DESC, id DESC) AS rn
                      FROM (
                          SELECT no_ic, voter_color, created_at, id FROM data_pengundi WHERE is_deceased = 0 AND no_ic <> ''
                          UNION ALL
                          SELECT no_ic, voter_color, created_at, id FROM hasil_culaan WHERE is_deceased = 0 AND no_ic <> ''
                      ) u
                ) d WHERE d.rn = 1
            ) c ON c.no_ic = k.no_ic
            SET k.voter_color = c.voter_color,
                k.is_dicula = (c.voter_color = 'hitam')
            {$scopeK}
        ", $bind);
    }
}
