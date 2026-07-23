<?php

namespace App\Services\Pilihanraya;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\PacaForm;
use App\Models\PacaPusat;
use App\Models\PacaSaluran;
use App\Models\PacaSlot;
use App\Services\Borang14StrukturService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Jambatan antara scoresheet Borang 14 (sumber struktur) dan roster PACABA
 * (Pusat Mengundi -> Saluran -> slot syif). Satu PacaForm hanya boleh disemai
 * SEKALI daripada satu Borang14Form — suntingan seterusnya (nama ketua,
 * petugas, dsb.) tidak pernah ditimpa oleh semaian berulang.
 */
class PacaBuilderService
{
    public function __construct(
        private readonly Borang14StrukturService $strukturService,
        private readonly PacaSlotPlanner $slotPlanner,
    ) {
    }

    /**
     * Senarai kerusi yang mempunyai scoresheet (Borang14Form dengan
     * structure['rows'] tidak kosong) — sumber untuk pemilih admin PACA.
     * TIDAK guna JSON_LENGTH (MySQL-sahaja, gagal di SQLite CI); tapis dalam
     * PHP sebaliknya.
     *
     * $bandarId, jika diberi, melingkupkan senarai kepada kerusi Bandar itu
     * sahaja (admin biasa); null (lalai) pulangkan semua kerusi (super_admin).
     * Bandar kerusi diselesaikan dengan cara SAMA seperti
     * PacaController::assertBolehAkses() — parlimen: kawasan_id ITU bandar;
     * dun: bandar_id Kadun berkenaan.
     *
     * @return array<int, array{kawasan_type:string, kawasan_id:int, jenis_pr:string, tahun:int, nama:string, negeri:string, parlimen:string, dun:?string, has_paca:bool}>
     */
    public function seatsWithScoresheet(?int $bandarId = null): array
    {
        $existingPaca = PacaForm::query()
            ->get(['kawasan_type', 'kawasan_id', 'jenis_pr', 'tahun'])
            ->map(fn ($p) => $p->kawasan_type.'|'.$p->kawasan_id.'|'.$p->jenis_pr.'|'.$p->tahun)
            ->flip();

        $seats = [];

        foreach (Borang14Form::all() as $f) {
            if (empty($f->structure['rows'] ?? null)) {
                continue;
            }

            $isParlimen = $f->kawasan_type === Borang14Form::KAWASAN_PARLIMEN;
            $kawasan = $f->kawasan();
            $bandar = $isParlimen ? $kawasan : $kawasan?->bandar;

            if ($bandarId !== null && (int) $bandar?->id !== $bandarId) {
                continue;
            }

            $kunci = $f->kawasan_type.'|'.$f->kawasan_id.'|'.$f->jenis_pr.'|'.$f->tahun;

            $seats[] = [
                'kawasan_type' => $f->kawasan_type,
                'kawasan_id' => (int) $f->kawasan_id,
                'jenis_pr' => $f->jenis_pr,
                'tahun' => (int) $f->tahun,
                'nama' => $kawasan?->nama ?? '—',
                'negeri' => $bandar?->negeri?->nama ?? '—',
                'parlimen' => $bandar?->nama ?? '—',
                'dun' => $isParlimen ? null : ($kawasan?->nama ?? '—'),
                'has_paca' => $existingPaca->has($kunci),
            ];
        }

        return $seats;
    }

    /**
     * Cari-atau-cipta PacaForm bagi kerusi/PR Borang14Form ini. Untuk PacaForm
     * BAHARU sahaja, semai Pusat/Saluran/slot daripada
     * Borang14StrukturService::collapse($form->structure). PacaForm sedia ada
     * dipulangkan TANPA disentuh — suntingan manual (nama ketua, petugas)
     * tidak pernah ditimpa.
     */
    public function buildFrom(Borang14Form $form): PacaForm
    {
        // firstOrCreate DAN semai() dalam SATU transaksi. Jika penyemaian
        // gagal separuh jalan (cth nama pusat melebihi 255 aksara di bawah
        // MySQL strict), PacaForm kosong itu TIDAK boleh kekal — jika tidak
        // wasRecentlyCreated menjadi false selama-lamanya dan kerusi itu jadi
        // roster kosong yang tidak boleh disemai semula.
        return DB::transaction(function () use ($form) {
            $paca = PacaForm::firstOrCreate(
                [
                    'kawasan_type' => $form->kawasan_type,
                    'kawasan_id' => $form->kawasan_id,
                    'jenis_pr' => $form->jenis_pr,
                    'tahun' => $form->tahun,
                ],
                [
                    'borang14_form_id' => $form->id,
                    'created_by' => auth()->id(),
                ],
            );

            if ($paca->wasRecentlyCreated) {
                $this->semai($paca, $form);
            }

            return $paca;
        });
    }

    /**
     * Semaian sebenar — dipanggil sekali sahaja, di dalam transaksi, hanya
     * untuk PacaForm yang baru dicipta.
     */
    private function semai(PacaForm $paca, Borang14Form $form): void
    {
        $collapsed = $this->strukturService->collapse($form->structure);

        $urutanPusat = 0;
        foreach ($collapsed['pusat'] as $p) {
            // Sentinel UNDI POS/AWAL (pusat === '') bukan pusat mengundi
            // sebenar — collapse() sudah tidak memasukkannya ke sini, tapi
            // kekal semak sebagai pertahanan berlapis.
            if (($p['pusat'] ?? '') === '') {
                continue;
            }

            $pusat = PacaPusat::create([
                'paca_form_id' => $paca->id,
                'dm' => $p['dm'] ?? '',
                'pusat' => $p['pusat'],
                'public_token' => Str::random(32),
                'urutan' => $urutanPusat++,
            ]);

            $count = max(1, (int) ($p['saluran_count'] ?? 1));
            $labels = array_values($p['saluran_labels'] ?? []);

            for ($i = 1; $i <= $count; $i++) {
                $label = array_key_exists($i - 1, $labels) ? (string) $labels[$i - 1] : (string) $i;

                $saluran = PacaSaluran::create([
                    'paca_pusat_id' => $pusat->id,
                    'label' => $label,
                    'urutan' => $i,
                ]);

                foreach ($this->slotPlanner->defaultSlots() as $slot) {
                    PacaSlot::create([
                        'paca_saluran_id' => $saluran->id,
                        'jawatan' => $slot['jawatan'],
                        'masa_mula' => $slot['masa_mula'],
                        'masa_tamat' => $slot['masa_tamat'],
                        'urutan' => $slot['urutan'],
                    ]);
                }
            }
        }
    }
}
