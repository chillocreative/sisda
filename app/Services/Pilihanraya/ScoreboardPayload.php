<?php

namespace App\Services\Pilihanraya;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Scoreboard;
use App\Support\Borang14Reference;
use App\Support\PartyLogo;
use App\Support\SeatScope;
use Illuminate\Support\Arr;

/**
 * Membina muatan papan markah langsung bagi satu kerusi. Logik baca tulen —
 * tiada kesedaran tentang Request, dipanggil oleh laluan pemilik dan awam.
 *
 * "Tiada data" BUKAN sifar: apabila pemilik belum memilih sumber Borang 14,
 * muatan mengembalikan ready=false TANPA kunci angka langsung, supaya antara
 * muka memapar "—" dan bukan "0".
 */
class ScoreboardPayload
{
    private const PENJURU = [2 => '1 vs 1', 3 => '3 Penjuru', 4 => '4 Penjuru', 5 => '5 Penjuru', 6 => '6 Penjuru'];

    /**
     * Kunci yang HANYA untuk skrin pemilik dan tidak boleh keluar pada laluan
     * awam tanpa log masuk:
     *  - 'dikemaskini' membawa users.name pengendali SISDA yang menyimpan
     *    terakhir — nama sebenar seorang petugas.
     *  - 'sumber' mendedahkan id + label senario Borang 14 dalaman.
     *  - 'tajuk_amaran' ialah arahan pembetulan untuk pengendali; penonton
     *    awam tidak boleh berbuat apa-apa dengannya dan tidak perlu tahu
     *    bahawa sepanduk papan ini pernah salah.
     * Halaman awam tidak memerlukan ketiga-tiganya (Pages/Public/Scoreboard.jsx
     * tidak merujuknya langsung), jadi ia ditapis di SATU tempat sahaja —
     * forPublicSeat() — supaya laluan pemilik terbukti tidak tersentuh.
     */
    private const KUNCI_PEMILIK = ['dikemaskini', 'sumber', 'tajuk_amaran'];

    /**
     * Kod kerusi di DALAM teks bebas: "N.15", "N15", "P 132". Sengaja longgar
     * pada titik/ruang kerana pengendali menaipnya dengan pelbagai cara, tetapi
     * ketat pada bentuk (satu huruf N/P + digit) supaya perkataan biasa tidak
     * tersilap ditangkap.
     */
    private const POLA_KOD_DALAM_TEKS = '/\b([NP])[.\s]*(\d{1,3})\b/i';

