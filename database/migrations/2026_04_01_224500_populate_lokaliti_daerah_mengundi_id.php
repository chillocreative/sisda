<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Find the most common daerah_mengundi for each lokaliti from voter data
        $mappings = DB::table('pangkalan_data_pengundi')
            ->select('lokaliti', 'daerah_mengundi', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('lokaliti')
            ->where('lokaliti', '!=', '')
            ->whereNotNull('daerah_mengundi')
            ->where('daerah_mengundi', '!=', '')
            ->groupBy('lokaliti', 'daerah_mengundi')
            ->orderBy('lokaliti')
            ->orderByDesc('cnt')
            ->get();

        // Build a map: lokaliti_name => daerah_mengundi_name (most common)
        $lokalitiToDm = [];
        foreach ($mappings as $row) {
            if (!isset($lokalitiToDm[$row->lokaliti])) {
                $lokalitiToDm[$row->lokaliti] = $row->daerah_mengundi;
            }
        }

        // Get all DaerahMengundi records keyed by name
        $dmRecords = DB::table('daerah_mengundi')->pluck('id', 'nama');

        // Update lokaliti records
        foreach ($lokalitiToDm as $lokalitiName => $dmName) {
            $dmId = $dmRecords[$dmName] ?? null;
            if ($dmId) {
                DB::table('lokaliti')
                    ->where('nama', $lokalitiName)
                    ->whereNull('daerah_mengundi_id')
                    ->update(['daerah_mengundi_id' => $dmId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('lokaliti')->update(['daerah_mengundi_id' => null]);
    }
};
