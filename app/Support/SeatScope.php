<?php

namespace App\Support;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya tempat peraturan kebenaran kerusi ditulis.
 *
 * Peranan → kerusi yang boleh disentuh:
 *   super_admin           semua Parlimen + semua DUN
 *   admin                 Parlimen sendiri + setiap DUN di bawahnya
 *   pengarah_dun          sama seperti admin (Parlimen sendiri + DUN di bawahnya)
 *   super_user / user     DUN sendiri sahaja
 *   ketua_paca_dun        tiada (peranannya satu menu: PACA)
 *
 * Tidak khusus kepada Scoreboard — Keanggotaan/Borang 14/PACA boleh
 * menerimanya kemudian, tetapi ITU DI LUAR SKOP kerja semasa.
 *
 * allows() dan seats() SENGAJA diterbitkan daripada tangga peranan yang sama.
 * Jika keduanya bercanggah, kerusi yang tidak muncul dalam pemilih boleh
 * ditulis dengan membina permintaan sendiri — kelas IDOR yang dihotfix pada
 * Julai 2026. SeatScopeTest memaku invarian itu.
 */
class SeatScope
{
    public const DUN = 'dun';

    public const PARLIMEN = 'parlimen';

    public static function allows(?User $user, string $type, int $id): bool
    {
        if (! $user || ! $user->isApproved()) {
            return false;
        }
        if (! in_array($type, [self::DUN, self::PARLIMEN], true)) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($user->isKetuaPacaDun()) {
            return false;
        }

        // Pengarah DUN dilayan SERUPA admin: Parlimen pada bandar_id, dan
        // setiap DUN di bawahnya. kadun_id (jika ada) TIDAK menyempitkannya.
        if ($user->isAdmin() || $user->isPengarahDun()) {
            // Nullable lajur: seorang admin/pengarah tanpa bandar_id mesti
            // TIDAK padan dengan apa-apa, bukan padan-semua.
            if (! $user->bandar_id) {
                return false;
            }

            return $type === self::PARLIMEN
                ? (int) $user->bandar_id === $id
                : Kadun::whereKey($id)->where('bandar_id', $user->bandar_id)->exists();
        }

        if ($user->isSuperUser() || $user->isUser()) {
            return $type === self::DUN
                && $user->kadun_id
                && (int) $user->kadun_id === $id;
        }

        return false; // Peranan tidak dikenali — tolak.
    }

    public static function assert(?User $user, string $type, int $id): void
    {
        abort_unless(self::allows($user, $type, $id), 403, 'Tindakan tidak dibenarkan.');
    }

    /**
     * Tegaskan kerusi HANYA bagi peranan yang dikurung kepada satu Parlimen.
     *
     * Berbeza daripada assert(), yang mengikat SETIAP peranan. Digunakan pada
     * hujung agregat Pilihanraya yang hari ini terbuka kepada `admin` untuk
     * mana-mana kerusi: mengetatkannya bagi admin ialah keputusan produk yang
     * belum dibuat, jadi peranan selain yang dikurung melaluinya tanpa
     * sebarang perubahan.
     */
    public static function assertJikaTerkurung(?User $user, string $type, int $id): void
    {
        if (self::parlimenKurungan($user) === null) {
            return;
        }

        self::assert($user, $type, $id);
    }

    /**
     * Parlimen yang MENGURUNG paparan agregat Pilihanraya (War Room,
     * Analisa, Simulasi, Kaum Mengikut DM, Minima), atau null jika peranan
     * itu tidak dikurung.
     *
     * Halaman-halaman itu mengagregat mengikut NAMA kawasan dan bukan
     * mengikut id kerusi, jadi allows()/seats() sahaja tidak dapat
     * menjaganya — ia memerlukan penapis yang dipaksa. Ini tempat SATU-SATUNYA
     * peraturan itu ditulis, sama seperti allows()/seats().
     *
     * SENGAJA `pengarah_dun` sahaja. Seorang `admin` melihat agregat
     * KEBANGSAAN di War Room hari ini; mengubahnya ialah keputusan produk
     * yang belum dibuat oleh pemilik sistem dan berada di luar skop peranan
     * baharu ini.
     *
     * Gagal-tutup: seorang pengarah yang belum diluluskan atau tanpa
     * bandar_id ditolak 403 di sini dan bukan dilayan sebagai "tiada had" —
     * itu pengajaran IDOR Julai 2026 (pengawal yang berpaut pada lajur
     * boleh-null).
     */
    public static function parlimenKurungan(?User $user): ?Bandar
    {
        if (! $user || ! $user->isPengarahDun()) {
            return null;
        }

        abort_unless($user->isApproved(), 403, 'Akaun anda belum diluluskan.');

        $bandar = $user->bandar_id ? Bandar::find($user->bandar_id) : null;

        abort_unless($bandar, 403, 'Akaun anda tiada Parlimen — hubungi Admin.');

        return $bandar;
    }