    public static function forSeat(string $type, int $id): array
    {
        $reference = $type === SeatScope::PARLIMEN
            ? Borang14Reference::forBandar($id)
            : Borang14Reference::forKadun($id);

        $board = Scoreboard::where('kawasan_type', $type)->where('kawasan_id', $id)->first();

        // Identiti papan diterbitkan daripada KERUSI, bukan daripada teks yang
        // ditaip. `title` ialah sepanduk bebas, dan sepanduk yang ditiru
        // daripada kerusi lain pernah menyebabkan papan DUN Gemas mengisytihar
        // dirinya "N.15 JUASSEH" — tiada semakan kebenaran boleh menghalang
        // itu, kerana kerusinya memang betul; yang menipu ialah teksnya.
        $identiti = self::identitiKerusi($type, $id);
        $amaranTajuk = self::amaranTajuk($board?->title, $identiti['kod']);
        $tajuk = $amaranTajuk === null ? (($board?->title ?: null) ?? 'SCOREBOARD') : 'SCOREBOARD';

        if (! $reference) {
            return ['hasData' => false, 'ready' => false, 'sumber' => null, 'liputan' => null];
        }

        $form = $board?->borang14_form_id
            ? Borang14Form::find($board->borang14_form_id)
            : null;

        if (! $form) {
            return [
                'hasData' => true,
                'ready' => false,
                'needsBorang14' => true,
                'sumber' => null,
                'title' => $tajuk,
                'identiti' => $identiti,
                'tajuk_amaran' => $amaranTajuk,
                'status' => $board?->status ?? Scoreboard::STATUS_DRAF,
                'kod' => $board?->kod,
                'liputan' => null,
            ];
        }

        $candidates = collect($board?->candidates ?? [])->keyBy('slot');
        $kami = array_map('intval', $board?->pihak_kami ?? []);

        // Kerusi Parlimen pada pilihanraya serentak TIDAK menyimpan undi pada
        // borangnya sendiri — borang itu hanyalah TAKRIFAN calon (parties +
        // penjuru dikongsi), dan undi sebenar berada pada borang DUN yang
        // memautinya. Borang14RollUp mengumpul undi tersebut dan membawa
        // liputan (bilangan DUN yang sudah melapor) supaya kiraan SEPARA pada
        // malam keputusan tidak kelihatan seperti keputusan muktamad.
        if ($type === SeatScope::PARLIMEN) {
            $rollUp = Borang14RollUp::forParlimen($id, $form->tahun);

            // Tiada borang Parlimen langsung — TIDAK DIKETAHUI, bukan kiraan
            // sifar. Papar sebagai belum sedia, tanpa kunci undi.
            if (! $rollUp) {
                return [
                    'hasData' => true,
                    'ready' => false,
                    'needsBorang14' => true,
                    'sumber' => null,
                    'title' => $tajuk,
                    'identiti' => $identiti,
                    'tajuk_amaran' => $amaranTajuk,
                    'status' => $board?->status ?? Scoreboard::STATUS_DRAF,
                    'kod' => $board?->kod,
                    'liputan' => null,
                ];
            }

            $penjuru = (int) $rollUp['penjuru'];
            $parties = $rollUp['parties'];
            $liputan = $rollUp['liputan'];

            $tally = array_fill(1, $penjuru, 0);
            foreach ($rollUp['undi'] as $slot => $total) {
                if ($slot >= 1 && $slot <= $penjuru) {
                    $tally[$slot] = (int) $total;
                }
            }
        } else {
            $penjuru = (int) $form->penjuru;
            $parties = $form->parties ?? [];
            $liputan = null;

            $tally = array_fill(1, $penjuru, 0);

            $sums = $form->votesFor(Borang14Vote::CONTEST_DUN)->where('slot', '>=', 1)
                ->selectRaw('slot, SUM(undi) as total')->groupBy('slot')->pluck('total', 'slot');
            foreach ($sums as $slot => $total) {
                if ($slot >= 1 && $slot <= $penjuru) {
                    $tally[$slot] = (int) $total;
                }
            }
        }

        $berdaftar = Borang14Reference::jumlahBerdaftar($reference);

        $rows = [];
        $undiKami = 0;
        foreach (range(1, $penjuru) as $slot) {
            $isKami = in_array($slot, $kami, true);
            $undi = $tally[$slot] ?? 0;
            if ($isKami) {
                $undiKami += $undi;
            }
            $rows[] = [
                'slot' => $slot,
                'parti' => $parties[$slot - 1]['nama'] ?? "Parti {$slot}",
                'is_kami' => $isKami,
                'calon' => $candidates[$slot]['nama'] ?? null,
                'undi' => $undi,
            ];
        }

        $totalKeluar = array_sum($tally);

        return [
            'hasData' => true,
            'ready' => true,
            'penjuru' => $penjuru,
            'penjuru_label' => self::PENJURU[$penjuru] ?? '',
            'title' => $tajuk,
            'identiti' => $identiti,
            'tajuk_amaran' => $amaranTajuk,
            'logo_url' => $board?->logo_path ? asset($board->logo_path) : asset('images/logo.png'),
            'minima' => $board?->minima,
            'kod' => $board?->kod,
            'status' => $board?->status ?? Scoreboard::STATUS_DRAF,
            'dun' => $reference['dun'] ?? null,
            'parlimen' => $reference['parlimen'] ?? null,
            'negeri' => $reference['negeri'] ?? null,
            'rows' => $rows,
            'undi_kami' => $undiKami,
            'total_keluar' => $totalKeluar,
            'total_berdaftar' => $berdaftar,
            'leader_slot' => $totalKeluar > 0 ? collect($rows)->sortByDesc('undi')->first()['slot'] : null,
            'sumber' => ['id' => $form->id, 'label' => self::labelSumber($form)],
            'dikemaskini' => self::dikemaskini($board),
            'liputan' => $liputan,
        ];
    }

