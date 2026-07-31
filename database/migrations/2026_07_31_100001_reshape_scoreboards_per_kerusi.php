<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Papan markah beralih daripada milik satu Borang 14 kepada milik satu KERUSI.
 *
 * Sebelum: UNIQUE(borang14_form_id) — satu DUN boleh memegang beberapa papan,
 * dan papan yang dipaparkan ialah "Borang 14 dengan updated_at terkini", yang
 * bertukar secara senyap apabila senario lain disunting.
 * Selepas: UNIQUE(kawasan_type, kawasan_id) — satu papan bagi satu kerusi,
 * dengan borang14_form_id sebagai sumber undi PILIHAN pemilik.
 *
 * Turutan MySQL (ralat 1553): gugur FK → gugur index → ubah lajur → pasang
 * semula FK. Menggugurkan index unique yang disandari FK tanpa menggugurkan FK
 * dahulu akan gagal pada MySQL.
 *
 * BACA 2026_07_16_100001_reshape_borang14_forms.php sebelum menyunting fail
 * ini — ia mendokumenkan perangkap 1553 dan perangkap rebuild SQLite.
 */
return new class extends Migration
{
    /** Digunakan sekali sahaja untuk mengisi pihak_kami papan sedia ada. */
    private const PH_PARTIES = ['KEADILAN', 'PKR', 'DAP', 'AMANAH', 'MUDA'];

    public function up(): void
    {
        if (Schema::hasColumn('scoreboards', 'kawasan_type')) {
            return; // Sudah dibentuk semula.
        }

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->string('kawasan_type', 10)->nullable()->after('id');
            $table->unsignedBigInteger('kawasan_id')->nullable()->after('kawasan_type');
            $table->string('status', 10)->default('draf')->after('minima');
            $table->string('kod', 12)->nullable()->after('status');
            $table->json('pihak_kami')->nullable()->after('candidates');
            // Seorang admin Parlimen dan pemilik DUN boleh menyunting papan yang
            // SAMA. Tiada kunci dibina; kita hanya menjadikan perlanggaran
            // KELIHATAN dengan menunjukkan siapa menyimpan terakhir.
            $table->foreignId('updated_by')->nullable()->after('pihak_kami')
                ->constrained('users')->nullOnDelete();
        });

        // Pemadaman fail TIDAK boleh berlaku di dalam transaksi: jika transaksi
        // digulung semula, baris pangkalan data kembali tetapi fail imej sudah
        // hilang selama-lamanya. Kumpul dahulu, padam selepas komit.
        $yatim = [];
        DB::transaction(function () use (&$yatim) {
            $this->backfillSeats();
            $this->backfillPihakKami();
            $yatim = $this->collapseDuplicateBoards();
        });

        foreach ($yatim as $path) {
            if (str_starts_with($path, 'uploads/') && is_file(public_path($path))) {
                @unlink(public_path($path));
            }
        }

        // FK dahulu, kemudian index unique (ralat 1553), kemudian lajur nullable.
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->dropForeign(['borang14_form_id']);
        });
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->dropUnique(['borang14_form_id']);
        });
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->unsignedBigInteger('borang14_form_id')->nullable()->change();
        });
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->foreign('borang14_form_id')->references('id')->on('borang14_forms')->nullOnDelete();
            $table->unique(['kawasan_type', 'kawasan_id'], 'scoreboards_kerusi_unique');
            $table->unique('kod', 'scoreboards_kod_unique');
        });
    }

    /**
     * Kerusi papan diwarisi daripada Borang 14 yang dipegangnya hari ini.
     *
     * Ditulis sebagai subquery berkorelasi (bukan ->join()->update() dengan
     * DB::raw() pada SET), kerana grammar SQLite mengkompil join-update
     * kepada corak "WHERE rowid IN (subquery)" yang tidak boleh merujuk
     * lajur jadual yang disertakan di dalam klausa SET — gagal dengan
     * "no such column: borang14_forms.kawasan_type". Subquery berkorelasi
     * berfungsi sama di MySQL dan SQLite, jadi tiada cawangan pemacu
     * diperlukan di sini.
     */
    private function backfillSeats(): void
    {
        DB::statement(<<<'SQL'
            UPDATE scoreboards
            SET kawasan_type = (
                    SELECT f.kawasan_type FROM borang14_forms f WHERE f.id = scoreboards.borang14_form_id
                ),
                kawasan_id = (
                    SELECT f.kawasan_id FROM borang14_forms f WHERE f.id = scoreboards.borang14_form_id
                )
            WHERE borang14_form_id IS NOT NULL
        SQL);
    }

    /**
     * Papan sedia ada diserlahkan mengikut PH_PARTIES yang dikekod tetap dalam
     * pengawal. Tanda slot yang sepadan supaya serlahan semasa kekal dan tidak
     * reset kepada kosong apabila kod itu dibuang.
     */
    private function backfillPihakKami(): void
    {
        $rows = DB::table('scoreboards')
            ->join('borang14_forms', 'scoreboards.borang14_form_id', '=', 'borang14_forms.id')
            ->select('scoreboards.id', 'borang14_forms.parties')
            ->get();

        foreach ($rows as $row) {
            $parties = json_decode((string) $row->parties, true) ?: [];
            $slots = [];
            foreach ($parties as $i => $p) {
                $nama = strtoupper((string) ($p['nama'] ?? ''));
                if (in_array($nama, self::PH_PARTIES, true)) {
                    $slots[] = $i + 1;
                }
            }
            DB::table('scoreboards')->where('id', $row->id)->update(['pihak_kami' => json_encode($slots)]);
        }
    }

    /**
     * Satu kerusi boleh memegang beberapa papan hari ini. Kekalkan yang
     * updated_at terkini — itulah yang sedang dipaparkan — dan padam yang lain.
     *
     * Memulangkan senarai fail imej yatim (dirujuk HANYA oleh papan yang
     * dipadam) untuk dinyahpaut oleh pemanggil SELEPAS transaksi komit.
     *
     * @return array<int, string>
     */
    private function collapseDuplicateBoards(): array
    {
        $yatim = [];

        $groups = DB::table('scoreboards')
            ->whereNotNull('kawasan_type')
            ->select('kawasan_type', 'kawasan_id')
            ->groupBy('kawasan_type', 'kawasan_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $g) {
            $boards = DB::table('scoreboards')
                ->where('kawasan_type', $g->kawasan_type)
                ->where('kawasan_id', $g->kawasan_id)
                ->orderByDesc('updated_at')->orderByDesc('id')
                ->get();

            $winner = $boards->shift();
            $keep = $this->imagePaths($winner);

            foreach ($boards as $loser) {
                foreach (array_diff($this->imagePaths($loser), $keep) as $path) {
                    $yatim[] = $path;
                }
                DB::table('scoreboards')->where('id', $loser->id)->delete();
            }
        }

        return array_values(array_unique($yatim));
    }

    /** @return array<int, string> */
    private function imagePaths(object $board): array
    {
        $paths = array_filter([$board->logo_path ?? null]);
        foreach (json_decode((string) ($board->candidates ?? '[]'), true) ?: [] as $c) {
            if (! empty($c['gambar'])) {
                $paths[] = $c['gambar'];
            }
        }

        return array_values(array_unique($paths));
    }

    public function down(): void
    {
        if (! Schema::hasColumn('scoreboards', 'kawasan_type')) {
            return; // Skema baharu belum wujud — tiada apa untuk diterbalikkan.
        }

        $jumlah = DB::table('scoreboards')->count();

        throw new \RuntimeException(
            "Migrasi ini TIDAK BOLEH diterbalikkan (not reversible): terdapat {$jumlah} baris scoreboards. ".
            'Skema lama mengunci papan pada borang14_form_id UNIQUE — papan yang tiada sumber Borang 14 '.
            '(borang14_form_id NULL) tidak boleh diwakili langsung, dan papan yang runtuh semasa up() sudah '.
            'dipadam. SANDARKAN data dahulu dan tangani migrasi data secara manual jika rollback benar-benar '.
            'diperlukan.'
        );
    }
};
