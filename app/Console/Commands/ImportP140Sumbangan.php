<?php

namespace App\Console\Commands;

use App\Models\DataPengundi;
use App\Models\HasilCulaan;
use App\Models\PangkalanDataPengundi;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Import PKR Segamat P140 "Permohonan Bantuan" records into hasil_culaan as
 * sumbangan history, matched to existing voters by IC.
 *
 * Input = the pdftotext -layout text of the PDF, e.g.:
 *   pdftotext -layout "SENARAI PENERIMA BANTUAN P140.pdf" storage/app/p140_extract.txt
 *
 * MAPPING (semantic-correct — the PDF's two columns are both "reason"-like):
 *   PDF "Jenis Permohonan" (program)  -> tujuan_sumbangan  (SEBAB / reason)
 *   PDF "Tujuan Permohonan" (specific) -> jenis_sumbangan   (BENTUK / form) where identifiable
 * The raw PDF text is preserved verbatim in `nota` so nothing is lost.
 *
 * Only recipients whose IC already exists in data_pengundi get a record
 * (crosscheck / "tiada overlap"); non-matches and malformed ICs are reported,
 * never silently created. Re-running is safe — dedup is by `no_rujukan`.
 */
class ImportP140Sumbangan extends Command
{
    protected $signature = 'sumbangan:import-p140
        {file=storage/app/p140_extract.txt : Path to the pdftotext -layout output}
        {--user= : users.id to record as submitted_by (default: first super_admin)}
        {--from-roll : For recipients not yet in data_pengundi, create the voter from the pangkalan roll (by IC) before attaching sumbangan}
        {--dry-run : Parse, map and match only; create nothing}';

    protected $description = 'Import P140 Segamat aid applications into hasil_culaan (matched to voters by IC)';

    /** PDF "Jenis Permohonan"/"Tujuan Permohonan" keywords -> tujuan_sumbangan (SEBAB). Order = priority. */
    private array $tujuanRules = [
        'Pendidikan / Persekolahan (Sekolah / IPT)' => ['PENDIDIKAN', 'PENDIDKAN', 'SEKOLAH', 'BELAJAR', 'YURAN', 'IPT', 'UNIVERSITI', 'KOLEJ', 'PELAJAR'],
        'Kematian'                                  => ['KEMATIAN', 'KHAIRAT', 'PENGEBUMIAN'],
        'Bencana (Banjir / Ribut / Kebakaran)'      => ['BENCANA', 'BANJIR', 'RIBUT', 'KEBAKARAN', 'TERBAKAR', 'KEROSAKAN RUMAH'],
        'Modal / Bantuan Perniagaan'                => ['PERNIAGAAN', 'MODAL', 'USAHAWAN', 'PERUSAHAAN'],
        'Masalah Kesihatan / Perubatan'             => ['KESIHATAN', 'PERUBATAN', 'HOSPITAL', 'RAWATAN', 'PESAKIT', 'KERUSI RODA', 'TONGKAT', 'OKSIGEN', 'PAMPERS'],
        'Warga Emas'                                => ['WARGA EMAS', 'ORANG TUA'],
        'Kebajikan / Perbelanjaan Harian'           => ['HARIAN', 'KEBAJIKAN', 'KEWANGAN', 'SARA', 'SUBSIDI', 'BAKUL', 'TONG GAS', 'BIL', 'TUNGGAKAN', 'MISKIN', 'ASNAF'],
    ];

    /** PDF text keywords -> jenis_sumbangan (BENTUK / form). Order = priority. */
    private array $jenisRules = [
        'Subsidi Tong Gas'                => ['SUBSIDI TONG', 'TONG GAS', 'GAS'],
        'Hamper Barangan Keperluan Dapur' => ['BAKUL', 'DAPUR', 'MAKANAN', 'HAMPER'],
        'Peralatan / Barangan Perubatan'  => ['KERUSI RODA', 'TONGKAT', 'PAMPERS', 'OKSIGEN', 'SUSU', 'CERMIN MATA', 'ALAT PERUBATAN'],
        'Peralatan Perniagaan'            => ['FREEZER', 'MESIN', 'KHEMAH', 'GERAI', 'PERALATAN PERNIAGAAN', 'STALL'],
        'Peralatan Pendidikan'            => ['LAPTOP', 'KOMPUTER', 'ALAT TULIS'],
        'Bayaran Bil / Tunggakan'         => ['BIL', 'TUNGGAKAN', 'TNB', 'SAJ', 'SEWA', 'ELEKTRIK', 'AIR'],
        'Wang Tunai'                      => ['KEWANGAN', 'WANG TUNAI', 'TUNAI'],
    ];

    private array $statusWords = ['Baru', 'Lulus', 'Dibayar', 'Ditolak', 'Batal', 'Selesai'];

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            $this->error("File not found: {$this->argument('file')}");
            return self::FAILURE;
        }

        $userId = $this->option('user') ?: optional(User::where('role', 'super_admin')->first())->id;
        if (! $userId) {
            $this->error('No submitted_by user. Pass --user=<id> or create a super_admin.');
            return self::FAILURE;
        }

        $records = $this->parse(file_get_contents($path));
        $this->info('Parsed '.count($records).' application rows.');

        if ($this->option('dry-run')) {
            $rows = [];
            foreach (array_slice($records, 0, 12) as $r) {
                $rows[] = [
                    mb_substr($r['permohonan'], 0, 46),
                    $this->resolve($this->tujuanRules, $r['permohonan'], 'Lain-lain'),
                    $this->resolve($this->jenisRules, $r['permohonan'], 'Tiada'),
                ];
            }
            $this->newLine();
            $this->line('<comment>Mapping preview (PDF Permohonan text -> tujuan_sumbangan [SEBAB] / jenis_sumbangan [BENTUK]):</comment>');
            $this->table(['PDF Jenis + Tujuan Permohonan', 'tujuan_sumbangan', 'jenis_sumbangan'], $rows);
        }

        $fromRoll = $this->option('from-roll');
        $dry = $this->option('dry-run');
        $stats = ['created' => 0, 'voter_created' => 0, 'voter_existing' => 0, 'dup' => 0, 'unmatched' => 0, 'not_in_roll' => 0, 'no_ic' => 0, 'malformed' => 0];
        $unmatched = [];

        foreach ($records as $r) {
            $ic = $r['no_kp'];
            if ($ic === '' || $ic === null) {
                $stats['no_ic']++;
                continue;
            }
            if (! preg_match('/^\d{12}$/', (string) $ic)) {
                $stats['malformed']++;
                continue;
            }

            $voter = DataPengundi::where('no_ic', $ic)->first();
            if ($voter) {
                $stats['voter_existing']++;
            } elseif ($fromRoll) {
                // Create the voter from the authoritative roll (by IC).
                $roll = PangkalanDataPengundi::where('no_ic', $ic)->first();
                if (! $roll) {
                    $stats['not_in_roll']++;
                    $unmatched[] = "{$ic}  {$r['nama']}";
                    continue;
                }
                $stats['voter_created']++;
                if (! $dry) {
                    $voter = $this->createVoterFromRoll($roll, $userId);
                }
            } else {
                $stats['unmatched']++;
                $unmatched[] = "{$ic}  {$r['nama']}";
                continue;
            }

            if ($r['no_rujukan'] && HasilCulaan::where('no_rujukan', $r['no_rujukan'])->exists()) {
                $stats['dup']++;
                continue;
            }

            $tujuan = $this->resolve($this->tujuanRules, $r['permohonan'], 'Lain-lain');
            $jenis  = $this->resolve($this->jenisRules, $r['permohonan'], 'Tiada');

            if ($this->option('dry-run')) {
                $stats['created']++;
                continue;
            }

            HasilCulaan::create([
                'nama'             => $voter->nama,
                'no_ic'            => $voter->no_ic,
                'umur'             => $voter->umur ?? 0,
                'no_tel'           => $voter->no_tel ?? '-',
                'bangsa'           => $voter->bangsa ?? '-',
                'alamat'           => $voter->alamat ?? '-',
                'poskod'           => $voter->poskod ?? '-',
                'negeri'           => $voter->negeri,
                'bandar'           => $voter->bandar,
                'parlimen'         => $voter->parlimen,
                'kadun'            => $voter->kadun,
                'mpkk'             => $voter->mpkk,
                'daerah_mengundi'  => $voter->daerah_mengundi,
                'lokaliti'         => $voter->lokaliti,
                'jenis_sumbangan'  => $jenis,
                'tujuan_sumbangan' => $tujuan,
                'status_sumbangan' => $r['status'] ?: 'Baru',
                'no_rujukan'       => $r['no_rujukan'],
                'tarikh_sumbangan' => $r['tarikh'],
                'jumlah_dipohon'   => $r['jumlah_dipohon'],
                'jumlah_dilulus'   => $r['jumlah_dilulus'],
                'jumlah_dibayar'   => $r['jumlah_dibayar'],
                'nota'             => "P140 Permohonan: {$r['permohonan']}".($r['no_rujukan'] ? " (Ruj: {$r['no_rujukan']})" : ''),
                'submitted_by'     => $userId,
                'sumber'           => 'import',
            ]);
            $stats['created']++;
        }

        $this->newLine();
        $this->table(['Result', 'Count'], [
            [$dry ? 'Sumbangan — would create' : 'Sumbangan — created', $stats['created']],
            [$dry ? 'Voter — would create from roll' : 'Voter — created from roll', $stats['voter_created']],
            ['Voter — already existed', $stats['voter_existing']],
            ['Skipped (duplicate ref)', $stats['dup']],
            ['Unmatched IC (valid, not a voter; use --from-roll)', $stats['unmatched']],
            ['Not in roll either (cannot enrich)', $stats['not_in_roll']],
            ['No IC in source (blank No KP)', $stats['no_ic']],
            ['Malformed IC (not 12 digits)', $stats['malformed']],
        ]);

        if ($unmatched && $this->option('dry-run')) {
            $this->newLine();
            $this->warn('Sample unmatched (first 15):');
            foreach (array_slice($unmatched, 0, 15) as $u) {
                $this->line('  '.$u);
            }
        }

        return self::SUCCESS;
    }

    /** Create a data_pengundi voter enriched from the authoritative roll row. */
    private function createVoterFromRoll(PangkalanDataPengundi $roll, int $userId): DataPengundi
    {
        $umur = ($roll->tahun_lahir && ctype_digit((string) $roll->tahun_lahir))
            ? max(0, now()->year - (int) $roll->tahun_lahir)
            : 0;

        return DataPengundi::create([
            'nama'            => $roll->nama ?: 'TIADA NAMA',
            'no_ic'           => $roll->no_ic,
            'umur'            => $umur,
            'no_tel'          => '-',
            'bangsa'          => $roll->bangsa ?: '-',
            'alamat'          => '-',
            'poskod'          => '-',
            'negeri'          => $roll->negeri ?: '-',
            'bandar'          => $roll->parlimen ?: '-',
            'parlimen'        => $roll->parlimen,
            'kadun'           => $roll->kadun,
            'mpkk'            => null,
            'daerah_mengundi' => $roll->daerah_mengundi,
            'lokaliti'        => $roll->lokaliti,
            'submitted_by'    => $userId,
            'sumber'          => 'import',
        ]);
    }

    /** First canonical value whose keyword appears in $text (uppercased); else $default. */
    private function resolve(array $rules, string $text, string $default): string
    {
        $t = mb_strtoupper($text);
        foreach ($rules as $canonical => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($t, $kw)) {
                    return $canonical;
                }
            }
        }
        return $default;
    }

    /**
     * Parse pdftotext -layout output into application records.
     * A record block starts at "<n> SGMT/..." and ends at the next such line
     * or a "BANK NAME" / page-header / "Printed on" line.
     */
    private function parse(string $text): array
    {
        $lines = preg_split('/\R/', $text);
        $blocks = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\s*\d+\s+SGMT\//', $line)) {
                if ($current !== null) {
                    $blocks[] = $current;
                }
                $current = [$line];
                continue;
            }
            if ($current === null) {
                continue;
            }
            if (preg_match('/^\s*(BANK NAME|Printed on|No\.\s+No Rujukan)/i', $line)) {
                $blocks[] = $current;
                $current = null;
                continue;
            }
            $current[] = $line;
        }
        if ($current !== null) {
            $blocks[] = $current;
        }

        $out = [];
        foreach ($blocks as $block) {
            $rec = $this->parseBlock($block);
            if ($rec) {
                $out[] = $rec;
            }
        }
        return $out;
    }

    private function parseBlock(array $block): ?array
    {
        $full = implode("\n", $block);
        // Strip the repeated column-label phrase that bleeds into rows.
        $clean = preg_replace('/Dipohon\s+Dilulus\s+Dibayar\s+Transaksi/u', ' ', $full);

        preg_match('/SGMT\/[A-Z]+\/\d{4}\/\d+/', $full, $mRef);
        preg_match('/\b(\d{12,13})\b/', preg_replace('/\d+\.\d{2}/', '', $clean), $mIc);
        preg_match('/\b(\d{2}\/\d{2}\/\d{4})\b/', $full, $mDate);

        // Textual columns via fixed-width slicing, accumulated across wrapped lines.
        // Jenis + Tujuan Permohonan share one region [79,113) captured together —
        // the internal boundary drifts and both feed the keyword resolvers anyway.
        $nama = $this->sliceJoin($block, 34, 31);
        $permohonan = $this->sliceJoin($block, 79, 34);

        // Amounts (first three money tokens after label-strip).
        preg_match_all('/\d[\d,]*\.\d{2}/', $clean, $mAmt);
        $amt = array_map(fn ($v) => (float) str_replace(',', '', $v), $mAmt[0] ?? []);

        // Status = last standalone status word (labels already stripped).
        $status = null;
        if (preg_match_all('/\b('.implode('|', $this->statusWords).')\b/u', $clean, $mSt)) {
            $status = end($mSt[1]);
        }

        $ic = $mIc[1] ?? '';

        // Discard blocks with neither a ref nor an IC (page artefacts).
        if (! ($mRef[0] ?? null) && ! $ic) {
            return null;
        }

        return [
            'no_rujukan'        => $mRef[0] ?? '',
            'tarikh'            => isset($mDate[1]) ? $this->toDate($mDate[1]) : null,
            'nama'             => $this->clean($nama),
            'no_kp'            => $ic,
            'permohonan'       => $this->clean($permohonan),
            'jumlah_dipohon'   => $amt[0] ?? null,
            'jumlah_dilulus'   => $amt[1] ?? null,
            'jumlah_dibayar'   => $amt[2] ?? null,
            'status'           => $status,
        ];
    }

    /** Slice [start, start+len) from each line, drop empties, join and squeeze spaces. */
    private function sliceJoin(array $block, int $start, int $len): string
    {
        $parts = [];
        foreach ($block as $line) {
            $frag = trim(mb_substr($line, $start, $len));
            if ($frag !== '') {
                $parts[] = $frag;
            }
        }
        return implode(' ', $parts);
    }

    private function clean(string $s): string
    {
        $s = preg_replace('/\b(Mohon|Dipohon|Dilulus|Dibayar|Transaksi|Baru|Lulus|Ditolak)\b/u', ' ', $s);
        $s = preg_replace('/\d+(?:[.,]\d+)?/', ' ', $s); // drop whole numbers incl. amounts like 0.00
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function toDate(string $dmy): ?string
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $dmy)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