    /**
     * Unjuran AWAM: muatan yang sama, tolak kunci milik pemilik. Digunakan oleh
     * PublicScoreboardController sahaja — lihat KUNCI_PEMILIK.
     */
    public static function forPublicSeat(string $type, int $id): array
    {
        return Arr::except(self::forSeat($type, $id), self::KUNCI_PEMILIK);
    }

    /**
     * Identiti kerusi daripada DATA INDUK — sumber tunggal bagi "papan ini
     * kerusi mana". Tidak boleh disunting daripada skrin Scoreboard, jadi ia
     * tidak boleh menyimpang daripada kerusi yang kebenaran pengguna berikan.
     *
     * @return array{kod: ?string, nama: ?string, jenis: string, label: string}
     */
    private static function identitiKerusi(string $type, int $id): array
    {
        $parlimen = $type === SeatScope::PARLIMEN;

        $row = $parlimen
            ? Bandar::whereKey($id)->first(['nama', 'kod_parlimen'])
            : Kadun::whereKey($id)->first(['nama', 'kod_dun']);

        $kod = strtoupper(trim((string) ($parlimen ? $row?->kod_parlimen : $row?->kod_dun)));
        $nama = strtoupper(trim((string) $row?->nama));
        $jenis = $parlimen ? 'PARLIMEN' : 'DUN';

        // Kerusi tanpa kod masih mempunyai nama — label jatuh balik kepadanya
        // dan bukan kepada rentetan kosong.
        $label = trim(($kod !== '' ? $kod.' ' : '').($nama !== '' ? $nama : $jenis));

        return [
            'kod' => $kod !== '' ? $kod : null,
            'nama' => $nama !== '' ? $nama : null,
            'jenis' => $jenis,
            'label' => $label,
        ];
    }

