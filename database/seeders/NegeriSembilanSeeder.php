<?php

namespace Database\Seeders;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use Illuminate\Database\Seeder;

/**
 * Seeds the complete Negeri Sembilan electoral hierarchy — 8 Parlimen (P126–
 * P133) and 36 DUN (N01–N36), from the current SPR delimitation (verified per
 * federal-constituency on Wikipedia; Juasseh sits under P129 Kuala Pilah, which
 * matches the live DPT roll).
 *
 * Daerah Mengundi (polling districts) are NOT seeded here: they are real SPR
 * DPPR data (hundreds per state) and flow in from voter-roll uploads — exactly
 * how Juasseh's DMs already appeared. Fabricating them would be inventing data.
 *
 * Names are UPPERCASE to match the DPT convention (the importer uppercases
 * negeri/parlimen/kadun) and the N. Sembilan rows already created by
 * syncMasterData from uploads.
 *
 * Duplicate-safe / idempotent: a pre-existing Bandar or Kadun (e.g. created by
 * syncMasterData, which stores a name but no kod_*) is matched BY NAME within
 * the state, then its kod is backfilled and — for a DUN — it is re-pointed to
 * the correct parliament. A DUN wrongly linked to a stray Bandar is thus healed.
 * Safe to run repeatedly.
 */
class NegeriSembilanSeeder extends Seeder
{
    /** P-code => [parlimen name, [ [N-code, DUN name], ... ] ] */
    private const DATA = [
        'P126' => ['JELEBU', [['N01', 'CHENNAH'], ['N02', 'PERTANG'], ['N03', 'SUNGAI LUI'], ['N04', 'KLAWANG']]],
        'P127' => ['JEMPOL', [['N05', 'SERTING'], ['N06', 'PALONG'], ['N07', 'JERAM PADANG'], ['N08', 'BAHAU']]],
        'P128' => ['SEREMBAN', [['N09', 'LENGGENG'], ['N10', 'NILAI'], ['N11', 'LOBAK'], ['N12', 'TEMIANG'], ['N13', 'SIKAMAT'], ['N14', 'AMPANGAN']]],
        'P129' => ['KUALA PILAH', [['N15', 'JUASSEH'], ['N16', 'SERI MENANTI'], ['N17', 'SENALING'], ['N18', 'PILAH'], ['N19', 'JOHOL']]],
        'P130' => ['RASAH', [['N20', 'LABU'], ['N21', 'BUKIT KEPAYANG'], ['N22', 'RAHANG'], ['N23', 'MAMBAU'], ['N24', 'SEREMBAN JAYA']]],
        'P131' => ['REMBAU', [['N25', 'PAROI'], ['N26', 'CHEMBONG'], ['N27', 'RANTAU'], ['N28', 'KOTA']]],
        'P132' => ['PORT DICKSON', [['N29', 'CHUAH'], ['N30', 'LUKUT'], ['N31', 'BAGAN PINANG'], ['N32', 'LINGGI'], ['N33', 'SRI TANJUNG']]],
        'P133' => ['TAMPIN', [['N34', 'GEMAS'], ['N35', 'GEMENCHEH'], ['N36', 'REPAH']]],
    ];

    public function run(): void
    {
        // Attach to the existing Negeri row whatever its case (uploads store
        // "NEGERI SEMBILAN"); create it only if truly absent.
        $negeri = Negeri::whereRaw('UPPER(TRIM(nama)) = ?', ['NEGERI SEMBILAN'])->first()
            ?? Negeri::create(['nama' => 'NEGERI SEMBILAN']);

        $parlimen = 0;
        $dun = 0;

        foreach (self::DATA as $pcode => [$pname, $duns]) {
            $bandar = Bandar::where('negeri_id', $negeri->id)
                ->whereRaw('UPPER(TRIM(nama)) = ?', [$pname])->first()
                ?? Bandar::firstOrNew(['kod_parlimen' => $pcode, 'negeri_id' => $negeri->id]);
            $bandar->negeri_id = $negeri->id;
            $bandar->kod_parlimen = $pcode;
            $bandar->nama = $bandar->nama ?: $pname; // never overwrite an existing name
            $bandar->save();
            $parlimen++;

            foreach ($duns as [$ncode, $dname]) {
                // Match a DUN by name ANYWHERE in this state (it may be linked to
                // a stray/old parliament) and re-point it correctly.
                $kadun = Kadun::whereRaw('UPPER(TRIM(nama)) = ?', [$dname])
                    ->whereHas('bandar', fn ($q) => $q->where('negeri_id', $negeri->id))
                    ->first()
                    ?? new Kadun(['nama' => $dname]);
                $kadun->nama = $kadun->nama ?: $dname;
                $kadun->kod_dun = $ncode;
                $kadun->bandar_id = $bandar->id;
                $kadun->save();
                $dun++;
            }
        }

        $this->command?->info("Negeri Sembilan seeded: {$parlimen} Parlimen, {$dun} DUN. (Daerah Mengundi come from DPT uploads.)");
    }
}
