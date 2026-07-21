<?php

namespace App\Http\Controllers;

use App\Models\ElectionDataSetting;
use App\Models\ElectionSeat;
use App\Models\ElectionSeatResult;
use App\Services\Pilihanraya\ElectionDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Tetapan API electiondata.my — mengikut ClaudeSettingController: kunci
 * disimpan (disulitkan) dalam pangkalan data, tidak pernah dipulangkan kepada
 * pelayar, dan ditutup sebagai '••••••••' pada borang.
 */
class ElectionDataSettingController extends Controller
{
    private const MASK = '••••••••';

    public function index()
    {
        $this->authorizeAdmin();

        $s = ElectionDataSetting::current();

        return Inertia::render('Settings/ElectionData', [
            'settings' => [
                'api_key' => $s?->api_key ? self::MASK : '',
                'is_active' => (bool) ($s?->is_active ?? true),
                'has_key' => ! empty($s?->api_key),
                'last_synced_at' => $s?->last_synced_at?->toDateTimeString(),
            ],
            'stats' => [
                'seats' => ElectionSeat::count(),
                'results' => ElectionSeatResult::count(),
                // Kerusi yang disegerakkan tetapi tidak terpaut kepada geografi
                // SISDA — angka ini ialah kesihatan padanan rentetan, bukan hiasan.
                'unmatched' => ElectionSeat::whereNull('kadun_id')->whereNull('bandar_id')->count(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'api_key' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        // Borang menghantar semula topeng apabila kunci tidak disentuh — jangan
        // sekali-kali menyimpan topeng itu sebagai kunci sebenar.
        if (($data['api_key'] ?? '') === self::MASK || empty($data['api_key'])) {
            unset($data['api_key']);
        }

        $s = ElectionDataSetting::current();
        $s ? $s->update($data) : ElectionDataSetting::create($data);

        return redirect()->back()->with('success', 'Tetapan electiondata.my berjaya dikemaskini.');
    }

    /** Uji kunci terhadap API sebenar tanpa menyimpan apa-apa. */
    public function testConnection(Request $request, ElectionDataService $api)
    {
        $this->authorizeAdmin();

        if (! $api->isConfigured()) {
            return back()->with('error', 'Sila simpan kunci API dahulu sebelum menguji sambungan.');
        }

        $seats = $api->seats();

        return $seats === []
            ? back()->with('error', 'Sambungan gagal — kunci mungkin tidak sah atau perkhidmatan tidak dapat dihubungi.')
            : back()->with('success', 'Sambungan berjaya — '.count($seats).' kerusi tersedia.');
    }

    /** Konvensyen kebenaran dalam pengawal (CLAUDE.md), bukan middleware. */
    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
    }
}
