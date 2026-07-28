<?php

namespace App\Http\Controllers;

use App\Models\PacaForm;
use App\Models\PacaPusat;
use App\Models\PacaSaluran;
use App\Models\PacaSlot;
use App\Services\Pilihanraya\PacaSlotPlanner;
use App\Support\MalaysianIc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Laluan AWAM (tiada log masuk) bagi PACA: pautan per-KERUSI (DUN) — token
 * boleh teka sukar pada paca_forms — yang dikongsi penyelaras kepada petugas,
 * supaya petugas boleh mendaftar diri ke dalam slot kosong mana-mana Pusat
 * Mengundi kerusi itu. Berdiri sendiri di luar kumpulan auth/admin — lihat
 * PacaController untuk sisi pengurusan.
 *
 * PERATURAN PRIVASI (sebab wujudnya pengawal ini): payload awam TIDAK PERNAH
 * membawa petugas_kp atau petugas_tel milik sesiapa — pelawat pautan awam
 * tidak sepatutnya nampak IC/telefon orang lain. Nama petugas turut tidak
 * didedahkan; hanya status terisi + jawatan + masa + parti (untuk pandangan
 * liputan) dipaparkan.
 */
class PacaPublicController extends Controller
{
    public function __construct(
        private readonly PacaSlotPlanner $planner,
    ) {
    }

    /** Halaman awam /paca/{token} — SEMUA Pusat->Saluran->slot bagi satu kerusi, tanpa PII pengisi. */
    public function show(string $token)
    {
        $form = PacaForm::with('pusatList.saluranList.slots')
            ->where('public_token', $token)
            ->firstOrFail();

        return Inertia::render('Public/Paca', [
            'token' => $token,
            'kerusi' => $this->namaKerusi($form),
            // Senarai parti daripada Data Induk > Keahlian Parti untuk dropdown.
            'parti' => \App\Models\KeahlianParti::orderBy('sort_order')->orderBy('nama')
                ->pluck('nama')->map(fn ($n) => trim($n))->filter()->unique()->values(),
            'pusat' => $form->pusatList->map(fn (PacaPusat $p) => [
                'id' => $p->id,
                'dm' => $p->dm,
                'pusat' => $p->pusat,
                'saluran' => $p->saluranList->map(fn (PacaSaluran $s) => [
                    'id' => $s->id,
                    'label' => $s->label,
                    'slot' => $s->slots->values()
                        ->map(fn (PacaSlot $sl, int $i) => $this->slotAwamPayload($sl, $i + 1))->all(),
                ])->all(),
            ])->all(),
        ]);
    }

    /** Nama kerusi untuk pengepala awam — 'DUN Juasseh' / 'Parlimen Segamat', atau null. */
    private function namaKerusi(PacaForm $form): ?string
    {
        $isParlimen = $form->kawasan_type === 'parlimen';
        $nama = $isParlimen
            ? \App\Models\Bandar::whereKey($form->kawasan_id)->value('nama')
            : \App\Models\Kadun::whereKey($form->kawasan_id)->value('nama');

        return $nama ? (($isParlimen ? 'Parlimen ' : 'DUN ').$nama) : null;
    }

    /**
     * Petugas mendaftar diri ke dalam satu slot — kosong ATAU terisi (kemas
     * kini/tulis ganti dibenarkan supaya petugas boleh membetulkan butiran
     * sendiri). Slot mesti kepunyaan MANA-MANA Pusat kerusi token ini (id
     * boleh diteka — semakan whereHas menghalang pengisian slot kerusi LAIN
     * melalui token sendiri). Dibalut DB::transaction dengan lockForUpdate
     * supaya tulisan serentak ke slot yang sama tidak berlanggar senyap.
     */
    public function hantar(Request $request, string $token)
    {
        $form = PacaForm::where('public_token', $token)->firstOrFail();

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
            ->whereHas('saluran.pusat', fn ($q) => $q->where('paca_form_id', $form->id))
            ->first();

        if (! $slot) {
            abort(404, 'Slot tidak wujud bagi kerusi ini.');
        }

        DB::transaction(function () use ($validated, $slot) {
            // Kunci baris untuk menyerikan tulisan serentak. Slot yang SUDAH
            // diisi BOLEH dikemas kini (ditulis ganti) — petugas membetulkan
            // butiran sendiri melalui pautan awam. Nama/KP/Tel kekal WAJIB
            // (lihat validate di atas), jadi kemaskini hanya MENGGANTIKAN
            // dengan pendaftaran sah lain, bukan mengosongkan slot.
            $terkini = PacaSlot::whereKey($slot->id)->lockForUpdate()->first();

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
    private function slotAwamPayload(PacaSlot $slot, int $kedudukan): array
    {
        $terisi = $slot->petugas_nama !== null;

        return [
            'id' => $slot->id,
            'jawatan' => $slot->jawatan,
            'jawatan_papar' => $this->planner->labelPapar($slot->jawatan, $kedudukan),
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