    /**
     * Amaran apabila sepanduk yang ditaip MENDAKWA kerusi lain.
     *
     * Pencetusnya deterministik, bukan tekaan: kami hanya melihat kod kerusi
     * (N/P + digit) di dalam teks. Jika satu pun kod itu bukan kod papan ini,
     * sepanduk itu menipu dan digugurkan daripada paparan.
     *
     * Gagal-tutup apabila papan ini sendiri TIADA kod: teks yang mendakwa
     * "N.15" tidak dapat disahkan, jadi ia tetap digugurkan. Lebih baik
     * memapar "SCOREBOARD" yang hambar daripada nama kerusi yang mungkin
     * salah pada malam keputusan.
     *
     * Teks tanpa sebarang kod (cth. "PILIHAN RAYA NEGERI 2026") tidak pernah
     * mencetuskan apa-apa — pengendali bebas menamakan papan mereka.
     */
    private static function amaranTajuk(?string $title, ?string $kodKerusi): ?string
    {
        $teks = trim((string) $title);
        if ($teks === '' || ! preg_match_all(self::POLA_KOD_DALAM_TEKS, $teks, $padanan, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($padanan as $m) {
            $kodDitaip = strtoupper($m[1]).ltrim($m[2], '0');

            if ($kodKerusi !== null && self::normalKod($kodKerusi) === $kodDitaip) {
                continue;
            }

            return $kodKerusi === null
                ? "Tajuk papan menyebut kod kerusi \"{$m[0]}\" tetapi kerusi ini tiada Kod DUN/Parlimen dalam Data Induk, jadi ia tidak dapat disahkan. Tajuk itu tidak dipaparkan."
                : "Tajuk papan menyebut kod kerusi \"{$m[0]}\", tetapi papan ini ialah {$kodKerusi}. Tajuk itu tidak dipaparkan — betulkan dalam Tetapan.";
        }

        return null;
    }

    /** "N.05" dan "N5" ialah kod yang SAMA — bandingkan dalam satu bentuk. */
    private static function normalKod(string $kod): string
    {
        if (! preg_match(self::POLA_KOD_DALAM_TEKS, $kod, $m)) {
            return strtoupper(trim($kod));
        }

        return strtoupper($m[1]).ltrim($m[2], '0');
    }

    /**
     * Kad ringkas bagi senarai awam /scoreboard — satu kerusi, satu kad.
     *
     * Memulangkan null apabila papan itu belum sedia (tiada rujukan DPT, atau
     * pemilik belum memilih Borang 14). Itulah penapis "hanya yang ada Borang
     * 14": pemanggil membuang null, jadi kad yang terpapar SENTIASA membawa
     * angka yang benar-benar dikira daripada satu borang.
     *
     * Kerana kad hanya wujud bagi papan yang sedia, `undi` di sini ialah hasil
     * SUM(undi) sebenar — 0 bermakna "belum ada undi dimasukkan", bukan
     * "tidak diketahui". Nilai tidak diketahui tidak pernah sampai ke sini.
     */
    public static function kadAwam(string $type, int $id): ?array
    {
        $p = self::forPublicSeat($type, $id);

        if (empty($p['ready'])) {
            return null;
        }

        return [
            'kod' => $p['kod'],
            'jenis' => $type,
            // Nama daripada DATA INDUK (identiti), bukan daripada rujukan DPT
            // yang dipadan-rentetan — kad senarai awam mesti menamakan kerusi
            // yang sama seperti papan yang dibukanya.
            'nama' => $p['identiti']['nama'] ?? ($type === SeatScope::PARLIMEN ? ($p['parlimen'] ?? null) : ($p['dun'] ?? null)),
            'negeri' => $p['negeri'] ?? null,
            'parlimen' => $p['parlimen'] ?? null,
            'title' => $p['title'] ?? null,
            'leader_slot' => $p['leader_slot'],
            'total_keluar' => $p['total_keluar'],
            'liputan' => $p['liputan'],
            'calon' => array_map(fn ($r) => [
                'slot' => $r['slot'],
                'parti' => $r['parti'],
                'logo' => PartyLogo::url($r['parti']),
                'calon' => $r['calon'],
                'undi' => $r['undi'],
            ], $p['rows']),
        ];
    }

    /**
     * Siapa menyimpan tetapan terakhir. Papan DUN boleh disunting oleh pemilik
     * DUN DAN admin Parlimennya — tiada kunci, jadi perlanggaran dibuat
     * kelihatan sahaja.
     */
    private static function dikemaskini(?Scoreboard $board): ?array
    {
        if (! $board?->updated_by) {
            return null; // Belum pernah disimpan melalui borang — bukan "tiada suntingan".
        }

        $board->loadMissing('penyunting');

        return [
            'nama' => $board->penyunting?->name,
            'pada' => $board->updated_at?->toIso8601String(),
        ];
    }

    /**
     * "DUN GEMAS (N34) · PRN 2026 · 3 Penjuru"
     *
     * Kerusi diterbitkan daripada borang ITU SENDIRI (kawasan_type/kawasan_id
     * padanya), bukan daripada papan yang memaparkannya. Itu disengajakan:
     * saveSettings() menolak borang milik kerusi lain, tetapi baris lama boleh
     * memaut borang asing dari zaman sebelum pengawal itu wujud. Dengan kerusi
     * borang tertulis pada label, pautan silang begitu kelihatan pada dropdown
     * dan bukan tersembunyi di belakang "PRN 2026" yang boleh jadi milik
     * mana-mana kerusi.
     */
    public static function labelSumber(Borang14Form $form): string
    {
        $identiti = self::identitiKerusi((string) $form->kawasan_type, (int) $form->kawasan_id);

        $kerusi = trim($identiti['jenis'].' '.($identiti['nama'] ?? ''));
        if ($identiti['kod'] !== null) {
            $kerusi .= ' ('.$identiti['kod'].')';
        }

        return $kerusi
            .' · '.strtoupper((string) $form->jenis_pr).' '.$form->tahun
            .' · '.(self::PENJURU[(int) $form->penjuru] ?? $form->penjuru.' Penjuru');
    }
}