    /** @return array<int, array{type: string, id: int, nama: string, kod: ?string}> */
    public static function seats(?User $user): array
    {
        if (! $user || ! $user->isApproved() || $user->isKetuaPacaDun()) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return array_merge(
                self::duns(Kadun::query()),
                self::parlimens(Bandar::query()),
            );
        }

        // Sengaja cabang yang SAMA seperti allows() di atas — jika keduanya
        // bercanggah, kerusi yang tidak muncul dalam pemilih boleh ditulis
        // dengan membina permintaan sendiri.
        if ($user->isAdmin() || $user->isPengarahDun()) {
            if (! $user->bandar_id) {
                return [];
            }

            return array_merge(
                self::duns(Kadun::where('bandar_id', $user->bandar_id)),
                self::parlimens(Bandar::whereKey($user->bandar_id)),
            );
        }

        if ($user->isSuperUser() || $user->isUser()) {
            if (! $user->kadun_id) {
                return [];
            }

            return self::duns(Kadun::whereKey($user->kadun_id));
        }

        return [];
    }

    /**
     * Setiap kerusi membawa konteks geografinya sendiri (negeri_id/negeri,
     * bandar_id/parlimen) supaya pemilih antara muka boleh melata
     * Negeri > Parlimen > DUN TANPA menyoal jadual induk secara berasingan.
     *
     * Itu bukan sekadar kemudahan: senarai lata MESTI dibina daripada kerusi
     * yang DIBENARKAN sahaja. Membinanya daripada data induk penuh akan
     * menyenaraikan kerusi yang pengguna tidak berhak sentuh.
     *
     * @return array<int, array{type: string, id: int, nama: string, kod: ?string, negeri_id: ?int, negeri: ?string, bandar_id: ?int, parlimen: ?string}>
     */
    private static function duns(Builder $q): array
    {
        return $q->with('bandar.negeri')->orderBy('nama')->get(['id', 'nama', 'kod_dun', 'bandar_id'])
            ->map(fn ($k) => [
                'type' => self::DUN,
                'id' => (int) $k->id,
                'nama' => (string) $k->nama,
                'kod' => $k->kod_dun ? strtoupper($k->kod_dun) : null,
                'negeri_id' => $k->bandar?->negeri_id !== null ? (int) $k->bandar->negeri_id : null,
                'negeri' => $k->bandar?->negeri?->nama,
                'bandar_id' => $k->bandar_id !== null ? (int) $k->bandar_id : null,
                'parlimen' => $k->bandar?->nama,
            ])->all();
    }

    /**
     * Kerusi Parlimen ialah induknya sendiri — bandar_id/parlimen menunjuk
     * kepada dirinya, supaya bentuknya seragam dengan kerusi DUN dan pemilih
     * lata boleh melayan kedua-duanya dengan kod yang sama.
     *
     * @return array<int, array{type: string, id: int, nama: string, kod: ?string, negeri_id: ?int, negeri: ?string, bandar_id: ?int, parlimen: ?string}>
     */
    private static function parlimens(Builder $q): array
    {
        return $q->with('negeri')->orderBy('nama')->get(['id', 'nama', 'kod_parlimen', 'negeri_id'])
            ->map(fn ($b) => [
                'type' => self::PARLIMEN,
                'id' => (int) $b->id,
                'nama' => (string) $b->nama,
                'kod' => $b->kod_parlimen ? strtoupper($b->kod_parlimen) : null,
                'negeri_id' => $b->negeri_id !== null ? (int) $b->negeri_id : null,
                'negeri' => $b->negeri?->nama,
                'bandar_id' => (int) $b->id,
                'parlimen' => (string) $b->nama,
            ])->all();
    }
}
