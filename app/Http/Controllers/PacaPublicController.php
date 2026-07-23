<?php

namespace App\Http\Controllers;

use App\Models\PacaPusat;
use App\Models\PacaSaluran;
use App\Models\PacaSlot;
use App\Support\MalaysianIc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Laluan AWAM (tiada log masuk) bagi PACA: pautan per-Pusat (token boleh teka
 * sukar) yang dikongsi penyelaras kepada petugas, supaya petugas boleh
 * mendaftar diri ke dalam slot kosong. Berdiri sendiri di luar kumpulan
 * auth/admin — lihat PacaController untuk sisi pengurusan.
 *
 * PERATURAN PRIVASI (sebab wujudnya pengawal ini): payload awam TIDAK PERNAH
 * membawa petugas_kp atau petugas_tel milik sesiapa — pelawat pautan awam
 * tidak sepatutnya nampak IC/telefon orang lain. Nama petugas turut tidak
 * didedahkan; hanya status terisi + jawatan + masa + parti (untuk pandangan
 * liputan) dipaparkan.
 */
class PacaPublicController extends Controller
{
    /** Halaman awam /paca/{token} — pokok Saluran->slot bagi satu Pusat, tanpa PII pengisi. */
    public function show(string $token)
    {
        $pusat = PacaPusat::with('saluranList.slots')
            ->where('public_token', $token)
            ->firstOrFail();

        return Inertia::render('Public/Paca', [
            'token' => $token,
            // Senarai parti daripada Data Induk > Keahlian Parti untuk dropdown.
            'parti' => \App\Models\KeahlianParti::orderBy('sort_order')->orderBy('nama')
                ->pluck('nama')->map(fn ($n) => trim($n))->filter()->unique()->values(),
            'pusat' => [
                'dm' => $pusat->dm,
                'pusat' => $pusat->pusat,
            ],
            'saluran' => $pusat->saluranList->map(fn (PacaSaluran $s) => [
                'id' => $s->id,
                'label' => $s->label,
                'slot' => $s->slots->map(fn (PacaSlot $sl) => $this->slotAwamPayload($sl))->all(),
            ])->all(),
        ]);
    }

    /**
     * Petugas mendaftar diri ke dalam satu slot kosong. Slot mesti kepunyaan
     * Pusat token ini (id boleh diteka — semakan whereHas menghalang
     * pengisian slot Pusat lain melalui token sendiri) DAN masih kosong.
     * Dibalut DB::transaction dengan lockForUpdate supaya dua petugas yang
     * cuba mengisi slot terbuka yang sama serentak tidak berlanggar senyap.
     */
    public function hantar(Request $request, string $token)
    {
        $pusat = PacaPusat::where('public_token', $token)->firstOrFail();

        $validated = $request->validate([
            'paca_slot_id' => 'required|integer',
            'petugas_nama' => 'required|string|max:255',
            'petugas_kp' => 'required|string|max:20',
            'petugas_tel' => 'required|string|max:30',
            'petugas_parti' => 'nullable|string|max:255',
        ]);

        if (! $this->icSahih($validated['petugas_kp'])) {
            throw ValidationException::withMessages([
                'petugas_kp' => 'Nombor kad pengenalan tidak sah.',
            ]);
        }

        $slot = PacaSlot::whereKey($validated['paca_slot_id'])
            ->whereHas('saluran', fn ($q) => $q->where('paca_pusat_id', $pusat->id))
            ->first();

        if (! $slot) {
            abort(404, 'Slot tidak wujud bagi Pusat ini.');
        }

        DB::transaction(function () use ($validated, $slot) {
            // Kunci baris dan semak semula ia masih kosong di dalam
            // transaksi ini — dua petugas boleh cuba mengisi slot terbuka
            // yang sama serentak antara semakan di atas dan tulisan ini.
            $terkini = PacaSlot::whereKey($slot->id)->lockForUpdate()->first();

            if ($terkini->petugas_nama !== null) {
                throw ValidationException::withMessages([
                    'paca_slot_id' => 'slot ini sudah diisi',
                ]);
            }

            $terkini->update([
                'petugas_nama' => $validated['petugas_nama'],
                'petugas_kp' => $validated['petugas_kp'],
                'petugas_tel' => $validated['petugas_tel'],
                'petugas_parti' => $validated['petugas_parti'] ?? null,
            ]);
        });

        return response()->json(['message' => 'Slot berjaya diisi.']);
    }

    /**
     * IC sah dari segi bentuk (enam-dua-empat berdash ATAU 12 digit) DAN
     * enam digit pertamanya mesti terurai kepada tarikh lahir sebenar.
     */
    private function icSahih(string $kp): bool
    {
        if (! preg_match('/^\d{6}-\d{2}-\d{4}$/', $kp) && ! preg_match('/^\d{12}$/', $kp)) {
            return false;
        }

        return MalaysianIc::birthDate($kp) !== null;
    }

    /**
     * Medan bukan-PII sahaja — disenaraiputihkan secara eksplisit (bukan
     * spread model) supaya penambahan lajur baharu pada PacaSlot pada masa
     * hadapan tidak senyap bocor ke laluan awam ini.
     */
    private function slotAwamPayload(PacaSlot $slot): array
    {
        $terisi = $slot->petugas_nama !== null;

        return [
            'id' => $slot->id,
            'jawatan' => $slot->jawatan,
            'masa' => $this->formatMasa($slot->masa_mula, $slot->masa_tamat),
            'terisi' => $terisi,
            // Parti pengisi untuk pandangan liputan sahaja — nama, no K/P
            // dan no telefon TIDAK PERNAH dihantar ke laluan awam ini.
            'parti' => $terisi ? $slot->petugas_parti : null,
        ];
    }

    /** 'HH:MM - HH:MM', 'HH:MM - selesai' (masa_tamat null = CA), atau null jika masa_mula tiada. */
    private function formatMasa(?string $masaMula, ?string $masaTamat): ?string
    {
        if ($masaMula === null) {
            return null;
        }

        return $masaMula.' - '.($masaTamat ?? 'selesai');
    }
}
