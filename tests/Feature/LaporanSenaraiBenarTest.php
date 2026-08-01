<?php
// tests/Feature/LaporanSenaraiBenarTest.php
//
// Hujung Laporan yang menulis peraturan kerusi DENGAN TANGAN (eksport,
// padam pukal, padam tunggal) dan bukan melalui VoterScopeService.
//
// Dua jaminan dipaku di sini:
//
//   1. `super_user` diskop kepada DUN SENDIRI — bukan disekat. Ia mesti
//      masih boleh mengeksport dan memadam kerusinya sendiri persis seperti
//      hari ini, dan ditolak (atau mendapat sifar baris) bagi kerusi lain.
//      Pembetulan yang senyap-senyap menyekat kerja sebenar mereka sama
//      buruknya dengan lubang itu sendiri.
//
//   2. Gerbangnya ialah SENARAI-BENAR. Peranan yang tidak dikenali — termasuk
//      yang ditambah esok — DITOLAK secara lalai. Ujian peranan-tidak-dikenali
//      itulah sebab kepada penyongsangan ini: tanpanya, orang seterusnya boleh
//      memperkenalkan semula fall-through secara senyap.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanSenaraiBenarTest extends TestCase
{
    use RefreshDatabase;

    private Negeri $negeri;

    private Bandar $parlimen;

    private Kadun $dunSendiri;

    private Kadun $dunJiran;   // Parlimen SAMA, DUN lain

    private Kadun $dunAsing;   // Parlimen lain

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'KUALA PILAH', 'negeri_id' => $this->negeri->id]);
        $lain = Bandar::create(['nama' => 'JEMPOL', 'negeri_id' => $this->negeri->id]);
        $this->dunSendiri = Kadun::create(['nama' => 'PILAH', 'bandar_id' => $this->parlimen->id]);
        $this->dunJiran = Kadun::create(['nama' => 'JOHOL', 'bandar_id' => $this->parlimen->id]);
        $this->dunAsing = Kadun::create(['nama' => 'BAHAU', 'bandar_id' => $lain->id]);
    }

    private function user(string $role, array $over = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name' => "Pengguna {$n}",
            'email' => "senarai{$n}@example.test",
            'telephone' => '01700000'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'password' => bcrypt('rahsia'),
            'role' => $role,
            'status' => 'approved',
            'negeri_id' => $this->negeri->id,
            'bandar_id' => $this->parlimen->id,
            'kadun_id' => $this->dunSendiri->id,
        ], $over));
    }

    private function superUser(): User
    {
        return $this->user('super_user');
    }

    /** Satu rekod dalam DUN sendiri, satu dalam DUN jiran, satu dalam Parlimen asing. */
    private function benih(): array
    {
        return [
            'sendiri' => HasilCulaan::factory()->create([
                'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'KUALA PILAH', 'kadun' => 'PILAH',
            ]),
            'jiran' => HasilCulaan::factory()->create([
                'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'KUALA PILAH', 'kadun' => 'JOHOL',
            ]),
            'asing' => HasilCulaan::factory()->create([
                'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'JEMPOL', 'kadun' => 'BAHAU',
            ]),
        ];
    }

    // ------------------------------------------- super_user: separuh POSITIF

    /**
     * Separuh yang mudah dilupakan. Seorang super_user mesti masih boleh
     * mengeksport kerusinya sendiri — jika pembetulan ini memulangkan sifar
     * baris untuk semua orang, kerja harian mereka pecah secara senyap.
     */
    public function test_super_user_can_still_export_its_own_dun(): void
    {
        $baris = $this->benih();
        Excel::fake();

        $this->actingAs($this->superUser())
            ->get(route('reports.hasil-culaan.export'))->assertOk();

        Excel::assertDownloaded('hasil-culaan-'.date('Y-m-d').'.xlsx', function ($export) use ($baris) {
            $ids = $export->collection()->pluck('id')->all();

            $this->assertContains($baris['sendiri']->id, $ids, 'DUN sendiri hilang daripada eksport.');
            $this->assertNotContains($baris['jiran']->id, $ids, 'DUN jiran bocor.');
            $this->assertNotContains($baris['asing']->id, $ids, 'Parlimen asing bocor.');

            return true;
        });
    }

    public function test_super_user_can_still_export_its_own_data_pengundi(): void
    {
        $sendiri = DataPengundi::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'KUALA PILAH', 'kadun' => 'PILAH',
        ]);
        $asing = DataPengundi::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'JEMPOL', 'kadun' => 'BAHAU',
        ]);

        Excel::fake();

        $this->actingAs($this->superUser())
            ->get(route('reports.data-pengundi.export'))->assertOk();

        // DataPengundiExport ialah FromQuery, bukan FromCollection.
        Excel::assertDownloaded('data-pengundi-'.date('Y-m-d').'.xlsx', function ($export) use ($sendiri, $asing) {
            $ids = $export->query()->pluck('id')->all();

            $this->assertContains($sendiri->id, $ids, 'DUN sendiri hilang daripada eksport.');
            $this->assertNotContains($asing->id, $ids, 'Parlimen asing bocor.');

            return true;
        });
    }

    /** Padam kerusi sendiri mesti kekal berfungsi. */
    public function test_super_user_can_still_delete_records_in_its_own_dun(): void
    {
        $baris = $this->benih();
        $su = $this->superUser();

        $this->actingAs($su)->delete(route('reports.hasil-culaan.destroy', $baris['sendiri']));
        $this->assertNull($baris['sendiri']->fresh(), 'super_user tidak lagi boleh memadam DUN sendiri.');

        $lagi = HasilCulaan::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'KUALA PILAH', 'kadun' => 'PILAH',
        ]);
        $this->actingAs($su)->post(route('reports.hasil-culaan.bulk-delete'), ['ids' => [$lagi->id]]);
        $this->assertNull($lagi->fresh(), 'Padam pukal DUN sendiri tidak lagi berfungsi.');
    }

    /** Rekod yang DIHANTAR sendiri kekal boleh disentuh walau di luar DUN. */
    public function test_super_user_keeps_reach_over_its_own_submissions(): void
    {
        $su = $this->superUser();
        $sendiriDiLuar = HasilCulaan::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'JEMPOL', 'kadun' => 'BAHAU',
            'submitted_by' => $su->id,
        ]);

        $this->actingAs($su)->delete(route('reports.hasil-culaan.destroy', $sendiriDiLuar));

        $this->assertNull($sendiriDiLuar->fresh(), 'Penyerahan sendiri sepatutnya kekal boleh disentuh.');
    }

    // ------------------------------------------- super_user: separuh NEGATIF

    public function test_super_user_can_no_longer_delete_outside_its_dun(): void
    {
        $baris = $this->benih();
        $su = $this->superUser();

        // DUN jiran (Parlimen SAMA) juga di luar skop — skopnya DUN, bukan Parlimen.
        $this->actingAs($su)
            ->delete(route('reports.hasil-culaan.destroy', $baris['jiran']))->assertForbidden();
        $this->actingAs($su)
            ->delete(route('reports.hasil-culaan.destroy', $baris['asing']))->assertForbidden();

        $this->assertNotNull($baris['jiran']->fresh());
        $this->assertNotNull($baris['asing']->fresh());
    }

    public function test_super_user_bulk_delete_cannot_reach_outside_its_dun(): void
    {
        $baris = $this->benih();

        $this->actingAs($this->superUser())->post(route('reports.hasil-culaan.bulk-delete'), [
            'ids' => [$baris['jiran']->id, $baris['asing']->id],
        ]);

        $this->assertNotNull($baris['jiran']->fresh(), 'DUN jiran dipadam.');
        $this->assertNotNull($baris['asing']->fresh(), 'Parlimen asing dipadam.');
    }

    /**
     * Padam pukal bercampur: hanya baris dalam skop yang lenyap, selebihnya
     * kekal. Ini bentuk serangan yang paling mungkin — sisipkan satu id sah
     * bersama sekumpulan id asing.
     */
    public function test_super_user_bulk_delete_removes_only_the_in_scope_rows(): void
    {
        $baris = $this->benih();

        $this->actingAs($this->superUser())->post(route('reports.hasil-culaan.bulk-delete'), [
            'ids' => [$baris['sendiri']->id, $baris['jiran']->id, $baris['asing']->id],
        ]);

        $this->assertNull($baris['sendiri']->fresh(), 'Baris dalam skop sepatutnya dipadam.');
        $this->assertNotNull($baris['jiran']->fresh(), 'Baris luar skop dipadam.');
        $this->assertNotNull($baris['asing']->fresh(), 'Baris luar skop dipadam.');
    }

    // ------------------------------------------------------ sunting (edit)

    /** Muatan `update` yang lengkap, dibina daripada rekod itu sendiri. */
    private function muatanKemasKini(HasilCulaan $r, array $ubah = []): array
    {
        return array_merge([
            'nama' => $r->nama,
            'no_ic' => $r->no_ic,
            'umur' => $r->umur,
            'no_tel' => $r->no_tel,
            'bangsa' => $r->bangsa,
            'alamat' => $r->alamat,
            'poskod' => $r->poskod,
            'negeri' => $r->negeri,
            'bandar' => $r->bandar,
            'parlimen' => $r->parlimen,
            'kadun' => $r->kadun,
            'bil_isi_rumah' => 3,
            'pekerjaan' => 'Swasta',
            'jenis_pekerjaan' => ['Pentadbiran'],
            'pemilik_rumah' => 'Sendiri',
            'jenis_sumbangan' => ['Tunai'],
            'tujuan_sumbangan' => ['Kecemasan'],
            'bantuan_lain' => ['Tiada'],
            'keahlian_parti' => 'Tiada',
            'kecenderungan_politik' => 'Putih',
        ], $ubah);
    }

    /**
     * Laluan sunting HIDUP ialah hasilCulaanUpdate()/dataPengundiUpdate(),
     * yang menyemak `isUser()` SAHAJA. Pembantu canModify* dalam pengawal ini
     * kelihatan seperti peraturan itu tetapi peribadi dan TIDAK PERNAH
     * dipanggil — kod mati. Jadi sunting `super_user` adalah KEBANGSAAN.
     */
    public function test_super_user_can_no_longer_edit_outside_its_dun(): void
    {
        $baris = $this->benih();
        $su = $this->superUser();

        foreach (['jiran', 'asing'] as $label) {
            $this->actingAs($su)
                ->put(route('reports.hasil-culaan.update', $baris[$label]),
                    $this->muatanKemasKini($baris[$label], ['nama' => 'DIUBAH']))
                ->assertForbidden();

            $this->assertNotSame('DIUBAH', $baris[$label]->fresh()->nama, "Rekod {$label} diubah.");
        }
    }

    /** Halaman sunting itu sendiri mendedahkan PII penuh — ia mesti diskop juga. */
    public function test_super_user_cannot_open_the_edit_page_of_a_foreign_record(): void
    {
        $baris = $this->benih();
        $su = $this->superUser();

        $this->actingAs($su)
            ->get(route('reports.hasil-culaan.edit', $baris['asing']))->assertForbidden();
        $this->actingAs($su)
            ->get(route('reports.data-pengundi.edit', DataPengundi::factory()->create([
                'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'JEMPOL', 'kadun' => 'BAHAU',
            ])))->assertForbidden();
    }

    public function test_super_user_can_still_edit_records_in_its_own_dun(): void
    {
        $baris = $this->benih();
        $su = $this->superUser();

        $this->actingAs($su)
            ->get(route('reports.hasil-culaan.edit', $baris['sendiri']))->assertOk();

        $this->actingAs($su)
            ->put(route('reports.hasil-culaan.update', $baris['sendiri']),
                $this->muatanKemasKini($baris['sendiri'], ['nama' => 'NAMA BAHARU']))
            ->assertRedirect();

        $this->assertSame('NAMA BAHARU', $baris['sendiri']->fresh()->nama,
            'super_user tidak lagi boleh menyunting DUN sendiri.');
    }

    /**
     * Toggle "kematian" ialah mutasi data pengundi dan langsung tiada semakan
     * kerusi — ditemui semasa menyapu keseluruhan pengawal, bukan dilaporkan.
     */
    public function test_super_user_cannot_toggle_deceased_outside_its_dun(): void
    {
        $baris = $this->benih();
        $su = $this->superUser();

        $this->actingAs($su)
            ->post(route('reports.hasil-culaan.toggle-deceased', $baris['asing']))
            ->assertForbidden();

        $this->assertFalse((bool) $baris['asing']->fresh()->is_deceased);

        // Kerusi sendiri kekal berfungsi.
        $this->actingAs($su)
            ->post(route('reports.hasil-culaan.toggle-deceased', $baris['sendiri']))
            ->assertOk();
    }

    // ------------------------------------------------ lalai SENARAI-BENAR

    /**
     * INTI PENYONGSANGAN ITU. Peranan yang tidak dikenali mesti ditolak pada
     * SETIAP hujung ini. Tanpa ujian ini, peranan ketujuh boleh sekali lagi
     * jatuh melalui ke eksport dan padam kebangsaan secara senyap — persis
     * seperti yang berlaku kepada `pengarah_dun`.
     */
    public function test_an_unrecognised_role_is_refused_everywhere(): void
    {
        $baris = $this->benih();
        // Peranan yang tidak wujud dalam mana-mana cabang — mensimulasikan
        // peranan yang ditambah esok tanpa mengemas kini gerbang ini.
        $asing = $this->user('penyelaras_negeri');

        foreach ([
            'reports.hasil-culaan.export',
            'reports.data-pengundi.export',
        ] as $nama) {
            $this->actingAs($asing)->get(route($nama))->assertForbidden();
        }

        $this->actingAs($asing)
            ->delete(route('reports.hasil-culaan.destroy', $baris['sendiri']))->assertForbidden();
        $this->actingAs($asing)
            ->delete(route('reports.data-pengundi.destroy', DataPengundi::factory()->create()))->assertForbidden();

        $this->actingAs($asing)->post(route('reports.hasil-culaan.bulk-delete'), [
            'ids' => [$baris['sendiri']->id],
        ])->assertForbidden();
        $this->actingAs($asing)->post(route('reports.data-pengundi.bulk-delete'), [
            'ids' => [DataPengundi::factory()->create()->id],
        ])->assertForbidden();

        $this->actingAs($asing)->post(route('reports.hasil-culaan.store'), [
            'nama' => 'PENYUSUP', 'no_ic' => '900101015555', 'parlimen' => 'KUALA PILAH',
        ])->assertForbidden();

        // Tiada apa-apa yang lenyap.
        foreach ($baris as $rekod) {
            $this->assertNotNull($rekod->fresh());
        }
    }

    /**
     * AKIBAT PALING TAJAM. Halaman sunting ialah laluan BACA yang memaparkan
     * no_ic, no_tel dan alamat penuh. Ia bukan sebahagian daripada
     * senarai-benar, jadi jaminan "yang tidak dikenali ditolak" adalah benar
     * bagi 13 hujung tulis/eksport tetapi PALSU bagi Laporan seluruhnya.
     */
    public function test_an_unrecognised_role_cannot_read_a_record_edit_page(): void
    {
        $baris = $this->benih();
        $dp = DataPengundi::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'JEMPOL', 'kadun' => 'BAHAU',
        ]);
        $asing = $this->user('penyelaras_negeri');

        $this->actingAs($asing)
            ->get(route('reports.hasil-culaan.edit', $baris['asing']))->assertForbidden();
        $this->actingAs($asing)
            ->get(route('reports.data-pengundi.edit', $dp))->assertForbidden();

        // Juga laluan baca berasaskan IC dan mutasi kematian.
        $this->actingAs($asing)
            ->getJson(route('api.hasil-culaan.by-ic', ['ic' => $baris['asing']->no_ic]))
            ->assertForbidden();
        $this->actingAs($asing)
            ->post(route('reports.hasil-culaan.toggle-deceased', $baris['asing']))
            ->assertForbidden();

        $this->assertFalse((bool) $baris['asing']->fresh()->is_deceased);
    }

    /** Kemas kini juga mesti ditolak bagi peranan yang tidak dikenali. */
    public function test_an_unrecognised_role_cannot_update_a_record(): void
    {
        $baris = $this->benih();

        $this->actingAs($this->user('penyelaras_negeri'))
            ->put(route('reports.hasil-culaan.update', $baris['sendiri']),
                $this->muatanKemasKini($baris['sendiri'], ['nama' => 'DIUBAH']))
            ->assertForbidden();

        $this->assertNotSame('DIUBAH', $baris['sendiri']->fresh()->nama);
    }

    // ----------------------------------------- peranan lain tidak bergerak

    public function test_admin_and_super_admin_are_unchanged(): void
    {
        $baris = $this->benih();
        Excel::fake();

        // admin: eksport masih terhad kepada Parlimennya — DUN jiran TERMASUK
        // (skopnya Parlimen), Parlimen asing tidak.
        $this->actingAs($this->user('admin'))
            ->get(route('reports.hasil-culaan.export'))->assertOk();

        Excel::assertDownloaded('hasil-culaan-'.date('Y-m-d').'.xlsx', function ($export) use ($baris) {
            $ids = $export->collection()->pluck('id')->all();

            $this->assertContains($baris['sendiri']->id, $ids);
            $this->assertContains($baris['jiran']->id, $ids, 'Skop admin menyempit kepada DUN — regresi.');
            $this->assertNotContains($baris['asing']->id, $ids);

            return true;
        });

        // admin: padam tunggal masih terbuka kepada mana-mana rekod (sedia ada).
        $this->actingAs($this->user('admin'))
            ->delete(route('reports.hasil-culaan.destroy', $baris['asing']));
        $this->assertNull($baris['asing']->fresh(), 'Skop padam admin berubah — regresi.');

        // super_admin: tiada had.
        Excel::fake();
        $this->actingAs($this->user('super_admin', ['bandar_id' => null, 'kadun_id' => null]))
            ->get(route('reports.hasil-culaan.export'))->assertOk();

        Excel::assertDownloaded('hasil-culaan-'.date('Y-m-d').'.xlsx', function ($export) use ($baris) {
            $ids = $export->collection()->pluck('id')->all();

            $this->assertContains($baris['sendiri']->id, $ids);
            $this->assertContains($baris['jiran']->id, $ids);

            return true;
        });
    }

    public function test_plain_user_is_unchanged(): void
    {
        $baris = $this->benih();
        $biasa = $this->user('user');

        $this->actingAs($biasa)->get(route('reports.hasil-culaan.export'))->assertForbidden();
        $this->actingAs($biasa)->get(route('reports.data-pengundi.export'))->assertForbidden();
        $this->actingAs($biasa)
            ->delete(route('reports.hasil-culaan.destroy', $baris['sendiri']))->assertForbidden();
        $this->actingAs($biasa)->post(route('reports.hasil-culaan.bulk-delete'), [
            'ids' => [$baris['sendiri']->id],
        ])->assertForbidden();

        $this->assertNotNull($baris['sendiri']->fresh());
    }

    /** Diperluas: eksport, padam tunggal, padam pukal, sunting DAN halaman sunting. */
    public function test_confined_roles_are_still_refused(): void
    {
        foreach (['pengarah_dun', 'ketua_paca_dun'] as $role) {
            $baris = $this->benih();
            $dp = DataPengundi::factory()->create([
                'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'KUALA PILAH', 'kadun' => 'PILAH',
            ]);
            $u = $this->user($role);

            $this->actingAs($u)->get(route('reports.hasil-culaan.export'))->assertForbidden();
            $this->actingAs($u)->get(route('reports.data-pengundi.export'))->assertForbidden();

            $this->actingAs($u)
                ->delete(route('reports.hasil-culaan.destroy', $baris['sendiri']))->assertForbidden();
            $this->actingAs($u)
                ->delete(route('reports.data-pengundi.destroy', $dp))->assertForbidden();

            $this->actingAs($u)->post(route('reports.hasil-culaan.bulk-delete'), [
                'ids' => [$baris['sendiri']->id],
            ])->assertForbidden();
            $this->actingAs($u)->post(route('reports.data-pengundi.bulk-delete'), [
                'ids' => [$dp->id],
            ])->assertForbidden();

            $this->actingAs($u)
                ->get(route('reports.hasil-culaan.edit', $baris['sendiri']))->assertForbidden();
            $this->actingAs($u)
                ->get(route('reports.data-pengundi.edit', $dp))->assertForbidden();

            $this->actingAs($u)
                ->put(route('reports.hasil-culaan.update', $baris['sendiri']),
                    $this->muatanKemasKini($baris['sendiri'], ['nama' => 'DIUBAH']))
                ->assertForbidden();

            // Tiada baris lenyap atau berubah.
            $this->assertNotNull($baris['sendiri']->fresh(), $role);
            $this->assertNotNull($dp->fresh(), $role);
            $this->assertNotSame('DIUBAH', $baris['sendiri']->fresh()->nama, $role);
        }
    }
}
