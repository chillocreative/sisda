<?php

namespace Database\Seeders;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use Illuminate\Database\Seeder;

/**
 * Shared logic for seeding a state's electoral hierarchy — Parlimen (Bandar) +
 * DUN (Kadun) — from a verified SPR delimitation.
 *
 * Daerah Mengundi (polling districts) are NOT seeded: they are real SPR DPPR
 * data (hundreds per state) and flow in from voter-roll uploads. Fabricating
 * them would be inventing data.
 *
 * Idempotent + duplicate-safe: a pre-existing Bandar or Kadun (e.g. created by
 * syncMasterData from an upload, which stores a name but no kod_*) is matched BY
 * NAME within the state, its kod backfilled, and — for a DUN — it is re-pointed
 * to the correct parliament. A DUN wrongly linked to a stray Bandar is healed.
 * Safe to run repeatedly.
 *
 * Subclasses supply the state name and the P-code => [parlimen, [[N-code, DUN]]]
 * map. Names are UPPERCASE to match the DPT convention (the importer uppercases
 * negeri/parlimen/kadun) and the rows uploads already created.
 */
abstract class StateElectoralSeeder extends Seeder
{
    abstract protected function negeriName(): string;

    /** @return array<string, array{0: string, 1: array<int, array{0: string, 1: string}>}> */
    abstract protected function data(): array;

    public function run(): void
    {
        $name = $this->negeriName();
        $negeri = Negeri::whereRaw('UPPER(TRIM(nama)) = ?', [mb_strtoupper($name)])->first()
            ?? Negeri::create(['nama' => $name]);

        $parlimen = 0;
        $dun = 0;

        foreach ($this->data() as $pcode => [$pname, $duns]) {
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

        $this->command?->info("{$name} seeded: {$parlimen} Parlimen, {$dun} DUN. (Daerah Mengundi come from DPT uploads.)");
    }
}
