<?php

namespace App\Services;

class VoterColorService
{
    /**
     * Determine voter color based on political affiliation and tendency.
     * Putih (White) = PH-aligned
     * Hitam (Black) = Other parties
     * Kelabu (Grey) = Undecided/No party
     */
    public static function determine(?string $keahlianParti, ?string $kecenderunganPolitik): string
    {
        $partiColor = self::classifyParti($keahlianParti);
        $kecenderunganColor = self::classifyKecenderungan($kecenderunganPolitik);

        // If either is black, voter is black
        if ($partiColor === 'hitam' || $kecenderunganColor === 'hitam') {
            return 'hitam';
        }

        // If either is white, voter is white
        if ($partiColor === 'putih' || $kecenderunganColor === 'putih') {
            return 'putih';
        }

        return 'kelabu';
    }

    protected static function classifyParti(?string $parti): string
    {
        if (!$parti) return 'kelabu';

        $parti = strtoupper(trim($parti));

        $grey = ['TIDAK PASTI', 'TIDAK BERPARTI', 'TIADA', 'BUKAN AHLI', '-'];
        if (in_array($parti, $grey)) return 'kelabu';

        $white = ['KEADILAN', 'PKR', 'DAP', 'AMANAH', 'PH', 'PAKATAN HARAPAN'];
        foreach ($white as $w) {
            if (str_contains($parti, $w)) return 'putih';
        }

        return 'hitam';
    }

    protected static function classifyKecenderungan(?string $kecenderungan): string
    {
        if (!$kecenderungan) return 'kelabu';

        $kecenderungan = strtoupper(trim($kecenderungan));

        $grey = ['TIDAK PASTI', 'ATAS PAGAR', '-'];
        if (in_array($kecenderungan, $grey)) return 'kelabu';

        $white = ['PH', 'PAKATAN HARAPAN', 'PH/BN'];
        foreach ($white as $w) {
            if (str_contains($kecenderungan, $w)) return 'putih';
        }

        return 'hitam';
    }
}
