<?php

namespace App\Services\Pilihanraya;

/**
 * Logik tulen untuk slot syif PACA (PA1, PA2, PA3, CA) bagi satu saluran:
 * penjanaan slot lalai, pelabelan semula jawatan mengikut urutan, dan
 * penguatkuasaan tempoh minimum 2 jam. Tiada capaian DB/HTTP di sini.
 */
class PacaSlotPlanner
{
    /**
     * Panjang setiap blok masa lalai, dalam minit (2 jam).
     */
    private const BLOCK_MINIT = 120;

    /**
     * Jana senarai slot lalai bagi satu saluran, bermula 08:00 dalam blok 2 jam.
     * Slot terakhir (CA) tiada masa_tamat — bermaksud "selesai".
     *
     * @return array<int, array{jawatan: string, masa_mula: ?string, masa_tamat: ?string, urutan: int}>
     */
    public function defaultSlots(int $count = 4): array
    {
        $slots = [];
        $mulaMinit = 8 * 60; // 08:00

        for ($i = 0; $i < $count; $i++) {
            $slots[] = [
                'jawatan' => 'PA' . ($i + 1), // dilabel semula di bawah
                'masa_mula' => $this->minitKeMasa($mulaMinit + $i * self::BLOCK_MINIT),
                'masa_tamat' => $this->minitKeMasa($mulaMinit + ($i + 1) * self::BLOCK_MINIT),
                'urutan' => $i + 1,
            ];
        }

        $slots = $this->relabel($slots);

        // Slot terakhir (CA) sentiasa "selesai" — tiada masa_tamat.
        if (!empty($slots)) {
            $slots[count($slots) - 1]['masa_tamat'] = null;
        }

        return $slots;
    }

    /**
     * Labelkan semula jawatan mengikut urutan (urutan menentukan jawatan,
     * bukan sebaliknya): slot terakhir sentiasa 'CA', selebihnya 'PA1'..'PAn'.
     * Masa dan petugas yang sedia ada dikekalkan.
     *
     * @param array<int, array<string, mixed>> $slots
     * @return array<int, array<string, mixed>>
     */
    public function relabel(array $slots): array
    {
        usort($slots, fn ($a, $b) => ($a['urutan'] ?? 0) <=> ($b['urutan'] ?? 0));

        $jumlah = count($slots);

        foreach ($slots as $i => &$slot) {
            $slot['jawatan'] = ($i === $jumlah - 1) ? 'CA' : 'PA' . ($i + 1);
        }
        unset($slot);

        return array_values($slots);
    }

    /**
     * Tempoh minimum 2 jam dikuatkuasakan hanya untuk slot BUKAN CA yang
     * mempunyai masa_mula DAN masa_tamat, dan tempohnya kurang 120 minit.
     * Slot CA (tiada masa_tamat) dikecualikan; slot PA yang belum diisi
     * masa_tamat (masih kosong) bukan satu pelanggaran.
     */
    public function minimumMet(array $slot): bool
    {
        $jawatan = $slot['jawatan'] ?? null;
        $masaMula = $slot['masa_mula'] ?? null;
        $masaTamat = $slot['masa_tamat'] ?? null;

        if ($jawatan === 'CA') {
            return true;
        }

        if ($masaMula === null || $masaTamat === null) {
            return true;
        }

        $tempoh = $this->masaKeMinit($masaTamat) - $this->masaKeMinit($masaMula);

        return $tempoh >= self::BLOCK_MINIT;
    }

    /**
     * Tukar bilangan minit sejak tengah malam kepada rentetan 'HH:MM'.
     */
    private function minitKeMasa(int $minit): string
    {
        $jam = intdiv($minit, 60);
        $baki = $minit % 60;

        return sprintf('%02d:%02d', $jam, $baki);
    }

    /**
     * Tukar rentetan 'HH:MM' kepada bilangan minit sejak tengah malam.
     */
    private function masaKeMinit(string $masa): int
    {
        return ((int) substr($masa, 0, 2)) * 60 + ((int) substr($masa, 3, 2));
    }
}
