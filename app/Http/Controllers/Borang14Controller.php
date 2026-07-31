<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Borang14DeletedForm;
use App\Models\Borang14Form;
use App\Models\Borang14Snapshot;
use App\Models\Borang14Upload;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\KeahlianParti;
use App\Models\Negeri;
use App\Models\User;
use App\Services\Borang14StrukturService;
use App\Services\Pilihanraya\KawasanResolver;
use App\Services\Pilihanraya\ScoresheetExtractor;
use App\Support\Borang14Reference;
use App\Support\SeatScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class Borang14Controller extends Controller
{
    /** Undi Awal & Undi Pos are combined into a single row only for this DUN. */
    private const BULOH_KASAP_KADUN_ID = 41;

    /** Folder scoresheet pada disk 'private' — tidak boleh dicapai melalui URL. */
    private const SCORESHEET_DIR = 'borang14-scoresheets';

    /**
     * Skop borang yang merekod DUA pertandingan sekaligus (PRU + PRN pada
     * scoresheet yang sama). Skop lain ialah nama pertandingan itu sendiri
     * ('parlimen' / 'dun'), jadi hanya nilai ketiga ini perlukan pemalar.
     */
    private const SKOP_KEDUA = 'kedua';

    /** Penjuru dropdown → number of party columns. */
    private const PENJURU = [
        2 => '1 vs 1',
        3 => '3 Penjuru',
        4 => '4 Penjuru',
        5 => '5 Penjuru',
        6 => '6 Penjuru',
    ];

    public function index(Request $request)
    {
        return Inertia::render('Pilihanraya/Borang14', [
            'negeriList'   => Negeri::orderBy('nama')->get(['id', 'nama']),
            'parlimenList' => Bandar::orderBy('nama')->get(['id', 'nama', 'negeri_id']),
            'kadunList'    => Kadun::orderBy('nama')->get(['id', 'nama', 'bandar_id']),
            'partiList'    => KeahlianParti::orderBy('nama')->get(['id', 'nama']),
            'penjuruOptions' => collect(self::PENJURU)->map(fn ($label, $val) => ['value' => (int) $val, 'label' => $label])->values(),
        ]);
    }

    /**
     * JSON payload for a chosen kawasan (Parlimen OR DUN) + jenis PR/tahun: reference
     * geography, saved parties & votes, and the form's review/publish state.
     *
     * Accepts EITHER `form_id` alone (used by the Upload tab's hand-off, which only
     * knows the form id — never the geography) OR the full
     * (kawasan_type, kawasan_id, jenis_pr, tahun) tuple used by the on-page picker.
     * Either way the response carries a `resolved` block so the caller can populate
     * its Negeri/Parlimen/DUN/Jenis PR/Tahun selects from whichever path was used.
     */
    public function data(Request $request, Borang14StrukturService $svc)
    {
        if ($request->filled('form_id')) {
            $form = Borang14Form::findOrFail((int) $request->input('form_id'));
            $kawasanType = $form->kawasan_type;
            $kawasanId = (int) $form->kawasan_id;
            $jenisPr = $form->jenis_pr;
            $tahun = (int) $form->tahun;
        } else {
            $kawasanType = $request->input('kawasan_type');
            $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

            $validated = $request->validate([
                'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
                'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
                'jenis_pr' => 'required|in:pru,prn,prk',
                'tahun'    => 'required|integer|between:1959,2100',
                'penjuru'  => 'nullable|integer|in:2,3,4,5,6', // accepted for backward compat; not required to find the form
            ]);

            $kawasanType = $validated['kawasan_type'];
            $kawasanId = (int) $validated['kawasan_id'];
            $jenisPr = $validated['jenis_pr'];
            $tahun = (int) $validated['tahun'];

            $form = Borang14Form::forKawasan($kawasanType, $kawasanId)
                ->where('jenis_pr', $jenisPr)
                ->where('tahun', $tahun)
                ->first();
        }

        ['reference' => $reference, 'inherited_from' => $inheritedFrom, 'asal' => $asal] = $this->resolveReference($kawasanType, $kawasanId, $form);

        // Jalur pertandingan borang ini SENDIRI. Kunci sel membawa contest
        // sebagai komponen pertama, jadi jalur PRU di bawah boleh berkongsi
        // ruang nama yang sama tanpa satu menulis ganti satu lagi.
        $votes = $form
            ? $form->votesFor($form->contestSendiri())->get(['pusat', 'saluran', 'slot', 'undi'])
                ->mapWithKeys(fn ($v) => [$this->cellKey($form->contestSendiri(), $v->pusat, $v->saluran, $v->slot) => $v->undi])
            : collect();

        $payload = [
            'reference' => $reference,
            'hasData'   => $reference !== null,
            'parties'   => $form->parties ?? [],
            'votes'     => $votes,
            'form' => $form ? [
                'id' => $form->id,
                'status' => $form->status,
                'source' => $form->source,
                'needs_review' => $form->needs_review,
                'crosscheck_issues' => $this->crosscheckIssues($form),
                'penjuru' => $form->penjuru,
            ] : null,
            'resolved' => array_merge(
                ['kawasan_type' => $kawasanType, 'kawasan_id' => $kawasanId, 'jenis_pr' => $jenisPr, 'tahun' => $tahun],
                $this->resolveIds($kawasanType, $kawasanId),
            ),
            // Keadaan permulaan bagi panel Sunting Struktur. Dikira di server
            // supaya peraturan penerbitan row_id (termasuk id terbitan bagi
            // struktur scoresheet/warisan yang tiada satu) hidup di SATU
            // tempat sahaja — dua pelaksanaan akan hanyut, dan hanyut di sini
            // bermakna undi dipadam sebagai ganti dipindahkan.
            // Disemai daripada rujukan yang DIPAPARKAN apabila borang ini
            // belum ada struktur sendiri — panel yang dibuka kosong di atas
            // grid yang penuh undi ialah punca pemadaman senyap.
            'struktur' => $this->strukturUntukPanel($svc, $form, $reference, $asal),
            // DUA syarat, bukan satu: peranan/status DAN asal grid. $asal
            // diguna semula daripada resolveReference() di atas — menyelesaikan
            // semula di sini bermakna DUA imbasan penuh roll DPT setiap muatan.
            'boleh_sunting_struktur' => $this->bolehSuntingStruktur(
                $request->user(),
                $form,
                ['kawasan_type' => $kawasanType, 'kawasan_id' => $kawasanId],
            ) && $this->strukturBolehDisunting($asal),
        ];

        // Deliberately OMITTED (not merely null) when nothing was inherited —
        // an inherited structure must be visible, never silent, but the key's
        // very presence is also how the frontend decides whether to render the
        // "diwarisi" notice at all.
        if ($inheritedFrom) {
            $payload['inherited_from'] = $inheritedFrom;
        }

        // Sengaja DITINGGALKAN (bukan sekadar null) apabila borang ini tidak
        // dipaut: muatan satu pertandingan mesti kekal serupa-bit dengan hari
        // ini, kerana itulah kes yang paling biasa dan ia sudah di produksi.
        if ($kontesParlimen = $this->kontesParlimen($form)) {
            $payload['kontes_parlimen'] = $kontesParlimen;
        }

        return response()->json($payload);
    }

    /**
     * Jalur PRU bagi skrin dua jalur: pertandingan Parlimen yang dipaut kepada
     * borang DUN ini, berserta undi PRU yang direkod PADA borang DUN ini.
     *
     * Pulangkan null apabila tiada pautan — pemanggil meninggalkan kunci itu
     * sepenuhnya supaya muatan satu pertandingan tidak berubah langsung.
     *
     * `penjuru` DI SINI datang daripada borang PARLIMEN, bukan borang DUN.
     * Pertandingan Parlimen mempunyai bilangan calonnya sendiri (3 di P133
     * berbanding 2 bagi N34); membaca penjuru borang DUN akan menolak atau
     * mengosongkan lajur calon secara senyap.
     */
    private function kontesParlimen(?Borang14Form $form): ?array
    {
        if (! $form || $form->borang14_form_parlimen_id === null) {
            return null;
        }

        $parlimen = $form->formParlimen;
        if (! $parlimen) {
            // Pautan menjadi yatim (borang Parlimen dipadam antara baca).
            // Kembali kepada mod satu pertandingan; jangan reka jalur kosong.
            return null;
        }

        return [
            'id' => $parlimen->id,
            // Nullable dengan sengaja — penjuru yang belum ditetapkan ialah
            // "tidak diketahui", bukan sifar lajur.
            'penjuru' => $parlimen->penjuru,
            'parties' => $parlimen->parties ?? [],
            'kawasan_nama' => $parlimen->kawasanNama(),
            // Undi PRU yang direkod pada borang DUN INI — bukan pada borang
            // Parlimen. Setiap DUN menyimpan keratan PRU-nya sendiri; jumlah
            // Parlimen ialah kerja Borang14RollUp, bukan skrin ini.
            'votes' => $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)
                ->get(['pusat', 'saluran', 'slot', 'undi'])
                ->mapWithKeys(fn ($v) => [
                    $this->cellKey(Borang14Vote::CONTEST_PARLIMEN, $v->pusat, $v->saluran, $v->slot) => $v->undi,
                ]),
        ];
    }

    /**
     * Persist the chosen party names for a (kawasan, jenis PR, tahun) scenario.
     *
     * `skop` menyatakan BERAPA pertandingan yang direkod oleh borang ini:
     * 'parlimen', 'dun', atau 'kedua' (serentak). Tiada `skop` bermakna skop
     * TUNGGAL yang sepadan dengan kawasan borang — muatan sebelum ciri ini,
     * dan laluan yang jauh paling biasa; ia mesti kekal serupa-bit.
     *
     * Apabila skop = 'kedua', senarai calon RATA daripada scoresheet dipisahkan
     * mengikut tanda `kontes` setiap parti: calon Parlimen dinomborkan semula
     * 1..n pada borang TAKRIFAN Parlimen, calon DUN dinomborkan semula 1..m
     * pada borang DUN ini. Pemisahan itu SENDIRI ialah satu-satunya rekod
     * penetapan pertandingan — tiada lajur tambahan yang boleh menyimpang.
     */
    public function saveParties(Request $request)
    {
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate([
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
            // Skop pertandingan borang. Nullable dengan sengaja: klien lama
            // (dan setiap borang sedia ada) tidak menghantarnya langsung.
            'skop'     => ['nullable', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN, self::SKOP_KEDUA])],
            'parties'  => 'array',
            'parties.*.slot' => 'required|integer|min:1|max:6',
            'parties.*.keahlian_parti_id' => 'nullable|integer',
            'parties.*.nama' => 'nullable|string|max:100',
            'parties.*.calon' => 'nullable|string|max:150',
            // WAJIB bagi setiap parti apabila skop = kedua — dikuatkuasakan di
            // bawah dan bukan dengan required_if, kerana skop lalai dikira
            // daripada kawasan_type borang dan bukan daripada muatan sahaja.
            'parties.*.kontes' => ['nullable', Rule::in([Borang14Vote::CONTEST_PARLIMEN, Borang14Vote::CONTEST_DUN])],
        ]);

        // Kebenaran KERUSI — sebelum sebarang bacaan atau tulisan. Gate laluan
        // ['auth','admin'] hanya menyemak PERANAN, jadi tanpa ini setiap admin
        // boleh menulis kerusi sesiapa; dan pada skop serentak sasarannya ialah
        // borang TAKRIFAN Parlimen yang DIKONGSI setiap DUN di bawahnya, lalu
        // Borang14RollUp menerbitkan undi DUN lain di bawah nama yang ditulis
        // di sini. Menegaskan pada DUN sudah memadai: pemilik DUN memiliki
        // Parlimen induknya menurut binaan, dan $indukId diterbitkan di server
        // daripada kadun.bandar_id, bukan daripada input.
        SeatScope::assert($request->user(), $validated['kawasan_type'], (int) $validated['kawasan_id']);

        // Pertandingan borang ini SENDIRI — sama dengan kawasannya, dan skop
        // lalai apabila pemanggil tidak menyatakan apa-apa.
        $sendiri = $validated['kawasan_type'] === Borang14Form::KAWASAN_PARLIMEN
            ? Borang14Form::KAWASAN_PARLIMEN
            : Borang14Form::KAWASAN_DUN;
        $skop = $validated['skop'] ?? $sendiri;
        $parties = $validated['parties'] ?? [];

        $form = Borang14Form::forKawasan($validated['kawasan_type'], $validated['kawasan_id'])
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        // Sekatan yang SAMA seperti panel Sunting Struktur — diguna semula dan
        // bukan ditulis kali kedua supaya kedua-dua laluan tulis tidak boleh
        // menyimpang. Ia menambah sekatan borang DITERBITKAN di atas semakan
        // kerusi di atas: nama calon pada borang yang sudah diterbitkan
        // menukar makna keputusan yang sudah dilihat orang ramai.
        abort_unless($this->bolehSuntingStruktur($request->user(), $form, $validated), 403, 'Unauthorized action.');

        $skopSemasa = $this->skopSemasa($form, $sendiri);

        // ---- Laluan SATU pertandingan: tepat seperti sebelum ciri skop.
        if ($skop === $sendiri) {
            // Satu-satunya tambahan: menukar skop KELUAR daripada susunan dua
            // pertandingan sesudah undi wujud akan menomborkan semula slot dan
            // memindahkan undi kepada calon yang salah.
            if ($skop !== $skopSemasa) {
                $this->assertTiadaUndi($form);
            }

            $form = Borang14Form::updateOrCreate(
                [
                    'kawasan_type' => $validated['kawasan_type'],
                    'kawasan_id'   => $validated['kawasan_id'],
                    'jenis_pr'     => $validated['jenis_pr'],
                    'tahun'        => $validated['tahun'],
                ],
                [
                    'penjuru' => $validated['penjuru'],
                    // `kontes` ialah keadaan UI semasa penetapan sahaja dan
                    // TIDAK PERNAH disimpan: dalam skop tunggal, pertandingan
                    // borang sudah menyatakan segalanya.
                    'parties' => array_map(function ($p) {
                        unset($p['kontes']);

                        return $p;
                    }, $parties),
                ],
            );

            return response()->json(['ok' => true, 'form_id' => $form->id]);
        }

        // ---- Skop yang melibatkan pertandingan Parlimen pada borang DUN.
        // Pautan hanya wujud dari borang DUN ke borang Parlimen, tidak pernah
        // sebaliknya: borang Parlimen ITU SENDIRI ialah takrifan calon PRU.
        if ($validated['kawasan_type'] !== Borang14Form::KAWASAN_DUN) {
            throw ValidationException::withMessages([
                'skop' => $skop === self::SKOP_KEDUA
                    ? 'Skop "Kedua-duanya (serentak)" hanya sah bagi borang DUN. Borang Parlimen itu sendiri '.
                        'ialah takrifan calon PRU, jadi ia tidak boleh dipaut kepada dirinya sendiri.'
                    : 'Borang Parlimen hanya boleh merekod pertandingan Parlimen. Simpan pertandingan DUN '.
                        'pada borang DUN berkenaan.',
            ]);
        }

        // Pisahkan senarai rata mengikut tanda pertandingan setiap parti.
        if ($skop === self::SKOP_KEDUA) {
            foreach ($parties as $p) {
                if (empty($p['kontes'])) {
                    throw ValidationException::withMessages([
                        'parties' => 'Setiap calon mesti ditanda milik pertandingan Parlimen atau DUN apabila '.
                            'skop borang ialah kedua-duanya (serentak).',
                    ]);
                }
            }

            $calonParlimen = $this->nomborSemula($parties, Borang14Vote::CONTEST_PARLIMEN);
            $calonDun = $this->nomborSemula($parties, Borang14Vote::CONTEST_DUN);

            $this->assertBilanganCalon($calonParlimen, 'Parlimen');
            $this->assertBilanganCalon($calonDun, 'DUN');
        } else {
            // Skop "Parlimen sahaja" pada borang DUN: pasukan ini melaporkan
            // bahagian PRU sahaja. Borang DUN kekal tanpa calonnya sendiri.
            //
            // Baris bertanda 'dun' DITOLAK dan bukan diserap: menyerapnya akan
            // meletakkan calon DUN ke dalam takrifan Parlimen yang DIKONGSI
            // setiap DUN di bawah Parlimen ini — salah tanda yang senyap pada
            // objek yang paling luas kesannya dalam sistem ini.
            foreach ($parties as $p) {
                if (! empty($p['kontes']) && $p['kontes'] !== Borang14Vote::CONTEST_PARLIMEN) {
                    throw ValidationException::withMessages([
                        'parties' => 'Skop borang ialah Parlimen sahaja, tetapi ada calon yang ditanda '.
                            'pertandingan DUN. Buang tanda itu, atau tukar skop kepada kedua-duanya (serentak).',
                    ]);
                }
            }

            $calonParlimen = $this->nomborSemula($parties, null);
            $calonDun = [];

            $this->assertBilanganCalon($calonParlimen, 'Parlimen');
        }

        $indukId = (int) Kadun::whereKey($validated['kawasan_id'])->value('bandar_id');
        if ($indukId <= 0) {
            throw ValidationException::withMessages([
                'skop' => 'DUN ini tiada Parlimen induk dalam data kawasan, jadi pertandingan Parlimen tidak '.
                    'dapat dipautkan. Betulkan Parlimen induk DUN ini dalam Data Induk terlebih dahulu.',
            ]);
        }

        // Lubang yang sama seperti togol serentak pada panel Struktur: borang
        // Parlimen yang SUDAH berstruktur dibaca TERUS oleh roll-up, jadi undi
        // PRU pada borang DUN tidak akan dikira langsung.
        $this->assertTakrifanParlimenMasihKosong(['parlimen_id' => $indukId, 'tahun' => $validated['tahun']]);

        // Tolak — jangan petakan semula. Menomborkan semula slot pada baris undi
        // sebenar boleh menukar undi calon A menjadi undi calon B secara senyap.
        if ($skop !== $skopSemasa || $this->penetapanBerubah($form, $calonParlimen, $calonDun)) {
            $this->assertTiadaUndi($form);
        }

        // Borang TAKRIFAN calon PRU DIKONGSI oleh setiap DUN dalam Parlimen ini,
        // jadi undi yang menguncinya boleh berada pada kerusi LAIN sepenuhnya —
        // assertTiadaUndi() di atas hanya melihat borang yang dihantar.
        $takrifanSedia = Borang14Form::forKawasan(Borang14Form::KAWASAN_PARLIMEN, $indukId)
            ->where('jenis_pr', 'pru')->where('tahun', $validated['tahun'])->first();

        if ($takrifanSedia && $this->takrifanBerubah($takrifanSedia, $calonParlimen)) {
            // Sekatan "diterbitkan" mesti mengikut borang yang DITULIS, bukan
            // borang yang dihantar: borang DUN ini boleh draf sementara
            // takrifan yang dikongsi itu sudah diterbitkan.
            if ($takrifanSedia->status === 'published') {
                throw ValidationException::withMessages([
                    'skop' => 'Senarai calon PRU bagi Parlimen ini disimpan pada borang TAKRIFAN yang sudah '.
                        'DITERBITKAN, jadi nama calonnya sudah dilihat orang ramai dan dikongsi setiap DUN di '.
                        'bawah Parlimen ini. Tarik balik penerbitan borang Parlimen itu sebelum menukar '.
                        'senarai calonnya.',
                ]);
            }

            $this->assertTiadaUndiPruDikongsi($takrifanSedia);
        }

        [$form, $definisi] = DB::transaction(function () use ($validated, $skop, $indukId, $form, $calonParlimen, $calonDun) {
            // Borang TAKRIFAN calon PRU yang dikongsi semua DUN dalam Parlimen
            // ini, dikunci pada TAHUN borang DUN ini sendiri — pautan rentas
            // tahun akan menjumlahkan undi tahun lain ke dalam keputusan.
            //
            // SENGAJA tanpa 'structure': kekosongan struktur pada borang
            // takrifan ialah SATU-SATUNYA isyarat yang menyuruh Borang14RollUp
            // mengumpul borang DUN dan bukan membaca terus dari sini.
            $definisi = Borang14Form::updateOrCreate(
                [
                    'kawasan_type' => Borang14Form::KAWASAN_PARLIMEN,
                    'kawasan_id'   => $indukId,
                    'jenis_pr'     => 'pru',
                    'tahun'        => $validated['tahun'],
                ],
                ['penjuru' => count($calonParlimen), 'parties' => $calonParlimen],
            );

            $form = Borang14Form::updateOrCreate(
                [
                    'kawasan_type' => $validated['kawasan_type'],
                    'kawasan_id'   => $validated['kawasan_id'],
                    'jenis_pr'     => $validated['jenis_pr'],
                    'tahun'        => $validated['tahun'],
                ],
                [
                    'parties' => $calonDun,
                    'borang14_form_parlimen_id' => $definisi->id,
                    // penjuru borang DUN hanya bermakna apabila ia benar-benar
                    // merekod pertandingan DUN; dalam skop Parlimen-sahaja ia
                    // dikekalkan seperti sedia ada (2 pada borang baharu), bukan
                    // dipaksa kepada 0 atau kepada kiraan calon PRU.
                    'penjuru' => $skop === self::SKOP_KEDUA ? count($calonDun) : ($form?->penjuru ?? 2),
                ],
            );

            return [$form, $definisi];
        });

        return response()->json(['ok' => true, 'form_id' => $form->id, 'form_parlimen_id' => $definisi->id]);
    }

    /**
     * Skop yang sedang DISIMPAN pada borang ini, dibaca semula daripada cara
     * senarai calon dipisahkan — bukan daripada mana-mana lajur "skop".
     *
     * Pautan SEMATA-MATA (togol serentak pada panel Struktur, calon PRU belum
     * dinamakan) BUKAN skop dua pertandingan: takrifan yang masih kosong tidak
     * menomborkan apa-apa slot, jadi menyimpan calon DUN seperti biasa di
     * atasnya tidak boleh memindahkan undi sesiapa.
     */
    private function skopSemasa(?Borang14Form $form, string $sendiri): string
    {
        if (! $form || $form->borang14_form_parlimen_id === null) {
            return $sendiri;
        }

        $definisi = $form->formParlimen;
        if (! $definisi || empty($definisi->parties)) {
            return $sendiri;
        }

        return empty($form->parties) ? Borang14Form::KAWASAN_PARLIMEN : self::SKOP_KEDUA;
    }

    /**
     * Adakah penetapan calon kepada pertandingan berubah berbanding apa yang
     * tersimpan? Dibandingkan pada kedua-dua borang sekaligus — memindahkan
     * satu calon merentas pertandingan boleh mengekalkan BILANGAN setiap
     * pertandingan tetapi tetap menukar maksud setiap nombor slot.
     */
    private function penetapanBerubah(?Borang14Form $form, array $calonParlimen, array $calonDun): bool
    {
        if (! $form) {
            return true;
        }

        $definisi = $form->borang14_form_parlimen_id !== null ? $form->formParlimen : null;

        return $this->sidikCalon($definisi?->parties ?? []) !== $this->sidikCalon($calonParlimen)
            || $this->sidikCalon($form->parties ?? []) !== $this->sidikCalon($calonDun);
    }

    /** Cap jari senarai calon untuk perbandingan sahaja — bukan untuk disimpan. */
    private function sidikCalon(array $parties): array
    {
        return array_map(fn ($p) => [
            'slot' => isset($p['slot']) ? (int) $p['slot'] : null,
            'keahlian_parti_id' => isset($p['keahlian_parti_id']) && $p['keahlian_parti_id'] !== null
                ? (int) $p['keahlian_parti_id']
                : null,
            'nama' => trim((string) ($p['nama'] ?? '')),
            'calon' => trim((string) ($p['calon'] ?? '')),
        ], array_values($parties));
    }

    /**
     * Ambil calon bagi SATU pertandingan daripada senarai rata dan nomborkan
     * semula 1..n. Tanda `kontes` DIBUANG daripada apa yang disimpan: selepas
     * pemisahan, borang mana calon itu duduk sudah menyatakan segalanya, dan
     * dua salinan penetapan yang sama pasti menyimpang cepat atau lambat.
     */
    private function nomborSemula(array $parties, ?string $kontes): array
    {
        $dipilih = $kontes === null
            ? $parties
            : array_filter($parties, fn ($p) => ($p['kontes'] ?? null) === $kontes);

        $slot = 0;

        return array_map(function ($p) use (&$slot) {
            unset($p['kontes']);
            $p['slot'] = ++$slot;

            return $p;
        }, array_values($dipilih));
    }

    /**
     * Satu pertandingan mesti ada 2..6 calon. Satu calon bukan pertandingan,
     * dan lebih daripada 6 melebihi had lajur `penjuru` sedia ada.
     */
    private function assertBilanganCalon(array $calon, string $namaKontes): void
    {
        $bilangan = count($calon);

        if ($bilangan < 2) {
            throw ValidationException::withMessages([
                'parties' => "Pertandingan {$namaKontes} hanya mempunyai {$bilangan} calon. Setiap pertandingan ".
                    'mesti mempunyai sekurang-kurangnya 2 calon.',
            ]);
        }

        if ($bilangan > 6) {
            throw ValidationException::withMessages([
                'parties' => "Pertandingan {$namaKontes} mempunyai {$bilangan} calon. Had bagi satu pertandingan ".
                    'ialah 6 calon.',
            ]);
        }
    }

    /**
     * Adakah tulisan ini benar-benar mengubah borang takrifan Parlimen?
     * Menyimpan semula senarai yang SAMA tidak menomborkan semula apa-apa slot,
     * jadi ia tidak boleh memindahkan undi sesiapa dan tidak perlu dihalang.
     */
    private function takrifanBerubah(Borang14Form $takrifan, array $calonParlimen): bool
    {
        return (int) $takrifan->penjuru !== count($calonParlimen)
            || $this->sidikCalon($takrifan->parties ?? []) !== $this->sidikCalon($calonParlimen);
    }

    /**
     * Undi PRU yang mengunci borang TAKRIFAN — di MANA-MANA kerusi.
     *
     * Takrifan Parlimen ialah objek yang DIKONGSI: Borang14RollUp membaca
     * identiti calon daripadanya lalu menjumlahkan undi contest='parlimen'
     * SETIAP borang DUN yang memautinya, mengikut nombor slot. Oleh itu
     * menomborkan semula senarai calon di sini boleh menerbitkan undi DUN LAIN
     * di bawah nama yang salah — walaupun borang yang sedang disunting itu
     * sendiri kosong tanpa satu undi pun. Semak keluarga penuh, bukan satu
     * borang, dan namakan kerusi yang sudah berundi supaya pengendali tahu ke
     * mana hendak pergi.
     */
    private function assertTiadaUndiPruDikongsi(Borang14Form $takrifan): void
    {
        $namaKerusi = null;

        if ($takrifan->votesFor(Borang14Vote::CONTEST_PARLIMEN)->exists()) {
            $namaKerusi = $takrifan->kawasanNama();
        } else {
            $idDun = $takrifan->borangDun()->pluck('id');
            $idBerundi = $idDun->isEmpty() ? null : Borang14Vote::query()
                ->where('contest', Borang14Vote::CONTEST_PARLIMEN)
                ->whereIn('borang14_form_id', $idDun)
                ->value('borang14_form_id');

            if ($idBerundi !== null) {
                $namaKerusi = Borang14Form::find($idBerundi)?->kawasanNama() ?? '—';
            }
        }

        if ($namaKerusi === null) {
            return;
        }

        throw ValidationException::withMessages([
            'skop' => "Pertandingan Parlimen ini sudah mempunyai undi yang dikunci masuk pada borang {$namaKerusi}. ".
                'Senarai calon PRU disimpan pada satu borang TAKRIFAN yang dikongsi setiap DUN di bawah Parlimen '.
                'ini, jadi menukarnya akan menomborkan semula slot yang undi tersebut sudah pun guna, dan undi itu '.
                'akan terpakai pada calon yang salah. Kosongkan undi Parlimen pada borang berkenaan terlebih dahulu.',
        ]);
    }

    /**
     * Undi sedia ada MENGUNCI penetapan pertandingan. Menomborkan semula slot
     * calon pada borang yang sudah ada undi bermakna undi calon A boleh menjadi
     * undi calon B — kegagalan paling teruk dalam sistem ini, dan sepenuhnya
     * senyap. Kedua-dua pertandingan disemak, bukan satu.
     */
    private function assertTiadaUndi(?Borang14Form $form): void
    {
        if (! $form) {
            return;
        }

        $adaUndi = $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)->exists()
            || $form->votesFor(Borang14Vote::CONTEST_DUN)->exists();

        if (! $adaUndi) {
            return;
        }

        throw ValidationException::withMessages([
            'skop' => 'Borang ini sudah mempunyai undi yang dikunci masuk. Menukar skop pertandingan atau '.
                'penetapan calon akan menomborkan semula slot calon, lalu undi yang sudah direkod akan '.
                'terpakai pada calon yang salah. Kosongkan undi kedua-dua pertandingan terlebih dahulu '.
                'jika penetapan ini benar-benar perlu diubah.',
        ]);
    }

    /** Upsert a single editable cell (auto-save on blur). */
    public function saveVote(Request $request)
    {
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate([
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
            'contest'  => ['required', Rule::in([Borang14Vote::CONTEST_DUN, Borang14Vote::CONTEST_PARLIMEN])],
            'pusat'    => 'nullable|string|max:255',
            'saluran'  => 'required|string|max:50',
            'slot'     => 'required|integer|in:1,2,3,4,5,6,90,91',   // 90 = ditolak (C), 91 = tidak dimasukkan (D)
            'undi'     => 'nullable|integer|min:0|max:1000000',
        ]);

        $form = Borang14Form::firstOrCreate(
            [
                'kawasan_type' => $validated['kawasan_type'],
                'kawasan_id'   => $validated['kawasan_id'],
                'jenis_pr'     => $validated['jenis_pr'],
                'tahun'        => $validated['tahun'],
            ],
            ['penjuru' => $validated['penjuru'], 'parties' => []],
        );

        // Undi TIDAK boleh dikunci pada borang TAKRIFAN Parlimen: undi PRU
        // hidup pada borang DUN, dan roll-up mengumpulnya dari sana. Apa yang
        // ditaip di sini akan disimpan (hijau, kelihatan berjaya) tetapi TIDAK
        // dikira oleh sesiapa — angka hantu pada malam mengira undi. Lihat
        // assertBukanTakrifanParlimen() untuk lubang yang sama pada struktur.
        $bilanganDun = $form->borangDun()->count();
        if ($bilanganDun > 0) {
            return response()->json([
                'message' => "Borang ini ialah TAKRIFAN calon PRU yang dikongsi oleh {$bilanganDun} borang DUN. ".
                    'Undi PRU dikunci pada borang DUN masing-masing (jalur PRU pada skrin DUN), bukan di '.
                    'sini — angka yang ditaip pada borang takrifan tidak akan dikira dalam keputusan Parlimen.',
            ], 422);
        }

        Borang14Vote::updateOrCreate(
            [
                'borang14_form_id' => $form->id,
                'contest' => $validated['contest'],
                'pusat'   => $validated['pusat'] ?? '',
                'saluran' => $validated['saluran'],
                'slot'    => $validated['slot'],
            ],
            ['undi' => $validated['undi'] ?? 0],
        );

        return response()->json(['ok' => true]);
    }

    /** Temporary: clear all entered vote figures for a (kawasan, jenis PR, tahun) scenario. */
    public function reset(Request $request)
    {
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate([
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            // WAJIB — bukan dilalaikan daripada kawasan_type. Pada borang serentak
            // ini bertindak atas apa yang pengguna SEDANG sunting; pemanggil yang
            // lupa mesti gagal (422), bukan senyap memadam pertandingan yang salah.
            'contest'  => ['required', Rule::in([Borang14Vote::CONTEST_DUN, Borang14Vote::CONTEST_PARLIMEN])],
        ]);

        $form = Borang14Form::forKawasan($validated['kawasan_type'], $validated['kawasan_id'])
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        $form?->votesFor($validated['contest'])->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Peraturan pengesahan yang dikongsi oleh simpanStruktur() dan
     * kesanStruktur(). Kedua-duanya MESTI menerima bentuk input yang sama —
     * kalau ia hanyut, pratonton akan melaporkan kesan bagi sesuatu yang
     * berbeza daripada apa yang benar-benar disimpan.
     */
    private function strukturRules(string $existsTable): array
    {
        return [
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'pusat'    => 'present|array|max:500',
            'pusat.*.row_id' => 'required|string|max:64',
            // WAJIB, bukan nullable. Borang14ScenarioMapper::map() MELANGKAU
            // setiap undi yang Pusatnya tiada Daerah Mengundi ("jangan reka
            // DM"). Bagi kerusi manual yang tiada rujukan lain untuk memetakan
            // DM, satu dm kosong bermakna Analisa/AI menerbitkan "keputusan
            // kerusi" yang sebenarnya baris undi pos semata-mata.
            'pusat.*.dm'     => 'required|string|max:255',
            'pusat.*.pusat'  => 'required|string|max:255',
            'pusat.*.saluran_count' => 'required|integer|min:1|max:20',
            // Label saluran/undi pos MENTAH yang dibawa balik oleh collapse().
            // Undi dikunci padanya, jadi ia mesti pulang ke expand() tanpa
            // diubah — lihat docblock expand().
            'pusat.*.saluran_labels'   => 'nullable|array|max:20',
            'pusat.*.saluran_labels.*' => 'nullable|string|max:255',
            'undi_awal' => 'boolean',
            'undi_pos'  => 'boolean',
            'undi_awal_label' => 'nullable|string|max:255',
            'undi_pos_label'  => 'nullable|string|max:255',
            // Baris pusat-kosong yang panel tidak boleh wakili, dibawa balik
            // mentah supaya undi di bawahnya tidak menjadi yatim.
            'lain_lain' => 'nullable|array|max:50',
            'lain_lain.*.row_id'  => 'nullable|string|max:64',
            'lain_lain.*.dm'      => 'nullable|string|max:255',
            'lain_lain.*.saluran' => 'nullable|string|max:255',
            // Togol mod pilihanraya serentak (PRU + PRN). integer = paut/tukar
            // pautan kepada borang takrifan Parlimen tersebut; null (dihantar
            // secara eksplisit) = nyahpaut; medan tidak dihantar langsung =
            // jangan sentuh pautan sedia ada.
            'parlimen_id' => ['nullable', 'integer', Rule::exists('bandar', 'id')],
        ];
    }

    /** Mesej pengesahan dalam Bahasa Melayu — teks lalai Laravel dalam Bahasa Inggeris. */
    private function strukturMessages(): array
    {
        return [
            'pusat.*.pusat.required' => 'Nama Pusat Mengundi wajib diisi.',
            'pusat.*.dm.required' => 'Daerah Mengundi wajib diisi bagi setiap Pusat Mengundi — analisa melangkau undi yang tiada Daerah Mengundi.',
            'pusat.*.saluran_count.required' => 'Bilangan saluran wajib diisi.',
            'pusat.*.saluran_count.min' => 'Setiap Pusat Mengundi mesti ada sekurang-kurangnya 1 saluran.',
            'pusat.*.saluran_count.max' => 'Bilangan saluran melebihi had 20.',
            'pusat.max' => 'Terlalu banyak Pusat Mengundi (had 500).',
        ];
    }

    /**
     * Simpan struktur (Pusat Mengundi + bilangan saluran) yang dibina dengan tangan.
     *
     * Ini satu-satunya jalan mencipta borang bagi kerusi yang tiada DPT dan
     * tiada scoresheet — firstOrCreate di sini yang memecahkan kebuntuan
     * "Data Borang 14 belum tersedia".
     *
     * Undi dikunci pada `pusat|saluran|slot`, jadi menukar struktur mesti
     * menggerakkan undi bersamanya. Urutan dalam transaksi PENTING:
     * snapshot → namakan semula → padam yatim → simpan struktur. Menamakan
     * semula dahulu bermakna langkah padam boleh menilai satu perkara sahaja:
     * "adakah kunci ini masih wujud dalam struktur baharu?"
     */
    public function simpanStruktur(Request $request, Borang14StrukturService $svc)
    {
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate($this->strukturRules($existsTable), $this->strukturMessages());

        // SATU tempat sahaja untuk memangkas nama Pusat/DM — nilai yang
        // dipangkas di sinilah yang disahkan, dibanding, dinamakan semula
        // DAN disimpan. Laluan tulis undi (normalizeSaluran()) memangkas
        // paksi Saluran; ini memangkas paksi Pusat supaya kedua-dua paksi
        // kunci sel diselaraskan — tanpa ini "SK TENGKEK " (ruang
        // berikutan) menjadi kunci yang berbeza daripada "SK TENGKEK".
        $validated['pusat'] = collect($validated['pusat'])->map(function ($p) {
            $p['pusat'] = trim($p['pusat']);
            if (array_key_exists('dm', $p) && $p['dm'] !== null) {
                $p['dm'] = trim($p['dm']);
            }

            return $p;
        })->all();

        // Mod serentak hanya sah bagi borang DUN — Parlimen ITU SENDIRI
        // ialah takrifan, ia tidak boleh dipaut kepada dirinya sendiri.
        // Disemak di sini (bukan sekadar dipercayai daripada UI) supaya
        // panggilan API langsung tidak boleh memaut borang Parlimen.
        if (array_key_exists('parlimen_id', $validated) && $validated['kawasan_type'] !== Borang14Form::KAWASAN_DUN) {
            throw ValidationException::withMessages([
                'parlimen_id' => 'Pautan pilihanraya serentak hanya sah bagi borang DUN.',
            ]);
        }

        // DUN tidak boleh dipaut kepada Parlimen sesiapa — hanya Parlimen
        // induknya sendiri. Disahkan di server supaya nilai select yang
        // dimanipulasi pada klien (atau panggilan API terus) tidak boleh
        // memaut kerusi kepada pertandingan Parlimen yang bukan miliknya.
        if (! empty($validated['parlimen_id'])) {
            $indukId = Kadun::whereKey($validated['kawasan_id'])->value('bandar_id');
            if ((int) $validated['parlimen_id'] !== (int) $indukId) {
                throw ValidationException::withMessages([
                    'parlimen_id' => 'Parlimen yang dipilih bukan Parlimen induk bagi DUN ini.',
                ]);
            }
        }

        $form = Borang14Form::forKawasan($validated['kawasan_type'], $validated['kawasan_id'])
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        // Kebenaran MESTI disemak dahulu — sebelum sebarang panduan
        // pendua/perlanggaran nama di bawah. Jika tidak, penyahutan (422)
        // membocorkan kandungan borang (nama Pusat mana yang sudah wujud)
        // kepada pemanggil yang langsung tiada kebenaran menyentuh borang
        // ini, dan borang yang DITERBITKAN melaporkan sebab penolakan yang
        // salah (isi kandungan tidak sah, bukan disekat).
        if (! $this->bolehSuntingStruktur($request->user(), $form, $validated)) {
            abort(403, 'Unauthorized action.');
        }

        // Selepas kebenaran (jangan bocorkan kewujudan borang), sebelum
        // firstOrCreate — kerusi kurasi/DPT/warisan tidak boleh disunting
        // langsung, dan tiada borang patut tercipta akibat cubaan itu.
        $this->assertStrukturBolehDisunting($validated, $form);
        $this->assertBukanTakrifanParlimen($form);
        $this->assertTakrifanParlimenMasihKosong($validated);

        $asas = $this->strukturAsas($svc, $form, $validated);

        $this->assertPusatNamesUsable($svc, $form, $validated['pusat'], $asas);

        $baharu = $svc->expand(
            $validated['pusat'],
            (bool) ($validated['undi_awal'] ?? false),
            (bool) ($validated['undi_pos'] ?? false),
            $validated['undi_awal_label'] ?? null,
            $validated['undi_pos_label'] ?? null,
            $validated['lain_lain'] ?? [],
        );

        DB::transaction(function () use (&$form, $validated, $baharu, $asas, $svc, $request) {
            $form ??= Borang14Form::create([
                'kawasan_type' => $validated['kawasan_type'],
                'kawasan_id'   => $validated['kawasan_id'],
                'jenis_pr'     => $validated['jenis_pr'],
                'tahun'        => $validated['tahun'],
                'penjuru'      => 2,
                'parties'      => [],
                'status'       => 'draft',
                'source'       => 'manual',
            ]);

            if ($form->wasRecentlyCreated === false) {
                Borang14Snapshot::create([
                    'borang14_form_id' => $form->id,
                    'structure' => $form->structure,
                    'votes' => $form->votes()->get(['contest', 'pusat', 'saluran', 'slot', 'undi'])->toArray(),
                    'parties' => $form->parties,
                    'reason' => 'before_structure_edit',
                    'created_by' => $request->user()?->id,
                ]);
            }

            foreach ($svc->renameMap($asas, $validated['pusat']) as $lama => $kini) {
                $form->votes()->where('pusat', $lama)->update(['pusat' => $kini]);
            }

            // SENGAJA merentas kedua-dua pertandingan (di sini dan pada
            // renameMap() di atas): struktur ialah pokok Pusat Mengundi/saluran
            // FIZIKAL, dan satu saluran menghasilkan undi PRU DAN PRN. Membuang
            // satu saluran mesti membuang undi kedua-dua pertandingan padanya —
            // jika tidak, baris PRU yatim kekal di bawah kunci sel yang tiada
            // siapa lagi membaca, dan menamakan semula pusat akan memisahkan
            // dua pertandingan yang datang dari saluran yang SAMA.
            $kekal = $svc->survivingKeys($baharu);
            foreach ($form->votes()->get(['id', 'pusat', 'saluran']) as $v) {
                if (! isset($kekal[$v->pusat.'|'.$v->saluran])) {
                    Borang14Vote::whereKey($v->id)->delete();
                }
            }

            $form->update(['structure' => $baharu]);

            // Togol mod pilihanraya serentak. Kunci dikira SEMULA setiap
            // panggilan (bukan sekadar ditambah) supaya nyahtogol benar-benar
            // menyahpaut — lihat blok if/else di bawah untuk sebab undi
            // kontes 'parlimen' sedia ada TIDAK dipadam apabila dinyahpaut.
            if (array_key_exists('parlimen_id', $validated)) {
                if ($validated['parlimen_id'] !== null) {
                    // Borang Parlimen itu SENDIRI ialah takrifan calon yang
                    // dikongsi semua DUN dalam Parlimen itu. Dikunci pada
                    // TAHUN borang DUN ini sendiri (bukan tahun global/lalai)
                    // — jika tidak, borang DUN tahun X boleh terpaut kepada
                    // takrifan Parlimen tahun Y, lalu keputusan tahun Y itu
                    // turut menjumlahkan undi DUN tahun X. UNIQUE(kawasan_type,
                    // kawasan_id, jenis_pr, tahun) menjamin firstOrCreate
                    // memulangkan tepat SATU takrifan bagi (Parlimen, tahun).
                    $definisi = Borang14Form::firstOrCreate(
                        [
                            'kawasan_type' => Borang14Form::KAWASAN_PARLIMEN,
                            'kawasan_id' => $validated['parlimen_id'],
                            'jenis_pr' => 'pru',
                            'tahun' => $validated['tahun'],
                        ],
                        ['penjuru' => 2, 'parties' => []],
                    );
                    $form->borang14_form_parlimen_id = $definisi->id;
                } else {
                    // Nyahtogol: nyahpaut sahaja. Undi kontes 'parlimen' yang
                    // sudah dikunci masuk pada borang DUN ini KEKAL dalam
                    // borang14_votes (ia tersimpan pada baris DUN ini
                    // sendiri, bukan pada borang takrifan) — hanya menjadi
                    // yatim/tersembunyi daripada skrin dua jalur, dan
                    // muncul semula tanpa kehilangan apa-apa jika dipaut
                    // semula. Memadamnya di sini akan memusnahkan undi PRU
                    // yang sah kerana togol UI ditutup secara tidak sengaja.
                    $form->borang14_form_parlimen_id = null;
                }
                $form->save();
            }
        });

        return response()->json(['ok' => true, 'form_id' => $form->id]);
    }

    /**
     * Pengesahan nama Pusat Mengundi yang dikongsi oleh simpanStruktur() DAN
     * kesanStruktur() — kedua-duanya MESTI bersetuju sama ada muatan ini
     * diterima, bukan sekadar apa kesannya. Melempar ValidationException
     * (422) apabila ditolak; tidak memulangkan apa-apa apabila diterima.
     *
     * Kunci nama sasaran (rename TARGET) terhadap nama SEDIA ADA — bukan
     * sahaja senarai baharu, dan bukan sahaja struktur lama.
     *
     * Sumber "sudah digunakan" ialah GABUNGAN DUA set, kerana indeks unik
     * sebenar (borang14_votes) tidak sama dengan struktur:
     *   (a) nama dalam struktur lama ($svc->collapse()); DAN
     *   (b) nama pusat yang wujud dalam borang14_votes itu sendiri —
     *       saveVote() menulis baris undi bagi MANA-MANA rentetan pusat
     *       tanpa menyemaknya terhadap struktur, jadi baris "yatim"
     *       (wujud dalam undi, tiada dalam struktur) tetap boleh
     *       bertembung dengan nama yang baharu dinamakan semula.
     *
     * Perbandingan dinormalisasi (nameKey(): huruf besar + pangkas) supaya
     * ia sepadan dengan collation MySQL utf8mb4_unicode_ci yang menguasai
     * indeks unik borang14_votes — pangkalan data tidak sensitif huruf
     * besar/kecil, jadi guard ini pun tidak boleh. NILAI YANG DISIMPAN
     * tidak diubah — hanya kunci perbandingan.
     *
     * Tanpa semakan ini, menamakan semula satu pusat ke atas nama pusat LAIN
     * yang turut dibuang dalam simpanan yang sama (atau menukar ganti dua
     * nama) lolos semakan pendua di atas (senarai baharu sendiri tiada
     * pendua) tetapi renameMap() kemudian menulis UPDATE ke atas kunci undi
     * yang masih dimiliki nama lama itu: sama ada undi salah pusat kekal
     * senyap (tiada perlanggaran kunci penuh), atau UPDATE bertembung
     * dengan indeks unik dan melempar 500 tanpa mesej Bahasa Melayu.
     *
     * Pusat yang namanya (dinormalisasi) TIDAK berubah sudah tentu
     * "berlanggar" dengan dirinya sendiri — nama yang dimiliki OLEH row ini
     * sendiri dalam struktur lama dikecualikan daripada set "sudah
     * digunakan" bagi row itu sahaja.
     *
     * @param  array<int,array{row_id:string,dm:?string,pusat:string,saluran_count:int}>  $pusat
     */
    private function assertPusatNamesUsable(Borang14StrukturService $svc, ?Borang14Form $form, array $pusat, ?array $asas = null): void
    {
        $namaKeyList = collect($pusat)->map(fn ($p) => $this->nameKey($p['pusat']));
        if ($namaKeyList->count() !== $namaKeyList->unique()->count()) {
            // Dua pusat senama (atau senama tidak-sensitif-huruf) berkongsi
            // kunci undi yang sama dan akan menulis atas satu sama lain
            // tanpa sebarang amaran.
            // Namakan pendua itu. Panel yang disemai daripada anggaran DPT
            // boleh membawa beberapa baris 'TIADA LOKALITI' (satu bagi setiap
            // Daerah Mengundi yang ada baris tanpa lokaliti), dan mesej yang
            // tidak menyebut nama meninggalkan pengguna mengamati 12 baris
            // yang kelihatan serupa.
            // Papar nama SEPERTI DITAIP, bukan bentuk nameKey() huruf besar
            // yang hanya digunakan untuk membanding.
            $kunciPendua = $namaKeyList->duplicates()->unique()->values();
            $pendua = collect($pusat)
                ->filter(fn ($p) => $kunciPendua->contains($this->nameKey($p['pusat'])))
                ->pluck('pusat')->unique()->values()->implode(', ');

            throw ValidationException::withMessages([
                'pusat' => "Nama Pusat Mengundi mesti unik dalam satu borang. Nama berulang: {$pendua}. Namakan semula atau buang baris yang berlebihan.",
            ]);
        }

        if (! $form) {
            return;
        }

        $lama = $svc->collapse($asas ?? $form->structure)['pusat'];

        // TIADA struktur lama = TIADA garis dasar penamaan semula:
        // renameMap() memulangkan [] dan tiada satu pun UPDATE dijalankan,
        // jadi perlanggaran indeks unik yang guard ini wujud untuk menghalang
        // tidak mungkin berlaku. Tanpa pintasan ini, panel yang DISEMAI
        // daripada rujukan (asal 'dpt'/'warisan', di mana structure memang
        // null menurut definisi) tidak akan pernah boleh disimpan sebaik ada
        // satu undi pun: setiap nama yang disemai sudah wujud dalam
        // borang14_votes tetapi tiada dalam struktur, jadi ia dikira
        // berlanggar dengan DIRINYA SENDIRI.
        if (empty($lama)) {
            return;
        }

        $namaLama = collect($lama)->pluck('pusat');
        // SENGAJA merentas kedua-dua pertandingan: ini bukan kiraan undi, ia
        // set nama yang MENDUDUKI indeks unik borang14_votes. Satu baris PRU
        // dengan nama pusat itu berlanggar sama kuatnya dengan baris PRN.
        $namaUndi = $form->votes()->distinct()->pluck('pusat');
        $digunakan = $namaLama->merge($namaUndi)->filter(fn ($n) => $n !== '');

        foreach ($pusat as $p) {
            $rowId = (string) $p['row_id'];
            $baharuNama = $p['pusat'];
            $baharuKey = $this->nameKey($baharuNama);

            $namaSendiri = collect($lama)->firstWhere('row_id', $rowId)['pusat'] ?? null;
            $kunciSendiri = $namaSendiri !== null ? $this->nameKey($namaSendiri) : null;

            $berlanggar = $digunakan->contains(fn ($n) => $this->nameKey($n) === $baharuKey && $this->nameKey($n) !== $kunciSendiri);

            if ($berlanggar) {
                throw ValidationException::withMessages([
                    'pusat' => "Nama Pusat Mengundi '{$baharuNama}' sudah digunakan oleh pusat lain dalam borang ini. Namakan semula atau buang pusat itu dahulu, kemudian simpan sekali lagi.",
                ]);
            }
        }
    }

    /**
     * Pratonton tanpa menulis: berapa baris undi dan berapa jumlah undi yang
     * akan DIPADAM oleh cadangan struktur ini.
     *
     * Wujud supaya dialog pengesahan memaparkan angka sebenar. Amaran kabur
     * ("perubahan ini mungkin menjejaskan data") dibaca sebagai bunyi latar
     * dan diklik terus — angka tidak.
     *
     * Mesti bersetuju dengan simpanStruktur() dalam DUA perkara, bukan
     * sekadar satu:
     *   1) kunci undi dinilai SELEPAS penamaan semula (rename), supaya
     *      pertukaran nama tulen melaporkan sifar; dan
     *   2) muatan yang simpanStruktur() akan TOLAK (pendua nama, atau nama
     *      yang berlanggar dengan struktur lama/undi yatim) juga ditolak di
     *      sini dengan 422 yang sama — bukan dipratonton seolah-olah sah.
     *      Tanpa ini dialog memaparkan angka yakin bagi muatan yang akan
     *      gagal sebaik sahaja pengguna menekan Simpan.
     *
     * Tidak menulis apa-apa: tiada transaksi, tiada Borang14Form::create(),
     * tiada Borang14Snapshot::create(), tiada UPDATE/DELETE ke atas
     * borang14_votes. Pratonton yang mencipta borang bukan pratonton.
     */
    public function kesanStruktur(Request $request, Borang14StrukturService $svc)
    {
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate($this->strukturRules($existsTable), $this->strukturMessages());

        // Sama seperti simpanStruktur() — SATU tempat sahaja untuk memangkas
        // nama Pusat/DM, supaya nilai yang disahkan/dibanding/dinamakan
        // semula sepadan dengan nilai yang benar-benar akan disimpan.
        $validated['pusat'] = collect($validated['pusat'])->map(function ($p) {
            $p['pusat'] = trim($p['pusat']);
            if (array_key_exists('dm', $p) && $p['dm'] !== null) {
                $p['dm'] = trim($p['dm']);
            }

            return $p;
        })->all();

        $form = Borang14Form::forKawasan($validated['kawasan_type'], $validated['kawasan_id'])
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        // Kebenaran MESTI disemak dahulu — sebelum guard pendua/perlanggaran
        // di bawah — atas sebab yang sama seperti simpanStruktur(): jika
        // tidak, penyahutan (422) membocorkan kandungan borang kepada
        // pemanggil yang langsung tiada kebenaran menyentuh borang ini.
        if (! $this->bolehSuntingStruktur($request->user(), $form, $validated)) {
            abort(403, 'Unauthorized action.');
        }

        // Sama seperti simpanStruktur(): pratonton mesti MENOLAK apa yang
        // simpanan akan tolak, bukan memaparkan angka bagi muatan yang mati.
        $this->assertStrukturBolehDisunting($validated, $form);

        $asas = $this->strukturAsas($svc, $form, $validated);

        $this->assertPusatNamesUsable($svc, $form, $validated['pusat'], $asas);

        if (! $form) {
            // Belum pernah disimpan bagi kerusi ini — tiada undi wujud untuk
            // dipadam, jadi sifar adalah jawapan JUJUR, bukan ralat.
            return response()->json(['baris' => 0, 'undi' => 0, 'pusat' => []]);
        }

        // Sama seperti simpanStruktur(): nilai kunci SELEPAS penamaan semula,
        // supaya pusat yang sekadar bertukar nama tidak dilaporkan sebagai
        // kehilangan.
        $rename = $svc->renameMap($asas, $validated['pusat']);
        $kekal = $svc->survivingKeys($svc->expand(
            $validated['pusat'],
            (bool) ($validated['undi_awal'] ?? false),
            (bool) ($validated['undi_pos'] ?? false),
            $validated['undi_awal_label'] ?? null,
            $validated['undi_pos_label'] ?? null,
            $validated['lain_lain'] ?? [],
        ));

        // SENGAJA merentas kedua-dua pertandingan, kerana ia mesti memadankan
        // padaman yang dipratontonnya (simpanStruktur(), yang juga merentas
        // kedua-duanya). Ini amaran KEHILANGAN DATA, bukan angka pilihan raya:
        // menapisnya kepada satu pertandingan akan melaporkan kehilangan yang
        // LEBIH KECIL daripada yang sebenarnya berlaku.
        $hilang = $form->votes()->get(['pusat', 'saluran', 'undi'])
            ->filter(fn ($v) => ! isset($kekal[($rename[$v->pusat] ?? $v->pusat).'|'.$v->saluran]));

        return response()->json([
            'baris' => $hilang->count(),
            'undi'  => (int) $hilang->sum('undi'),
            'pusat' => $hilang->pluck('pusat')->unique()->values()->all(),
        ]);
    }

    /**
     * Menyunting struktur menggerakkan undi sebenar, jadi ia berkongsi tahap
     * kepercayaan yang sama seperti mengisi undi — admin ke atas — tetapi
     * borang DITERBITKAN disekat sepenuhnya, termasuk untuk super_admin:
     * bentuk rekod rasmi tidak boleh berubah di bawah angka yang sudah
     * disiarkan ke Scoreboard. Revert dahulu, kemudian sunting.
     */
    /**
     * Adakah grid yang dipaparkan benar-benar datang daripada struktur borang
     * INI (atau tiada grid langsung)?
     *
     * Panel Sunting Struktur disemai daripada $form->structure SAHAJA. Apabila
     * grid sebenarnya dibina daripada JSON kurasi, anggaran DPT, atau struktur
     * yang diwarisi daripada pilihan raya lain, borang ini sendiri mempunyai
     * structure = null — jadi panel dibuka KOSONG di atas grid yang penuh
     * undi. Menyimpan ketika itu menjadikan setiap undi sedia ada "yatim" dan
     * survivingKeys() memadamnya. Bagi kerusi kurasi ia lebih buruk lagi:
     * resolveReference() tidak pernah merujuk struktur borang untuk sumber
     * itu, jadi struktur yang baru ditaip tidak akan dipaparkan pun —
     * operasi itu kerugian semata-mata.
     *
     * HANYA 'kurasi' disekat, dan sebab yang tepat itu penting:
     *   - 'kurasi'   — JSON kurasi tiada kunci 'source', jadi resolveReference()
     *                  TIDAK PERNAH merujuk structure borang untuk sumber ini.
     *                  Struktur yang ditaip tidak akan dipaparkan pun, jadi
     *                  menyunting di sini kerugian semata-mata.
     *   - 'dpt'      — structure MENGALAHKAN anggaran DPT (lihat keutamaan di
     *                  atas), jadi suntingan benar-benar dipaparkan. Inilah
     *                  cara membaiki anggaran DPT yang menganggap 1 saluran
     *                  per Pusat — antara sebab ciri ini wujud.
     *   - 'warisan'  — sebaik borang ini ada structure sendiri, cabang warisan
     *                  tidak berjalan langsung. Suntingan dipaparkan.
     *   - 'struktur' / null — struktur borang ini sendiri, atau kebuntuan yang
     *                  ciri ini wujud untuk dipecahkan.
     *
     * Bahaya "panel dibuka kosong di atas grid penuh" bagi 'dpt'/'warisan'
     * ditutup di tempat lain, dengan MENYEMAI panel daripada rujukan yang
     * dipaparkan (strukturUntukPanel()), bukan dengan menyekat penyuntingan.
     */
    private function strukturBolehDisunting(?string $asal): bool
    {
        return $asal !== 'kurasi';
    }

    /**
     * Keadaan permulaan panel: struktur borang INI apabila ada, jika tidak
     * rujukan yang sedang DIPAPARKAN.
     *
     * Menyemai daripada $form->structure sahaja bermakna panel dibuka kosong
     * setiap kali grid datang daripada DPT atau warisan — dan menyimpan
     * ketika itu memadam setiap undi yang tidak ditaip semula.
     */
    private function strukturUntukPanel(Borang14StrukturService $svc, ?Borang14Form $form, ?array $reference, ?string $asal): array
    {
        if ($asal === 'struktur' || ! empty($form?->structure['rows'])) {
            return $svc->collapse($form?->structure);
        }

        return $asal === null
            ? $svc->collapse(null)
            : $svc->collapseReference($reference);
    }

    /**
     * Struktur yang menjadi GARIS DASAR penamaan semula.
     *
     * renameMap() memadankan pada row_id terhadap struktur lama. Bagi
     * suntingan PERTAMA panel yang disemai daripada rujukan (asal 'dpt' /
     * 'warisan'), borang ini belum ada struktur sendiri — menurut definisi
     * cabang itu — jadi tanpa ini garis dasarnya kosong, setiap pusat dibaca
     * sebagai baharu, dan menamakan semula dilaksanakan sebagai padam-lama +
     * tambah-baharu: undi di bawah nama lama dipadam sedangkan ia sepatutnya
     * BERPINDAH. Garis dasar itu sebenarnya wujud — grid yang disemai — dan
     * row_id-nya diterbitkan dengan peraturan yang sama, jadi ia memang
     * sepadan.
     *
     * Dipakai oleh simpanStruktur() DAN kesanStruktur(): pratonton mesti
     * mengira penamaan semula yang sama seperti simpanan, jika tidak dialog
     * melaporkan pemusnahan yang tidak akan berlaku (atau sebaliknya).
     */
    private function strukturAsas(Borang14StrukturService $svc, ?Borang14Form $form, array $validated): ?array
    {
        if (! empty($form?->structure['rows'])) {
            return $form->structure;
        }

        ['reference' => $reference, 'asal' => $asal] = $this->resolveReference(
            $validated['kawasan_type'], (int) $validated['kawasan_id'], $form,
        );

        if ($asal === null) {
            return null;
        }

        $semaian = $svc->collapseReference($reference);

        return $svc->expand(
            $semaian['pusat'],
            $semaian['undi_awal'],
            $semaian['undi_pos'],
            $semaian['undi_awal_label'],
            $semaian['undi_pos_label'],
        );
    }

    /** 422 supaya sebabnya boleh dibaca pengguna; guard ini bukan hal kebenaran. */
    private function assertStrukturBolehDisunting(array $validated, ?Borang14Form $form): void
    {
        $asal = $this->resolveReference($validated['kawasan_type'], (int) $validated['kawasan_id'], $form)['asal'];

        if ($this->strukturBolehDisunting($asal)) {
            return;
        }

        throw ValidationException::withMessages([
            'pusat' => 'Struktur kerusi ini datang daripada fail rujukan rasmi SPR yang sentiasa mengatasi struktur borang. Apa yang ditaip di sini tidak akan dipaparkan, tetapi undi sedia ada akan dipadam.',
        ]);
    }

    /**
     * Borang TAKRIFAN Parlimen tidak boleh diberi struktur — 422, bukan 403
     * (ini bukan hal kebenaran; pengguna memang berhak menyunting kerusi ini).
     *
     * `structure` yang KOSONG pada borang Parlimen ialah SATU-SATUNYA isyarat
     * yang memberitahu Borang14RollUp::forParlimen() supaya MENGUMPUL borang
     * DUN yang memautinya. Sebaik sahaja borang itu ada struktur sendiri,
     * roll-up bertukar kepada bacaan TERUS daripada borang takrifan — yang
     * tiada satu undi pun padanya — lalu papan Parlimen awam menerbitkan
     * 0-0-0, DAN dengan `liputan = null` (bacaan terus tiada konsep liputan
     * separa) jadi tiada pun badge SEMENTARA yang memberi amaran. Undi PRU
     * sebenar sementara itu duduk elok pada borang DUN, tidak dibaca sesiapa.
     *
     * Alur kerja MEMAKSA pengendali membuka skrin ini (di situlah nama calon
     * PRU dinamakan), jadi butang "Sunting Struktur" hanya sejauh satu klik
     * daripada memusnahkan keputusan Parlimen pada malam mengira undi.
     */
    private function assertBukanTakrifanParlimen(?Borang14Form $form): void
    {
        if (! $form) {
            return;
        }

        $bilangan = $form->borangDun()->count();
        if ($bilangan === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'pusat' => "Borang ini ialah TAKRIFAN calon PRU yang dikongsi oleh {$bilangan} borang DUN ".
                'dalam Parlimen ini. Undi PRU direkod pada borang DUN tersebut, bukan di sini, dan '.
                'keputusan Parlimen dikumpulkan daripadanya. Memberi borang ini struktur sendiri akan '.
                'menghentikan pengumpulan itu dan papan Parlimen akan memapar 0 undi. Namakan calon PRU '.
                'sahaja di sini; sunting struktur pada borang DUN masing-masing.',
        ]);
    }

    /**
     * Lubang yang SAMA daripada arah bertentangan: memaut borang DUN kepada
     * satu borang Parlimen yang SUDAH mempunyai struktur sendiri.
     *
     * firstOrCreate() di bawah akan memulangkan borang PRU sedia ada bagi
     * (Parlimen, tahun) itu. Jika borang itu sudah mempunyai struktur (kes
     * PRU-sahaja yang sah), pautan baharu ini menjadikannya takrifan DAN
     * borang berstruktur serentak — dan roll-up akan membaca TERUS daripada
     * struktur itu, mengabaikan setiap undi PRU pada borang DUN. Tolak, dan
     * beritahu apa yang perlu diselesaikan dahulu.
     */
    private function assertTakrifanParlimenMasihKosong(array $validated): void
    {
        if (empty($validated['parlimen_id'])) {
            return;
        }

        $takrifan = Borang14Form::forKawasan(Borang14Form::KAWASAN_PARLIMEN, (int) $validated['parlimen_id'])
            ->where('jenis_pr', 'pru')->where('tahun', $validated['tahun'])->first();

        if (! $takrifan || empty($takrifan->structure)) {
            return;
        }

        throw ValidationException::withMessages([
            'parlimen_id' => 'Borang Parlimen bagi tahun ini sudah mempunyai struktur (Pusat Mengundi) '.
                'sendiri, jadi keputusan Parlimen dibaca terus daripadanya dan bukan dikumpulkan '.
                'daripada borang DUN. Kosongkan struktur borang Parlimen itu (atau padamkannya) '.
                'sebelum menghidupkan mod pilihanraya serentak, jika tidak undi PRU yang dikunci pada '.
                'borang DUN ini tidak akan dikira langsung.',
        ]);
    }

    private function bolehSuntingStruktur(?User $user, ?Borang14Form $form, array $validated): bool
    {
        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdmin())) {
            return false;
        }
        if ($form && $form->status === 'published') {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        $bandarId = $validated['kawasan_type'] === Borang14Form::KAWASAN_PARLIMEN
            ? $validated['kawasan_id']
            : Kadun::whereKey($validated['kawasan_id'])->value('bandar_id');

        return $user->bandar_id !== null && (int) $user->bandar_id === (int) $bandarId;
    }

    /**
     * SATU tempat sahaja untuk rantaian fallback rujukan struktur — dikongsi
     * oleh data() DAN pdf() supaya kedua-duanya tidak boleh terpesong
     * (drift) antara satu sama lain. Susunan keutamaan:
     *   1) JSON kurasi (Borang14Reference) — pecahan Saluran rasmi gazet SPR.
     *   2) Struktur borang INI sendiri (referenceFromStructure()) — scoresheet
     *      yang dimuat naik bagi pilihan raya INI.
     *   2b) Anggaran DPT (Borang14Reference dengan source 'dpt_estimate') —
     *      hanya apabila (1) dan (2) tiada.
     *   3) Pilihan raya LAIN yang paling baru bagi kerusi yang SAMA
     *      ("warisi") — pilihan terakhir sahaja, kerana pada malam
     *      pengiraan pilihan raya BAHARU tiada scoresheet lagi (ia OUTPUT,
     *      bukan INPUT). Undi TIDAK PERNAH diwarisi — hanya pokok kosong
     *      Pusat Mengundi/Saluran.
     *
     * Sumber warisan PILIH status 'published' berbanding draf/belum disemak
     * apabila kedua-duanya wujud (rujuk laporan warisi-fix-report.md untuk
     * sebab), tetapi tidak MEWAJIBKAN published — kerusi yang hanya ada satu
     * draf sejarah tetap mewarisi draf itu, bukan "belum tersedia".
     *
     * `asal` melaporkan CABANG MANA yang menang — 'kurasi' | 'dpt' |
     * 'struktur' | 'warisan', atau null apabila tiada rujukan langsung.
     * Penyuntingan struktur bergantung padanya: panel disemai daripada
     * struktur borang INI sahaja, jadi ia hanya selamat dibuka apabila grid
     * yang dipaparkan memang datang dari situ (atau tiada grid langsung).
     *
     * @return array{reference: array|null, inherited_from: array{tahun:int, jenis_pr:string}|null, asal: string|null}
     */
    private function resolveReference(string $kawasanType, int $kawasanId, ?Borang14Form $form): array
    {
        $isParlimen = $kawasanType === Borang14Form::KAWASAN_PARLIMEN;
        $reference = $isParlimen
            ? Borang14Reference::forBandar($kawasanId)
            : Borang14Reference::forKadun($kawasanId);

        $asal = $reference
            ? (($reference['source'] ?? null) === 'dpt_estimate' ? 'dpt' : 'kurasi')
            : null;

        // Struktur scoresheet borang INI mengatasi anggaran DPT.
        //
        // Anggaran DPT mengumpul roll mengikut Lokaliti dan menganggap SATU
        // saluran bagi setiap Pusat Mengundi — ia sendiri mengaku anggaran.
        // Scoresheet pula ialah pecahan rasmi gazet SPR bagi kerusi ini.
        //
        // Membiarkan DPT menang menghasilkan kegagalan yang sunyi dan teruk:
        // undi ditulis di bawah kunci scoresheet ("SEKOLAH KEBANGSAAN TENGKEK|1|1")
        // tetapi grid dibina daripada kunci DPT ("KG KUALA JEMAPOH|1|1"), jadi
        // SETIAP sel memaparkan 0 walaupun undi selamat tersimpan — hanya baris
        // UNDI POS terselamat kerana labelnya kebetulan sepadan. Itulah yang
        // dilaporkan di produksi: Juasseh memaparkan 98/73 sedangkan 4,471/4,549
        // ada dalam pangkalan data.
        //
        // JSON kurasi (tiada kunci 'source') kekal keutamaan tertinggi.
        $isDptEstimate = ($reference['source'] ?? null) === 'dpt_estimate';
        if ((! $reference || $isDptEstimate) && ! empty($form?->structure['rows'])) {
            $dariStruktur = $this->referenceFromStructure($form->structure, $form->kawasan());
            if ($dariStruktur) {
                $reference = $dariStruktur;
                $asal = 'struktur';
            }
        }

        // On counting night for a NEW election there IS no scoresheet — it's the
        // OUTPUT, not the input — so a brand-new (kawasan, jenis_pr, tahun) has
        // neither a curated reference nor a structure of its own. Pusat Mengundi
        // & Saluran are essentially stable between elections, so inherit them
        // from the most recent OTHER election of the SAME seat rather than
        // showing "belum tersedia" for something that's really just missing a
        // scoresheet. Curated JSON / DPT (above) and this election's own
        // structure (above) both still win over this — inheritance is the LAST
        // resort. Votes are never inherited: callers read votes only from
        // $form (this election's own row), never from the source form here.
        $inheritedFrom = null;
        if (! $reference) {
            $sourceQuery = Borang14Form::forKawasan($kawasanType, $kawasanId)
                ->whereNotNull('structure')
                ->when($form, fn ($q) => $q->where('id', '!=', $form->id));

            // Prefer a PUBLISHED source over a draft/needs-review one — an
            // abandoned draft, or a scoresheet the AI misread and flagged
            // needs_review, should not become the structure a fresh election
            // silently inherits, even if it happens to be more recent than
            // the last published election. Only fall back to the best
            // available draft when NO published election of this seat
            // exists at all, so a seat whose only history is still in
            // progress gets something rather than "belum tersedia".
            $sourceForm = (clone $sourceQuery)->published()
                    ->orderByDesc('tahun')->orderByDesc('created_at')->first()
                ?? $sourceQuery->orderByDesc('tahun')->orderByDesc('created_at')->first();

            if ($sourceForm) {
                $reference = $this->referenceFromStructure($sourceForm->structure, $sourceForm->kawasan());
                $inheritedFrom = ['tahun' => $sourceForm->tahun, 'jenis_pr' => $sourceForm->jenis_pr];
                if ($reference) {
                    $asal = 'warisan';
                }
            }
        }

        return ['reference' => $reference, 'inherited_from' => $inheritedFrom, 'asal' => $asal];
    }

    /**
     * Builds a Borang14Reference-shaped structure straight from a scoresheet's raw
     * extraction when no curated reference file / DPT roll exists yet for the
     * kawasan (true for any seat this feature just created). berdaftar figures are
     * ALWAYS null here — the scoresheet has no registered-voter column, only column
     * (A) which is ballots in the box, not registrations. Rows with pusat === ''
     * (UNDI AWAL / UNDI POS) are surfaced as undi_awal/undi_pos and are only present
     * when the sheet actually printed that row — never fabricated.
     *
     * @return array<string,mixed>
     */
    private function referenceFromStructure(array $structure, Bandar|Kadun|null $kawasan): array
    {
        // Asal-usul mesti dilaporkan dengan jujur ke UI: 'manual' bermakna
        // seorang manusia menaip bentuk ini, bukan SPR yang menggazetkannya.
        $origin = ($structure['origin'] ?? null) === 'manual' ? 'manual' : 'scoresheet';

        $isParlimen = $kawasan instanceof Bandar;
        $daerah = [];
        $undiAwal = null;
        $undiPos = null;

        foreach ($structure['rows'] ?? [] as $r) {
            $pusat = (string) ($r['pusat'] ?? '');
            $saluran = $this->normalizeSaluran($r['saluran'] ?? null);

            if ($pusat === '') {
                // Carry the REAL saluran string through as 'label' — votes are
                // stored keyed on this exact string (putVote() uses
                // $row['saluran']), so the frontend must key off it too rather
                // than a hardcoded 'UNDI POS'/'UNDI AWAL' constant. If the AI
                // ever emits something other than the exact literal (e.g. an
                // extra suffix), a hardcoded key would silently show 0 votes
                // and any edit would write an orphan row under a key nothing
                // reads.
                $upper = strtoupper($saluran);
                if (str_contains($upper, 'AWAL')) {
                    $undiAwal = ['berdaftar' => null, 'label' => $saluran];
                } elseif (str_contains($upper, 'POS')) {
                    $undiPos = ['berdaftar' => null, 'label' => $saluran];
                }

                continue;
            }

            $dmNama = (string) ($r['dm'] ?? '');
            $daerah[$dmNama] ??= ['nama' => $dmNama, 'pusat_mengundi' => []];

            $pmIndex = null;
            foreach ($daerah[$dmNama]['pusat_mengundi'] as $i => $pm) {
                if ($pm['nama'] === $pusat) {
                    $pmIndex = $i;

                    break;
                }
            }
            if ($pmIndex === null) {
                $daerah[$dmNama]['pusat_mengundi'][] = ['nama' => $pusat, 'jumlah_berdaftar' => null, 'saluran' => []];
                $pmIndex = array_key_last($daerah[$dmNama]['pusat_mengundi']);
            }

            // Dedup: several raw rows can normalise to the SAME saluran value for one
            // Pusat (the blank-saluran case — the AI couldn't read that column at
            // all). Those votes are aggregated into ONE DB row by putVote(), so the
            // reference must show ONE Saluran row too, not a duplicate per raw row.
            $alreadyListed = collect($daerah[$dmNama]['pusat_mengundi'][$pmIndex]['saluran'])->contains('no', $saluran);
            if (! $alreadyListed) {
                $daerah[$dmNama]['pusat_mengundi'][$pmIndex]['saluran'][] = ['no' => $saluran, 'berdaftar' => null];
            }
        }

        return [
            'negeri'   => $isParlimen ? ($kawasan?->negeri?->nama ?? '') : ($kawasan?->bandar?->negeri?->nama ?? ''),
            'parlimen' => $isParlimen ? $kawasan?->nama : ($kawasan?->bandar?->nama ?? ''),
            'dun'      => $isParlimen ? null : $kawasan?->nama,
            'daerah_mengundi' => array_values($daerah),
            'undi_awal' => $undiAwal,
            'undi_pos'  => $undiPos,
            'source'    => $origin,
        ];
    }

    /** Negeri/Parlimen ids for a kawasan — lets the frontend picker resync from a bare form_id. */
    private function resolveIds(string $kawasanType, int $kawasanId): array
    {
        if ($kawasanType === Borang14Form::KAWASAN_PARLIMEN) {
            $bandar = Bandar::find($kawasanId);

            return ['negeri_id' => $bandar?->negeri_id, 'bandar_id' => $kawasanId];
        }

        $kadun = Kadun::find($kawasanId);

        return ['negeri_id' => $kadun?->bandar?->negeri_id, 'bandar_id' => $kadun?->bandar_id];
    }

    /**
     * Live silang-semak against the CURRENT votes (not the frozen extraction) so
     * fixing a cell clears its own warning on the next fetch. Column (A) itself is
     * not editable (no slot stores it), so it's taken as-is from the frozen
     * structure; everything else (party undi, ditolak, tidak dimasukkan) is
     * re-read from borang14_votes. Only meaningful for scoresheet-sourced forms —
     * manual entry has no independent (A) to check against.
     *
     * PADA BORANG SERENTAK, SETIAP PERTANDINGAN DISEMAK, bukan hanya
     * pertandingan borang itu sendiri. Sel PRU tinggal PADA borang DUN ini
     * (borang Parlimen yang dipaut hanyalah takrifan calon dan langsung tiada
     * undi), jadi sebelum ini tiada sesiapa pun menyemak baki jalur PRU: satu
     * digit PRU yang tertukar diterbitkan bersih dan mengalir terus melalui
     * Borang14RollUp ke papan Parlimen awam. Mesej dilabel mengikut jalur
     * supaya operator tahu kertas undi MANA yang tidak seimbang.
     *
     * @return string[]
     */
    private function crosscheckIssues(Borang14Form $form): array
    {
        // Takrifan Parlimen yang dipaut; null bermakna borang satu
        // pertandingan — kes yang paling biasa dan sudah di produksi.
        $parlimen = $form->borang14_form_parlimen_id !== null ? $form->formParlimen : null;

        // Pariti keluar didahulukan kerana ia TIDAK memerlukan sebarang angka
        // bercetak: ia membandingkan dua jalur antara satu sama lain. Itu
        // penting kerana borang serentak dicipta melalui panel Sunting
        // Struktur, dan Borang14StrukturService::expand() sentiasa menanda
        // struktur itu 'manual' — jadi semakan bercetak di bawah TIDAK akan
        // berjalan pada borang serentak yang biasa. Tanpa pariti di sini,
        // jalur PRU sekali lagi tidak disemak sesiapa.
        $isu = $parlimen ? $this->semakParitiKeluar($form, $parlimen) : [];

        $structure = $form->structure;
        if (empty($structure['rows'])) {
            return $isu;
        }

        // Struktur yang dibina dengan tangan tiada baris bercetak untuk
        // dibandingkan — tiada lajur (A), tiada 'jumlah_undian'. Menjalankan
        // validateBalance() ke atasnya akan membandingkan setiap sel dengan
        // sifar yang tidak pernah dicetak sesiapa, lalu menuduh borang yang
        // diisi dengan BETUL sebagai tidak seimbang.
        if (($structure['origin'] ?? null) === 'manual') {
            return $isu;
        }

        // Jalur borang ini sendiri — kelakuan tidak berubah langsung, termasuk
        // bentuk mesej apabila borang tidak dipaut (tiada label jalur).
        $isu = array_merge($isu, $this->semakBakiBercetak(
            form: $form,
            contest: $form->contestSendiri(),
            nCalon: max(1, (int) $form->penjuru),
            structure: $structure,
            calon: $structure['calon'] ?? null,
            label: $parlimen ? $this->labelKontes($form) : null,
        ));

        if ($parlimen) {
            $isu = array_merge($isu, $this->semakBakiBercetak(
                form: $form,
                contest: Borang14Vote::CONTEST_PARLIMEN,
                // Bilangan calon jalur PRU milik borang PARLIMEN, bukan borang
                // DUN — membaca penjuru DUN akan memotong lajur calon PRU dan
                // "mengimbangkan" baris yang sebenarnya tidak seimbang.
                nCalon: max(1, (int) $parlimen->penjuru),
                structure: $structure,
                // TIADA senarai calon PRU bercetak pada scoresheet DUN ini,
                // jadi peraturan calon_count tiada rujukan bebas: null di sini
                // menjadikannya mustahil menyala, bukan menyala secara palsu.
                calon: null,
                label: $this->labelKontes($parlimen),
                // 'jumlah_undian' bercetak ialah jumlah kertas undi PRN pada
                // sheet ini. Membandingkan hasil campur PRU dengannya akan
                // menuduh setiap borang serentak yang BETUL — hanya baki (A)
                // yang bermakna merentas kedua-dua kertas.
                hanyaBaki: true,
                // Baris yang jalur PRU-nya belum dikunci langsung ialah
                // "belum diketahui", BUKAN sifar undi. Melaporkannya akan
                // menyalakan setiap baris setiap borang pada saat mod serentak
                // dihidupkan, dan spanduk yang sentiasa merah ialah spanduk
                // yang tidak lagi dibaca sesiapa.
                langkauBelumDikunci: true,
            ));
        }

        return $isu;
    }

    /**
     * Semakan satu jalur terhadap angka BERCETAK pada scoresheet.
     *
     * Feed validateBalance() the REAL frozen values from the sheet's own
     * extraction — the printed 'jumlah_undian' total and the actual 'calon'
     * list — NOT values re-derived from the live undi array itself. Rebuilding
     * both from the same live array made 'jumlah_undian' and 'calon_count'
     * compare a number against itself (mathematically unreachable); only
     * 'balance' could ever fire. Now:
     *   - jumlah_undian: live vote sum vs the sheet's own printed total —
     *     fires when entered figures no longer add up to what was printed.
     *   - calon_count: live candidate slot count (nCalon) vs the sheet's
     *     own candidate list — fires if the extraction's column count
     *     diverges from the currently configured penjuru.
     *
     * @param  array|null  $calon  senarai calon bercetak; null = tiada rujukan
     *                             bebas, jadi calon_count dilumpuhkan
     * @return string[]
     */
    private function semakBakiBercetak(
        Borang14Form $form,
        string $contest,
        int $nCalon,
        array $structure,
        ?array $calon,
        ?string $label,
        bool $hanyaBaki = false,
        bool $langkauBelumDikunci = false,
    ): array {
        // Baris bercetak dalam `structure` datang daripada SATU scoresheet,
        // iaitu satu pertandingan. Membandingkannya dengan undi kedua-dua
        // pertandingan sekali gus akan menuduh setiap borang serentak yang
        // diisi dengan BETUL sebagai tidak seimbang.
        $votesByCell = $form->votesFor($contest)->get(['pusat', 'saluran', 'slot', 'undi'])
            ->groupBy(fn ($v) => $v->pusat.'|'.$v->saluran);

        $calon ??= array_fill(0, $nCalon, '');

        $liveRows = collect($structure['rows'])->map(function ($r) use ($votesByCell, $nCalon, $langkauBelumDikunci) {
            $pusat = (string) ($r['pusat'] ?? '');
            $saluran = $this->normalizeSaluran($r['saluran'] ?? null);
            $cells = $votesByCell->get($pusat.'|'.$saluran, collect());

            if ($langkauBelumDikunci && $cells->isEmpty()) {
                return null;
            }

            $slotVal = fn (int $n) => (int) ($cells->firstWhere('slot', $n)->undi ?? 0);

            $undi = [];
            for ($i = 1; $i <= $nCalon; $i++) {
                $undi[] = $slotVal($i);
            }

            return [
                'pusat' => $pusat,
                'saluran' => $saluran,
                'a' => (int) ($r['a'] ?? 0),
                'undi' => $undi,
                'jumlah_undian' => (int) ($r['jumlah_undian'] ?? 0),
                'ditolak' => $slotVal(90),
                'tidak_dimasukkan' => $slotVal(91),
            ];
        })->filter()->values()->all();

        $findings = ScoresheetExtractor::validateBalance([
            'calon' => $calon,
            'rows' => $liveRows,
        ]);

        return collect($findings)
            ->when($hanyaBaki, fn ($c) => $c->where('rule', 'balance'))
            ->map(fn ($f) => $this->formatCrosscheckMessage($f, $label))
            ->values()->all();
    }

    /**
     * Pariti keluar antara dua jalur borang serentak.
     *
     * Dalam pilihan raya serentak setiap pengundi yang keluar menerima KEDUA-DUA
     * kertas undi, jadi (A) = Σ undi + (C) + (D) mesti SAMA pada jalur PRN dan
     * jalur PRU bagi saluran yang sama. Ini rujukan silang yang percuma: ia
     * tidak memerlukan sebarang angka bercetak, jadi ia berjalan walaupun pada
     * struktur manual — mod yang hampir setiap borang serentak berada di
     * dalamnya.
     *
     * Dikira daripada undi SENDIRI (bukan daripada `structure`) supaya borang
     * yang tiada struktur sendiri pun tetap disemak.
     *
     * TIDAK DIKETAHUI BUKAN SIFAR: saluran yang salah satu jalurnya belum ada
     * SATU pun undi calon dilangkau sepenuhnya. Jalur yang belum dikunci bukan
     * jalur yang keluarnya sifar.
     *
     * @return string[]
     */
    private function semakParitiKeluar(Borang14Form $form, Borang14Form $parlimen): array
    {
        $sendiri = $form->contestSendiri();

        // Borang Parlimen yang entah bagaimana terpaut (simpanStruktur()
        // menolaknya, tetapi data lama boleh) tidak mempunyai dua jalur untuk
        // dibandingkan — jangan bandingkan sesuatu dengan dirinya sendiri dan
        // laporkan "seimbang" yang tidak bermakna.
        if ($sendiri === Borang14Vote::CONTEST_PARLIMEN) {
            return [];
        }

        // DUA bacaan votesFor() berasingan, bukan satu votes() merentas
        // kedua-duanya: setiap jumlah di sini ialah kiraan undi, dan kiraan
        // undi tidak sekali-kali dibaca melalui votes() dalam aplikasi ini.
        $jalurPrn = $this->selMengikutSaluran($form, $sendiri);
        $jalurPru = $this->selMengikutSaluran($form, Borang14Vote::CONTEST_PARLIMEN);

        $isu = [];

        foreach ($jalurPrn as $kunci => $prn) {
            $pru = $jalurPru->get($kunci);

            // Kedua-dua jalur mesti sudah dimulakan (sekurang-kurangnya satu
            // slot CALON dikunci) sebelum ia layak dibandingkan.
            if (! $this->jalurBermula($prn) || ! $this->jalurBermula($pru)) {
                continue;
            }

            $keluarPrn = $this->jumlahKeluar($prn);
            $keluarPru = $this->jumlahKeluar($pru);
            if ($keluarPrn === $keluarPru) {
                continue;
            }

            $first = $prn->first();
            $loc = $first->pusat !== '' ? "{$first->pusat} — Saluran {$first->saluran}" : $first->saluran;
            $isu[] = "{$loc}: Jum. Keluar tidak sama antara jalur — "
                .$this->labelKontes($form).' '.number_format($keluarPrn)
                .' berbanding '.$this->labelKontes($parlimen).' '.number_format($keluarPru)
                .'. Setiap pengundi menerima kedua-dua kertas undi, jadi angka ini sepatutnya sama.';
        }

        return $isu;
    }

    /**
     * Sel satu pertandingan, dikumpul mengikut "pusat|saluran".
     *
     * @return Collection<string, Collection>
     */
    private function selMengikutSaluran(Borang14Form $form, string $contest): Collection
    {
        return $form->votesFor($contest)
            ->whereIn('slot', [1, 2, 3, 4, 5, 6, 90, 91])
            ->get(['contest', 'pusat', 'saluran', 'slot', 'undi'])
            ->groupBy(fn ($v) => $v->pusat.'|'.$v->saluran);
    }

    /** Adakah jalur ini sudah dikunci — sekurang-kurangnya satu slot calon (1..6)? */
    private function jalurBermula(?Collection $sel): bool
    {
        return $sel !== null && $sel->contains(fn ($v) => $v->slot >= 1 && $v->slot <= 6);
    }

    /** (A) terbitan satu jalur: Σ undi calon + (C) ditolak + (D) tidak dimasukkan. */
    private function jumlahKeluar(Collection $sel): int
    {
        return (int) $sel->sum(fn ($v) => (int) $v->undi);
    }

    /**
     * Label jalur bagi mesej silang-semak — menamakan pertandingan DAN kerusinya,
     * sama seperti tajuk jalur pada skrin dua jalur.
     */
    private function labelKontes(Borang14Form $form): string
    {
        return $form->kawasan_type === Borang14Form::KAWASAN_PARLIMEN
            ? 'PRU · Parlimen '.$form->kawasanNama()
            : mb_strtoupper((string) $form->jenis_pr).' · DUN '.$form->kawasanNama();
    }

    private function formatCrosscheckMessage(array $f, ?string $label = null): string
    {
        $loc = $f['pusat'] !== '' ? "{$f['pusat']} — Saluran {$f['saluran']}" : $f['saluran'];
        // Label jalur hanya pada borang serentak: borang satu pertandingan
        // mesti mengekalkan mesej yang SERUPA BIT dengan hari ini.
        if ($label !== null) {
            $loc = "[{$label}] {$loc}";
        }

        return match ($f['rule']) {
            'balance' => "{$loc}: (A) dijangka {$f['jangka']}, dapat {$f['dapat']}",
            'jumlah_undian' => "{$loc}: jumlah undian dijangka {$f['jangka']}, dapat {$f['dapat']}",
            'calon_count' => "{$loc}: bilangan calon dijangka {$f['expected']}, dapat {$f['actual']}",
            default => "{$loc}: silang-semak tidak sepadan",
        };
    }

    public function pdf(Request $request)
    {
        // kawasan_id's exists-table depends on kawasan_type — validated against
        // bandar for Parlimen, kadun for DUN, so an id from the wrong table
        // (e.g. a Parlimen id passed with kawasan_type=dun) is always rejected.
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate([
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
            'parti'    => 'array',
            'parti.*'  => 'nullable|string|max:100',
        ]);

        $isParlimen = $validated['kawasan_type'] === Borang14Form::KAWASAN_PARLIMEN;
        $kawasanId = (int) $validated['kawasan_id'];

        $form = Borang14Form::where('kawasan_type', $validated['kawasan_type'])
            ->where('kawasan_id', $kawasanId)
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        // Same fallback chain as data() (curated JSON/DPT -> this form's own
        // structure -> inherited from the seat's most recent other election)
        // via ONE shared helper — a form created purely by keying votes
        // (saveParties()/saveVote()) never writes its own structure, so
        // without inheritance here "Muat Turun PDF" 404s even though the
        // on-screen grid renders fine off the inherited structure.
        ['reference' => $reference, 'inherited_from' => $inheritedFrom] = $this->resolveReference($validated['kawasan_type'], $kawasanId, $form);

        $seatLabel = $isParlimen ? 'Parlimen' : 'DUN';
        abort_if(! $reference, 404, "Data Borang 14 belum tersedia untuk {$seatLabel} ini.");

        // Prefer the party names passed from the page so the PDF column headers
        // match the on-screen dropdown selection exactly; fall back to the saved
        // form when the request doesn't carry them.
        $parties = $form?->parties ?? [];
        if ($request->filled('parti')) {
            $parties = [];
            foreach (array_values($request->input('parti')) as $i => $nama) {
                $parties[] = ['slot' => $i + 1, 'nama' => $nama];
            }
        }
        // Borang PDF ini ialah rekod SATU pertandingan — pertandingan borang
        // itu sendiri. Kunci selnya membawa contest yang sama seperti grid
        // kemasukan supaya hanya ADA SATU bentuk kunci dalam aplikasi ini;
        // blade pdf.borang14 membina kunci yang serupa daripada $contest.
        $contestPdf = $form?->contestSendiri()
            ?? ($isParlimen ? Borang14Vote::CONTEST_PARLIMEN : Borang14Vote::CONTEST_DUN);
        $votes = $form
            ? $form->votesFor($contestPdf)->get()->mapWithKeys(fn ($v) => [
                $this->cellKey($contestPdf, $v->pusat, $v->saluran, $v->slot) => $v->undi,
            ])->all()
            : [];

        $logo = $this->logoDataUri();

        $pdf = Pdf::loadView('pdf.borang14', [
            'reference' => $reference,
            'penjuru'   => (int) $validated['penjuru'],
            'penjuruLabel' => self::PENJURU[$validated['penjuru']] ?? '',
            'parties'   => $parties,
            'votes'     => $votes,
            'contest'   => $contestPdf,
            'logo'      => $logo,
            'inheritedFrom' => $inheritedFrom,
            // Buloh Kasap's Undi Awal/Pos merge is a DUN-only exception — a
            // Parlimen that happens to share id 41 must never trigger it.
            'isBulohKasap' => ! $isParlimen && $kawasanId === self::BULOH_KASAP_KADUN_ID,
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);

        $areaName = $isParlimen ? ($reference['parlimen'] ?? 'parlimen') : ($reference['dun'] ?? 'dun');
        $name = 'borang-14-' . str($areaName)->slug() . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($name);
    }

    /** TTL untuk cache dry-run — cukup masa untuk pengguna baca prompt pengesahan. */
    private const DRY_RUN_TTL_MINUTES = 15;

    /**
     * Upload scoresheet dalam DUA langkah supaya AI yang tersalah baca nama kawasan
     * boleh dibatalkan SEBELUM apa-apa ditulis:
     *   1) dry_run=1 + fail  -> baca (AI) + padan kawasan TANPA menulis; pulangkan token.
     *   2) token (tiada fail) -> baca semula hasil ekstrak dari cache ikut token, tulis sebenar.
     * Ekstrak (mahal, ~200s) hanya berlaku SEKALI, semasa langkah 1.
     */
    public function upload(Request $request, ScoresheetExtractor $extractor)
    {
        if ($request->boolean('dry_run')) {
            return $this->uploadDryRun($request, $extractor);
        }

        if ($request->filled('token')) {
            return $this->uploadCommit($request);
        }

        return response()->json(['message' => 'Permintaan muat naik tidak lengkap.'], 422);
    }

    /** Langkah 1: baca scoresheet, padan kawasan tanpa menulis, simpan hasil ekstrak dalam cache. */
    private function uploadDryRun(Request $request, ScoresheetExtractor $extractor)
    {
        $data = $request->validate([
            'fail' => 'required|file|mimes:xlsx,xls,csv,txt,pdf,jpg,jpeg,png,webp|max:20480',
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun' => 'required|integer|between:1959,2100',
        ]);

        @set_time_limit(200);

        $res = $extractor->extractDetailed($request->file('fail'));
        if (! $res['ok']) {
            return response()->json(['message' => $res['error'] ?: 'Bacaan scoresheet gagal. Semak Tetapan → Claude.'], 422);
        }

        $kawasan = KawasanResolver::resolve($res['data'], dryRun: true);
        if (! $kawasan['ok']) {
            return response()->json(['message' => $kawasan['error']], 422);
        }

        $token = Str::random(40);

        // Fail disimpan SEKARANG, bukan semasa commit: langkah commit tidak
        // menerima fail (hanya token), jadi ini satu-satunya masa bait-baitnya
        // ada. Baris sejarah pula hanya dicipta selepas commit berjaya, jadi
        // muat naik yang ditinggalkan meninggalkan fail tanpa rujukan — dibersihkan
        // oleh sweep di bawah supaya storan tidak membesar tanpa had.
        $this->pruneAbandonedScoresheets();
        $failPath = null;
        try {
            $failPath = $request->file('fail')->store(self::SCORESHEET_DIR, 'private');
        } catch (\Throwable) {
            // Simpanan gagal tidak boleh membatalkan bacaan yang sudah berjaya —
            // sejarah kehilangan fail asal, undi tetap masuk.
        }

        Cache::put($this->dryRunCacheKey($token), [
            'user_id' => $request->user()?->id,
            'extracted' => $res['data'],
            'jenis_pr' => $data['jenis_pr'],
            'tahun' => $data['tahun'],
            'filename' => $request->file('fail')->getClientOriginalName(),
            'fail_path' => $failPath,
        ], now()->addMinutes(self::DRY_RUN_TTL_MINUTES));

        $review = $this->computeNeedsReview($res['data']);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'will_create' => $kawasan['created'],
            // Surface the resolved seat type BEFORE anything is written, so the
            // confirm panel can show "Parlimen" vs "DUN" explicitly rather than
            // the user only finding out afterwards (or, before this fix, never —
            // the resolver used to hardcode DUN regardless of the sheet's level).
            'kawasan_type' => $kawasan['kawasan_type'],
            'negeri' => $res['data']['negeri'] ?? null,
            'kawasan_nama' => $res['data']['kawasan_nama'] ?? null,
            'needs_review' => $review['needs_review'],
            'unbalanced' => $review['unbalanced'],
            // 'deterministic' = dibaca terus dari borang SPR 760 (boleh dibuktikan);
            // 'ai' = dibaca oleh Claude. Ditunjukkan supaya pengguna tahu tahap
            // kepercayaan sebelum mengesahkan.
            'source' => $res['data']['source'] ?? 'ai',
        ]);
    }

    /** Langkah 2: ambil hasil ekstrak dari cache ikut token, cipta kawasan sebenar, tulis borang + undi. */
    private function uploadCommit(Request $request)
    {
        $data = $request->validate(['token' => 'required|string']);

        $cacheKey = $this->dryRunCacheKey($data['token']);
        $cached = Cache::get($cacheKey);

        // Token asing/luput/tidak wujud dilayan SAMA — jangan bocorkan sebab sebenar.
        if (! $cached || ($cached['user_id'] ?? null) !== $request->user()?->id) {
            return response()->json(['message' => 'Token muat naik tidak sah atau telah tamat tempoh. Sila muat naik semula.'], 422);
        }

        $extractedData = $cached['extracted'];

        $kawasan = KawasanResolver::resolve($extractedData, dryRun: false);
        if (! $kawasan['ok']) {
            Cache::forget($cacheKey);

            return response()->json(['message' => $kawasan['error']], 422);
        }

        $form = Borang14Form::firstOrNew([
            'kawasan_type' => $kawasan['kawasan_type'],
            'kawasan_id' => $kawasan['kawasan_id'],
            'jenis_pr' => $cached['jenis_pr'],
            'tahun' => $cached['tahun'],
        ]);

        // Pintu KETIGA kepada lubang yang sama: writeForm() menulis
        // 'structure' => $extractedData, jadi memuat naik scoresheet ke atas
        // borang TAKRIFAN Parlimen memberinya struktur dan menukar roll-up
        // kepada bacaan terus — sama seperti Sunting Struktur. Disekat di sini
        // sebelum apa-apa ditulis. (Lihat assertBukanTakrifanParlimen().)
        if ($form->exists && ($bilanganDun = $form->borangDun()->count()) > 0) {
            Cache::forget($cacheKey);

            return response()->json([
                'message' => "Borang Parlimen ini ialah TAKRIFAN calon PRU bagi {$bilanganDun} borang DUN. ".
                    'Scoresheet PRU dimuat naik ke borang DUN masing-masing, bukan ke sini — memuat naik '.
                    'ke borang takrifan akan memberinya struktur sendiri dan keputusan Parlimen akan '.
                    'berhenti dikumpulkan daripada borang DUN.',
            ], 422);
        }

        $review = $this->computeNeedsReview($extractedData);

        // Satu commit menulis borang + snapshot + ratusan baris undi. Tanpa
        // transaksi, kegagalan separuh jalan meninggalkan borang dengan undi
        // yang tidak lengkap — dan undi lama SUDAH dipadam, jadi kerugiannya
        // kekal. (CLAUDE.md menamakan kaedah ini sebagai salah satu penulisan
        // berbilang baris yang belum dibungkus.)
        DB::transaction(function () use ($form, $extractedData, $review, $cached, $request, $kawasan) {
            $this->writeForm($form, $extractedData, $review, $cached, $request);
            $this->recordUpload($form, $extractedData, $review, $cached, $request, $kawasan);
        });

        // Elak main semula token selepas commit berjaya.
        Cache::forget($cacheKey);

        return response()->json([
            'ok' => true,
            'form_id' => $form->id,
            'created' => $kawasan['created'],
            'unbalanced' => $review['unbalanced'],
            'needs_review' => $form->needs_review,
            'source' => $extractedData['source'] ?? 'ai',
        ]);
    }

    /**
     * Tulis borang, snapshot pemulihan, dan setiap sel undi. Mesti dalam transaksi.
     *
     * $contest melalaikan kepada pertandingan borang itu sendiri — muat naik
     * scoresheet hari ini sentiasa satu pertandingan sahaja (satu borang = satu
     * kawasan_type), jadi ini kekal SAMA seperti sebelum ini. Snapshot yang
     * dirakam tetap merangkumi KEDUA-DUA pertandingan (untuk revert() penuh),
     * tetapi PADAMAN sebelum menulis semula mesti terhad kepada pertandingan
     * yang sedang ditulis — jika tidak, muat naik PRU memadam undi PRN yang
     * sudah dikunci masuk oleh pasukan lain pada borang serentak yang sama.
     */
    private function writeForm(Borang14Form $form, array $extractedData, array $review, array $cached, Request $request, ?string $contest = null): void
    {
        $contest ??= $form->contestSendiri();

        // Scoresheet menang — tetapi simpan keadaan lama dahulu supaya boleh revert.
        if ($form->exists) {
            Borang14Snapshot::create([
                'borang14_form_id' => $form->id,
                'structure' => $form->structure,
                'votes' => $form->votes()->get(['contest', 'pusat', 'saluran', 'slot', 'undi'])->toArray(),
                'parties' => $form->parties,
                'reason' => 'before_scoresheet_overwrite',
                'created_by' => $request->user()?->id,
            ]);
            $form->votesFor($contest)->delete();
        }

        $form->fill([
            'penjuru' => max(2, count($extractedData['calon'] ?? [])),
            // 'nama' starts out as the candidate's own name (placeholder until the
            // user maps a party) while 'calon' permanently records who the AI read
            // off the sheet, so the dropdown UI can always show "Calon: X" even
            // after a party is picked and 'nama' becomes the party name instead.
            'parties' => collect($extractedData['calon'] ?? [])->values()
                ->map(fn ($c, $i) => [
                    'slot' => $i + 1, 'keahlian_parti_id' => null,
                    'nama' => $c['nama'], 'calon' => $c['nama'],
                ])->all(),
            'structure' => $extractedData,
            'status' => 'draft',
            'source' => 'scoresheet',
            'source_filename' => $cached['filename'],
            'needs_review' => $review['needs_review'],
        ])->save();

        foreach ($extractedData['rows'] as $r) {
            foreach (($r['undi'] ?? []) as $i => $undi) {
                $this->putVote($form, $r, $i + 1, (int) $undi, $contest);
            }
            $this->putVote($form, $r, 90, (int) ($r['ditolak'] ?? 0), $contest);
            $this->putVote($form, $r, 91, (int) ($r['tidak_dimasukkan'] ?? 0), $contest);
        }
    }

    /**
     * Rekod satu baris sejarah bagi muat naik ini.
     *
     * `totals` menyimpan KEDUA-DUA angka: yang DICETAK pada sheet dan yang
     * DICAMPUR daripada baris yang diekstrak. Menyimpan hanya satu daripadanya
     * akan menyembunyikan percanggahan seperti kegagalan produksi (98 lawan
     * 4,471) — perbandingan itu sendiri ialah rekod auditnya.
     *
     * Setiap angka yang tidak dicetak kekal null, bukan 0.
     */
    private function recordUpload(Borang14Form $form, array $extractedData, array $review, array $cached, Request $request, array $kawasan): void
    {
        $rows = $extractedData['rows'] ?? [];
        $printed = $extractedData['jumlah'] ?? null;
        $isParlimen = $form->kawasan_type === Borang14Form::KAWASAN_PARLIMEN;
        $seat = $form->kawasan();
        $bandar = $isParlimen ? $seat : $seat?->bandar;

        $sum = fn (string $key) => (int) collect($rows)->sum(fn ($r) => (int) ($r[$key] ?? 0));
        $sumUndi = collect($rows)->reduce(function (array $carry, array $r) {
            foreach (($r['undi'] ?? []) as $i => $v) {
                $carry[$i] = ($carry[$i] ?? 0) + (int) $v;
            }

            return $carry;
        }, []);

        Borang14Upload::create([
            'borang14_form_id' => $form->id,
            'kawasan_type' => $form->kawasan_type,
            'kawasan_id' => $form->kawasan_id,
            'negeri' => $bandar?->negeri?->nama,
            'parlimen' => $bandar?->nama,
            'dun' => $isParlimen ? null : $seat?->nama,
            'nama_fail' => $cached['filename'],
            'fail_path' => $cached['fail_path'] ?? null,
            'jenis_pr' => $cached['jenis_pr'],
            'tahun' => $cached['tahun'],
            'source' => $extractedData['source'] ?? 'ai',
            'row_count' => count($rows),
            'saluran_count' => $extractedData['saluran_count'] ?? null,
            'totals' => [
                'pemilih' => $extractedData['jumlah_pemilih'] ?? null,
                'dicetak' => $printed ? [
                    'undi' => array_values($printed['undi'] ?? []),
                    'keluar' => $printed['jumlah_undian'] ?? null,
                    'ditolak' => $printed['ditolak'] ?? null,
                    'tidak_dimasukkan' => $printed['tidak_dimasukkan'] ?? null,
                ] : null,
                'dikira' => [
                    'undi' => array_values($sumUndi),
                    'keluar' => $sum('jumlah_undian'),
                    'ditolak' => $sum('ditolak'),
                    'tidak_dimasukkan' => $sum('tidak_dimasukkan'),
                ],
                'calon' => array_column($extractedData['calon'] ?? [], 'nama'),
                'percanggahan' => $review['unbalanced'],
            ],
            'needs_review' => $review['needs_review'],
            'uploaded_by' => $request->user()?->id,
        ]);
    }

    private function dryRunCacheKey(string $token): string
    {
        return 'borang14:upload-dry-run:' . $token;
    }

    /**
     * Satu-satunya tempat "needs_review" dikira — dikongsi antara dry run dan commit
     * supaya kedua-dua langkah tidak boleh terpesong (drift) antara satu sama lain.
     */
    private function computeNeedsReview(array $extractedData): array
    {
        // DUA semakan berbeza, kedua-duanya perlu:
        //   validateBalance()  — setiap baris terhadap dirinya sendiri
        //   reconcileTotals()  — jumlah semua baris terhadap baris JUMLAH BERCETAK
        // Semakan kedua itulah yang akan menangkap kegagalan produksi (hanya baris
        // UNDI POS diekstrak: 98/73 diterbitkan, sedangkan sheet mencetak 4,471/4,549).
        $unbalanced = array_merge(
            ScoresheetExtractor::validateBalance($extractedData),
            ScoresheetExtractor::reconcileTotals($extractedData),
        );
        $anyGuess = collect($extractedData['calon'] ?? [])->contains(fn ($c) => ! ($c['yakin'] ?? false));
        $noSaluran = collect($extractedData['rows'] ?? [])
            ->contains(fn ($r) => ($r['pusat'] ?? '') !== '' && blank($r['saluran'] ?? null));

        return [
            'unbalanced' => $unbalanced,
            'needs_review' => $unbalanced !== [] || $anyGuess || $noSaluran,
        ];
    }

    /**
     * Satu baris per sel. Baris Undi Pos/Awal guna pusat=''.
     *
     * AGGREGATE (jumlah), bukan tulis ganti: beberapa baris mentah scoresheet
     * boleh menyimpang kepada kunci sel (pusat, saluran, slot) yang SAMA apabila
     * saluran kosong (AI gagal baca lajur "No. Tempat Mengundi" untuk baris itu)
     * — spesifikasi memerlukan baris sebegini digabung menjadi SATU baris per
     * Pusat, bukan biarkan baris terakhir menulis ganti yang sebelumnya (undi
     * hilang secara senyap). Untuk kes biasa (saluran sebenar, unik per baris)
     * kelakuan ini SAMA seperti tulis ganti — tiada baris sedia ada untuk
     * ditambah kepada, jadi ia berkelakuan seperti "set" seperti sebelum ini.
     */
    private function putVote(Borang14Form $form, array $row, int $slot, int $undi, ?string $contest = null): void
    {
        $key = [
            'borang14_form_id' => $form->id,
            'contest' => $contest ?? $form->contestSendiri(),
            'pusat' => (string) ($row['pusat'] ?? ''),
            'saluran' => $this->normalizeSaluran($row['saluran'] ?? null),
            'slot' => $slot,
        ];

        $existingUndi = (int) (Borang14Vote::where($key)->value('undi') ?? 0);

        Borang14Vote::updateOrCreate($key, ['undi' => $existingUndi + $undi]);
    }

    /**
     * SATU tempat sahaja untuk normalkan saluran kosong — writer (putVote())
     * dan readers (referenceFromStructure(), crosscheckIssues()) MESTI guna
     * nilai yang SAMA, jika tidak kunci sel (cellKey) menyimpang: undi
     * tersimpan di DB di bawah satu kunci, tetapi skrin/PDF membaca kunci
     * lain — sel kelihatan 0 walaupun undi selamat tersimpan, dan sebarang
     * suntingan skrin menulis baris anak-yatim baharu yang tiada siapa baca.
     */
    private function normalizeSaluran(?string $raw): string
    {
        return trim((string) ($raw ?? ''));
    }

    /**
     * Kunci padanan nama Pusat Mengundi yang tidak sensitif huruf besar/kecil
     * atau ruang lampau — sepadan dengan collation MySQL (utf8mb4_unicode_ci)
     * yang menguasai indeks unik borang14_votes, dan konvensyen nameKey() yang
     * sama seperti ElectionDataService/ElectionAnalyticsService. HANYA untuk
     * PERBANDINGAN dalam guard simpanStruktur() — nama yang disimpan ke DB
     * tidak sekali-kali diubah huruf besarnya di sini.
     */
    private function nameKey(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    public function publish(Request $request)
    {
        $data = $request->validate(['form_id' => 'required|integer|exists:borang14_forms,id']);
        $form = Borang14Form::findOrFail($data['form_id']);
        $form->update(['status' => 'published', 'published_at' => now()]);

        return response()->json(['ok' => true, 'published_at' => $form->published_at]);
    }

    public function revert(Request $request)
    {
        $data = $request->validate(['form_id' => 'required|integer|exists:borang14_forms,id']);
        $form = Borang14Form::findOrFail($data['form_id']);

        $snap = $form->snapshots()->latest('created_at')->first();
        if (! $snap) {
            return response()->json(['message' => 'Tiada snapshot untuk dipulihkan.'], 422);
        }

        $form->votes()->delete();
        foreach ($snap->votes as $v) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id,
                // Snapshot lama tiada `contest` — anggap ia pertandingan borang
                // itu sendiri, iaitu satu-satunya yang wujud ketika itu.
                'contest' => $v['contest'] ?? $form->contestSendiri(),
                'pusat' => $v['pusat'], 'saluran' => $v['saluran'],
                'slot' => $v['slot'], 'undi' => $v['undi'],
            ]);
        }
        $form->update(['structure' => $snap->structure, 'parties' => $snap->parties]);

        return response()->json(['ok' => true]);
    }

    public function senarai(Request $request)
    {
        $data = $request->validate([
            'negeri_id' => 'required|integer|exists:negeri,id',
            'bandar_id' => 'nullable|integer|exists:bandar,id',
            'kadun_id' => 'nullable|integer|exists:kadun,id',
        ]);

        // Semantik penapis (spec):
        //   Negeri sahaja      -> semua rekod dalam negeri (Parlimen DAN DUN)
        //   + Parlimen         -> rekod Parlimen itu DAN semua DUN di bawahnya
        //   + DUN              -> DUN itu sahaja
        $bandarIds = Bandar::where('negeri_id', $data['negeri_id'])->pluck('id');
        if (! empty($data['bandar_id'])) {
            $bandarIds = collect([$data['bandar_id']]);
        }
        $kadunIds = ! empty($data['kadun_id'])
            ? collect([$data['kadun_id']])
            : Kadun::whereIn('bandar_id', $bandarIds)->pluck('id');

        $rows = Borang14Form::query()
            ->where(function ($q) use ($bandarIds, $kadunIds, $data) {
                if (empty($data['kadun_id'])) {
                    $q->orWhere(fn ($w) => $w->where('kawasan_type', 'parlimen')->whereIn('kawasan_id', $bandarIds));
                }
                $q->orWhere(fn ($w) => $w->where('kawasan_type', 'dun')->whereIn('kawasan_id', $kadunIds));
            })
            ->orderByDesc('tahun')->orderBy('jenis_pr')->orderBy('kawasan_type')
            ->get()
            ->map(function (Borang14Form $f) {
                // Resolve the real ids ourselves so the frontend never has to
                // recover them by matching kawasan_nama strings — duplicate
                // names within a state (plausible since this feature CREATES
                // seats from AI-read sheets) would otherwise silently target
                // the wrong seat.
                $isParlimen = $f->kawasan_type === Borang14Form::KAWASAN_PARLIMEN;
                $kawasan = $f->kawasan();
                $bandar = $isParlimen ? $kawasan : $kawasan?->bandar;

                return [
                    'id' => $f->id, 'tahun' => $f->tahun, 'jenis_pr' => $f->jenis_pr,
                    'kawasan_type' => $f->kawasan_type, 'kawasan_id' => $f->kawasan_id,
                    'kawasan_nama' => $kawasan?->nama ?? '—',
                    'negeri_id' => $bandar?->negeri_id,
                    'bandar_id' => $isParlimen ? $f->kawasan_id : $kawasan?->bandar_id,
                    'penjuru' => $f->penjuru, 'status' => $f->status, 'source' => $f->source,
                    'source_filename' => $f->source_filename, 'needs_review' => $f->needs_review,
                    'published_at' => $f->published_at,
                ];
            });

        return response()->json(['rows' => $rows]);
    }

    /**
     * Sejarah muat naik scoresheet, terbaharu dahulu, ditapis mengikut kawasan
     * yang sama seperti senarai() supaya kedua-dua panel menunjukkan skop yang
     * konsisten.
     */
    public function sejarahUpload(Request $request)
    {
        $data = $request->validate([
            'negeri_id' => 'nullable|integer|exists:negeri,id',
            'bandar_id' => 'nullable|integer|exists:bandar,id',
            'kadun_id' => 'nullable|integer|exists:kadun,id',
        ]);

        $rows = Borang14Upload::query()
            ->with('uploader:id,name')
            ->when($data['kadun_id'] ?? null, fn ($q, $id) => $q->where('kawasan_type', 'dun')->where('kawasan_id', $id))
            ->when(! ($data['kadun_id'] ?? null) && ($data['bandar_id'] ?? null), function ($q, $_) use ($data) {
                $kadunIds = Kadun::where('bandar_id', $data['bandar_id'])->pluck('id');
                $q->where(function ($w) use ($data, $kadunIds) {
                    $w->orWhere(fn ($x) => $x->where('kawasan_type', 'parlimen')->where('kawasan_id', $data['bandar_id']))
                        ->orWhere(fn ($x) => $x->where('kawasan_type', 'dun')->whereIn('kawasan_id', $kadunIds));
                });
            })
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Borang14Upload $u) => [
                'id' => $u->id,
                'form_id' => $u->borang14_form_id,
                'kawasan' => trim(collect([$u->parlimen, $u->dun])->filter()->implode(' / ')) ?: '—',
                'negeri' => $u->negeri ?? '—',
                'nama_fail' => $u->nama_fail,
                'jenis_pr' => $u->jenis_pr,
                'tahun' => $u->tahun,
                'source' => $u->source,
                'row_count' => $u->row_count,
                'saluran_count' => $u->saluran_count,
                'totals' => $u->totals,
                'needs_review' => $u->needs_review,
                // Fail lama boleh dipadam dari cakera walaupun barisnya kekal —
                // UI perlu tahu supaya tidak menawarkan pautan yang mati.
                'boleh_muat_turun' => (bool) $u->fail_path,
                'oleh' => $u->uploader?->name ?? '—',
                'tarikh' => $u->created_at?->toDateTimeString(),
            ]);

        return response()->json(['rows' => $rows]);
    }

    /**
     * Hidangkan fail scoresheet asal dari disk 'private'.
     *
     * Fail ini TIDAK PERNAH boleh dicapai terus melalui URL awam — hanya melalui
     * laluan ini, dan hanya selepas skop pengguna disemak semula pada masa muat
     * turun (bukan bergantung pada tapisan senarai, yang boleh dipintas dengan
     * meneka id).
     */
    public function muatTurunUpload(Request $request, Borang14Upload $upload)
    {
        if (! $this->bolehCapaiUpload($request->user(), $upload)) {
            abort(403, 'Unauthorized action.');
        }

        if (! $upload->fail_path || ! Storage::disk('private')->exists($upload->fail_path)) {
            abort(404, 'Fail scoresheet tidak lagi tersedia.');
        }

        return Storage::disk('private')->download($upload->fail_path, $upload->nama_fail);
    }

    /**
     * super_admin melihat semua; admin terhad kepada Parlimen (Bandar) sendiri;
     * pengguna lain terhad kepada DUN (Kadun) sendiri.
     *
     * Muat naik yang tidak dapat ditempatkan pada mana-mana kawasan hanya boleh
     * dicapai oleh super_admin — "tidak diketahui" tidak boleh bermakna "terbuka
     * kepada semua orang".
     */
    private function bolehCapaiUpload(?User $user, Borang14Upload $upload): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        if (! $upload->kawasan_type || ! $upload->kawasan_id) {
            return false;
        }

        $bandarId = $upload->kawasan_type === Borang14Form::KAWASAN_PARLIMEN
            ? $upload->kawasan_id
            : Kadun::whereKey($upload->kawasan_id)->value('bandar_id');

        if ($user->isAdmin()) {
            return $user->bandar_id !== null && (int) $user->bandar_id === (int) $bandarId;
        }

        return $upload->kawasan_type === Borang14Form::KAWASAN_DUN
            && $user->kadun_id !== null
            && (int) $user->kadun_id === (int) $upload->kawasan_id;
    }

    /**
     * Buang fail scoresheet yang tiada baris sejarah merujuknya dan sudah lebih
     * lama daripada tempoh sah token — iaitu muat naik yang dibaca tetapi tidak
     * pernah disahkan. Tanpa ini, setiap dry run yang ditinggalkan meninggalkan
     * fail sehingga 20 MB pada cakera selama-lamanya.
     */
    private function pruneAbandonedScoresheets(): void
    {
        try {
            $disk = Storage::disk('private');
            $cutoff = now()->subMinutes(self::DRY_RUN_TTL_MINUTES + 60)->getTimestamp();
            $files = collect($disk->files(self::SCORESHEET_DIR))
                ->filter(fn ($p) => $disk->lastModified($p) < $cutoff);

            if ($files->isEmpty()) {
                return;
            }

            $kekal = Borang14Upload::whereIn('fail_path', $files->all())->pluck('fail_path')->all();
            $disk->delete($files->reject(fn ($p) => in_array($p, $kekal, true))->all());
        } catch (\Throwable) {
            // Pembersihan tidak boleh menggagalkan muat naik yang sah.
        }
    }

    /**
     * Padam satu rekod Borang 14 (borang + semua undinya).
     *
     * SNAPSHOT DAHULU, kemudian padam. Rekod ini memegang undi sebenar, jadi
     * satu klik pada baris yang salah tidak boleh memusnahkannya secara kekal —
     * Borang14Snapshot 'before_delete' menyimpan undi, struktur dan pemetaan
     * parti supaya ia masih boleh dipulihkan daripada pangkalan data.
     *
     * Arkib ditulis ke jadual BERASINGAN, bukan borang14_snapshots: lajur
     * borang14_form_id jadual itu NOT NULL dengan cascadeOnDelete, jadi arkib
     * di sana akan dipadam oleh padaman yang sepatutnya ia lindungi.
     */
    public function hapus(Request $request)
    {
        $data = $request->validate(['form_id' => 'required|integer|exists:borang14_forms,id']);
        $form = Borang14Form::findOrFail($data['form_id']);

        if (! $this->bolehPadamBorang($request->user(), $form)) {
            abort(403, 'Unauthorized action.');
        }

        $kawasan = $form->kawasan()?->nama ?? '—';

        DB::transaction(function () use ($form, $request, $kawasan) {
            Borang14DeletedForm::create([
                'kawasan_type' => $form->kawasan_type,
                'kawasan_id' => $form->kawasan_id,
                'kawasan_nama' => $kawasan,
                'jenis_pr' => $form->jenis_pr,
                'tahun' => $form->tahun,
                'status' => $form->status,
                'structure' => $form->structure,
                'votes' => $form->votes()->get(['contest', 'pusat', 'saluran', 'slot', 'undi'])->toArray(),
                'parties' => $form->parties,
                'deleted_by' => $request->user()?->id,
            ]);

            // Sengaja TIDAK terhad kepada satu contest — borang itu SENDIRI sedang
            // dipadam (baris di bawah), jadi kedua-dua pertandingan pada borang
            // serentak mesti turut lenyap bersama. Ini BUKAN lubang contest-blind
            // yang sama seperti reset()/writeForm(): di situ borang KEKAL wujud
            // selepas padaman, di sini ia tidak. Arkib di atas (yang MERANGKUMI
            // kedua-dua contest) ialah satu-satunya cara memulihkannya.
            $form->votes()->delete();
            $form->delete();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * super_admin boleh memadam apa-apa. Admin terhad kepada rekod DRAF dalam
     * Parlimen mereka sendiri — rekod DITERBITKAN ialah keputusan rasmi yang
     * sudah disiarkan ke Scoreboard, jadi membuangnya ialah keputusan
     * super_admin.
     */
    private function bolehPadamBorang(?User $user, Borang14Form $form): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        if (! $user->isAdmin() || $form->status === 'published') {
            return false;
        }

        $bandarId = $form->kawasan_type === Borang14Form::KAWASAN_PARLIMEN
            ? $form->kawasan_id
            : Kadun::whereKey($form->kawasan_id)->value('bandar_id');

        return $user->bandar_id !== null && (int) $user->bandar_id === (int) $bandarId;
    }

    /**
     * Padam satu baris sejarah muat naik, berserta fail scoresheet yang
     * disimpan. Undi yang telah dimasukkan ke dalam borang TIDAK disentuh —
     * memadam jejak muat naik tidak sepatutnya membatalkan keputusan yang
     * dibina daripadanya (gunakan Padam pada tab Papar untuk itu).
     */
    public function hapusUpload(Request $request, Borang14Upload $upload)
    {
        if (! $this->bolehCapaiUpload($request->user(), $upload)) {
            abort(403, 'Unauthorized action.');
        }

        if ($upload->fail_path) {
            try {
                Storage::disk('private')->delete($upload->fail_path);
            } catch (\Throwable) {
                // Fail yang tidak dapat dibuang tidak boleh mengekalkan baris
                // sejarah yang pengguna minta dipadam.
            }
        }

        $upload->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Kunci sel grid kemasukan. `contest` ialah komponen PERTAMA dan WAJIB:
     * pada borang serentak, sel PRU dan PRN berkongsi (pusat, saluran, slot)
     * yang sama, jadi kunci tanpa contest akan membuat satu menulis ganti
     * satu lagi dalam keadaan frontend.
     *
     * BENTUK INI IALAH KONTRAK MERENTAS SEMPADAN — `cellKey()` dalam
     * resources/js/Pages/Pilihanraya/components/Borang14Form.jsx mesti
     * menghasilkan rentetan yang SERUPA BIT. Jika ia menyimpang, setiap sel
     * dipapar kosong.
     */
    private function cellKey(string $contest, ?string $pusat, string $saluran, int $slot): string
    {
        return $contest . '|' . ($pusat ?? '') . '|' . $saluran . '|' . $slot;
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/logo.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }
}
