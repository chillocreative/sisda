<?php

namespace App\Http\Controllers;

use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\PacaForm;
use App\Models\PacaPusat;
use App\Models\PacaSaluran;
use App\Models\PacaSlot;
use App\Models\PacaSnapshot;
use App\Models\User;
use App\Services\Pilihanraya\PacaBuilderService;
use App\Services\Pilihanraya\PacaSlotPlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Pengawal admin PACABA — pemilihan kerusi, pembinaan/pemuatan roster
 * Pusat->Saluran->slot, simpan (dengan sejarah), tambah PA/Saluran, dan
 * pemulihan snapshot. Laluan awam (token per-Pusat) TIADA di sini —
 * lihat PacaPublicController (Tugasan 5).
 */
class PacaController extends Controller
{
    public function __construct(
        private readonly PacaBuilderService $builder,
        private readonly PacaSlotPlanner $planner,
    ) {
    }

    /** Halaman admin PACA — senarai kerusi berscoresheet untuk pemilih. */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('Pilihanraya/Paca', [
            'seats' => $this->builder->seatsWithScoresheet(),
        ]);
    }

    /**
     * Bina (atau muatkan semula, idempoten) PacaForm bagi satu kerusi/PR
     * daripada Borang14Form berscoresheet, dan pulangkan keseluruhan pokok
     * Pusat->Saluran->slot.
     */
    public function data(Request $request)
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate($this->kawasanRules($request->input('kawasan_type')));

        // Kebenaran disemak SEBELUM sebarang capaian Borang14Form — jika
        // tidak, respons 404 "borang belum wujud" membocorkan kandungan
        // kepada pemanggil yang langsung tiada kebenaran ke atas kerusi ini.
        $this->assertBolehAkses($user, $validated['kawasan_type'], (int) $validated['kawasan_id']);

        $borang14 = Borang14Form::forKawasan($validated['kawasan_type'], $validated['kawasan_id'])
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        if (! $borang14) {
            abort(404, 'Borang 14 (scoresheet) bagi kerusi ini belum wujud.');
        }

        $paca = $this->builder->buildFrom($borang14);

        return response()->json(['paca' => $this->treePayload($paca)]);
    }

    /**
     * Simpan keseluruhan pokok (ketua Pusat + petugas/masa setiap slot).
     * Snapshot PRA-suntingan ditulis dahulu (mirip Borang14) supaya sejarah
     * menyimpan apa yang WUJUD sebelum suntingan ini, dan 'pulih' boleh
     * mengembalikannya. Tempoh minimum 2 jam dikuatkuasakan SELEPAS
     * pengesahan bentuk, SEBELUM sebarang tulisan DB.
     */
    public function simpan(Request $request)
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate($this->pacaRules());

        $form = PacaForm::findOrFail($validated['paca_form_id']);

        $this->assertBolehAkses($user, $form->kawasan_type, (int) $form->kawasan_id);

        $pusatModels = $form->pusatList()->with('saluranList.slots')->get()->keyBy('id');

        // Laluan pertama: sahkan setiap id benar-benar milik borang ini, DAN
        // kuatkuasakan tempoh minimum 2 jam — sebelum sebarang tulisan DB.
        foreach ($validated['pusat'] as $pusatInput) {
            $pusat = $pusatModels->get($pusatInput['id']);
            if (! $pusat) {
                abort(422, 'Pusat mengundi tidak sah bagi borang ini.');
            }

            $saluranModels = $pusat->saluranList->keyBy('id');
            foreach ($pusatInput['saluran'] as $saluranInput) {
                $saluran = $saluranModels->get($saluranInput['id']);
                if (! $saluran) {
                    abort(422, 'Saluran tidak sah bagi Pusat ini.');
                }

                $slotModels = $saluran->slots->keyBy('id');
                foreach ($saluranInput['slot'] as $slotInput) {
                    $slot = $slotModels->get($slotInput['id']);
                    if (! $slot) {
                        abort(422, 'Slot tidak sah bagi Saluran ini.');
                    }

                    $calon = [
                        'jawatan' => $slot->jawatan,
                        'masa_mula' => $slotInput['masa_mula'],
                        'masa_tamat' => $slotInput['masa_tamat'],
                    ];

                    if (! $this->planner->minimumMet($calon)) {
                        throw ValidationException::withMessages([
                            'pusat' => "Saluran {$saluran->label} (Pusat {$pusat->pusat}): tempoh minimum 2 jam bagi slot {$slot->jawatan} tidak dipenuhi.",
                        ]);
                    }
                }
            }
        }

        DB::transaction(function () use ($form, $validated, $user) {
            PacaSnapshot::create([
                'paca_form_id' => $form->id,
                'data' => $this->treePayload($form),
                'reason' => 'before_edit',
                'created_by' => $user->id,
                'created_at' => now(),
            ]);

            foreach ($validated['pusat'] as $pusatInput) {
                PacaPusat::where('id', $pusatInput['id'])
                    ->where('paca_form_id', $form->id)
                    ->update([
                        'ketua_nama' => $pusatInput['ketua_nama'],
                        'ketua_tel' => $pusatInput['ketua_tel'],
                    ]);

                foreach ($pusatInput['saluran'] as $saluranInput) {
                    foreach ($saluranInput['slot'] as $slotInput) {
                        PacaSlot::where('id', $slotInput['id'])
                            ->where('paca_saluran_id', $saluranInput['id'])
                            ->update([
                                'masa_mula' => $slotInput['masa_mula'],
                                'masa_tamat' => $slotInput['masa_tamat'],
                                'petugas_nama' => $slotInput['petugas_nama'],
                                'petugas_kp' => $slotInput['petugas_kp'],
                                'petugas_tel' => $slotInput['petugas_tel'],
                                'petugas_parti' => $slotInput['petugas_parti'],
                            ]);
                    }
                }
            }
        });

        return response()->json(['paca' => $this->treePayload($form->fresh())]);
    }

    /** Tambah satu Saluran baharu (dengan 4 slot lalai PA1/PA2/PA3/CA) pada satu Pusat. */
    public function tambahSaluran(Request $request)
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'paca_pusat_id' => 'required|integer|exists:paca_pusat,id',
        ]);

        $pusat = PacaPusat::with('form')->findOrFail($validated['paca_pusat_id']);
        $form = $pusat->form;

        $this->assertBolehAkses($user, $form->kawasan_type, (int) $form->kawasan_id);

        DB::transaction(function () use ($pusat) {
            $urutan = (int) ($pusat->saluranList()->max('urutan') ?? 0) + 1;

            $saluran = $pusat->saluranList()->create([
                'label' => (string) $urutan,
                'urutan' => $urutan,
            ]);

            foreach ($this->planner->defaultSlots() as $slot) {
                $saluran->slots()->create([
                    'jawatan' => $slot['jawatan'],
                    'masa_mula' => $slot['masa_mula'],
                    'masa_tamat' => $slot['masa_tamat'],
                    'urutan' => $slot['urutan'],
                ]);
            }
        });

        return response()->json(['paca' => $this->treePayload($form->fresh())]);
    }

    /** Tambah satu slot PA baharu pada satu Saluran, dan labelkan semula (CA kekal terakhir). */
    public function tambahSlot(Request $request)
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'paca_saluran_id' => 'required|integer|exists:paca_saluran,id',
        ]);

        $saluran = PacaSaluran::with('pusat.form')->findOrFail($validated['paca_saluran_id']);
        $form = $saluran->pusat->form;

        $this->assertBolehAkses($user, $form->kawasan_type, (int) $form->kawasan_id);

        DB::transaction(function () use ($saluran) {
            $slots = $saluran->slots()->orderBy('urutan')->get();
            $ca = $slots->last();

            // Slot baharu disisipkan pada kedudukan CA sekarang, dan CA
            // ditolak satu ke belakang — relabel() di bawah yang menentukan
            // jawatan sebenar mengikut kedudukan baharu, bukan nilai ini.
            $insertUrutan = $ca ? $ca->urutan : (int) ($slots->max('urutan') ?? 0) + 1;

            if ($ca) {
                $ca->update(['urutan' => $ca->urutan + 1]);
            }

            $saluran->slots()->create([
                'jawatan' => 'PA',
                'masa_mula' => null,
                'masa_tamat' => null,
                'urutan' => $insertUrutan,
            ]);

            $this->relabelSaluran($saluran);
        });

        return response()->json(['paca' => $this->treePayload($form->fresh())]);
    }

    /** Senarai snapshot (sejarah) bagi satu PacaForm, terbaru dahulu. */
    public function sejarah(Request $request)
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'paca_form_id' => 'required|integer|exists:paca_forms,id',
        ]);

        $form = PacaForm::findOrFail($validated['paca_form_id']);
        $this->assertBolehAkses($user, $form->kawasan_type, (int) $form->kawasan_id);

        $sejarah = $form->snapshots()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'reason', 'created_by', 'created_at']);

        return response()->json(['sejarah' => $sejarah]);
    }

    /**
     * Pulihkan pokok semasa daripada satu snapshot — kemas kini baris
     * SEDIA ADA (dipadan mengikut id) kepada nilai yang disimpan snapshot
     * itu. Baris yang ditambah SELEPAS snapshot diambil (melalui Tambah
     * Saluran/Slot) tidak dibuang oleh pemulihan ini — lihat nota "concerns"
     * dalam laporan Tugasan 4.
     */
    public function pulih(Request $request)
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'snapshot_id' => 'required|integer|exists:paca_snapshots,id',
        ]);

        $snapshot = PacaSnapshot::with('form')->findOrFail($validated['snapshot_id']);
        $form = $snapshot->form;

        $this->assertBolehAkses($user, $form->kawasan_type, (int) $form->kawasan_id);

        DB::transaction(function () use ($form, $snapshot) {
            foreach ($snapshot->data['pusat'] ?? [] as $pusatData) {
                if (empty($pusatData['id'])) {
                    continue;
                }

                PacaPusat::where('id', $pusatData['id'])
                    ->where('paca_form_id', $form->id)
                    ->update([
                        'ketua_nama' => $pusatData['ketua_nama'] ?? null,
                        'ketua_tel' => $pusatData['ketua_tel'] ?? null,
                    ]);

                foreach ($pusatData['saluran'] ?? [] as $saluranData) {
                    if (empty($saluranData['id'])) {
                        continue;
                    }

                    foreach ($saluranData['slot'] ?? [] as $slotData) {
                        if (empty($slotData['id'])) {
                            continue;
                        }

                        PacaSlot::where('id', $slotData['id'])
                            ->where('paca_saluran_id', $saluranData['id'])
                            ->update([
                                'jawatan' => $slotData['jawatan'] ?? 'PA1',
                                'masa_mula' => $slotData['masa_mula'] ?? null,
                                'masa_tamat' => $slotData['masa_tamat'] ?? null,
                                'petugas_nama' => $slotData['petugas_nama'] ?? null,
                                'petugas_kp' => $slotData['petugas_kp'] ?? null,
                                'petugas_tel' => $slotData['petugas_tel'] ?? null,
                                'petugas_parti' => $slotData['petugas_parti'] ?? null,
                            ]);
                    }
                }
            }
        });

        return response()->json(['paca' => $this->treePayload($form->fresh())]);
    }

    /** Peraturan pengesahan bagi (kawasan_type, kawasan_id, jenis_pr, tahun). */
    private function kawasanRules(?string $kawasanType): array
    {
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        return [
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id' => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun' => 'required|integer|between:1959,2100',
        ];
    }

    /**
     * Peraturan pengesahan bagi simpan() — setiap medan 'present' (bukan
     * sekadar 'nullable') supaya pelanggan MESTI menghantar keadaan penuh
     * bagi setiap slot; jika tidak, ketiadaan satu kunci boleh sengaja
     * ditafsir sebagai "kosongkan" oleh update() di bawah tanpa pelanggan
     * berniat begitu.
     */
    private function pacaRules(): array
    {
        return [
            'paca_form_id' => 'required|integer|exists:paca_forms,id',
            'pusat' => 'present|array|max:500',
            'pusat.*.id' => 'required|integer',
            'pusat.*.ketua_nama' => 'present|nullable|string|max:255',
            'pusat.*.ketua_tel' => 'present|nullable|string|max:50',
            'pusat.*.saluran' => 'present|array|max:20',
            'pusat.*.saluran.*.id' => 'required|integer',
            'pusat.*.saluran.*.slot' => 'present|array|max:20',
            'pusat.*.saluran.*.slot.*.id' => 'required|integer',
            'pusat.*.saluran.*.slot.*.masa_mula' => 'present|nullable|string|max:5',
            'pusat.*.saluran.*.slot.*.masa_tamat' => 'present|nullable|string|max:5',
            'pusat.*.saluran.*.slot.*.petugas_nama' => 'present|nullable|string|max:255',
            'pusat.*.saluran.*.slot.*.petugas_kp' => 'present|nullable|string|max:20',
            'pusat.*.saluran.*.slot.*.petugas_tel' => 'present|nullable|string|max:30',
            'pusat.*.saluran.*.slot.*.petugas_parti' => 'present|nullable|string|max:255',
        ];
    }

    /** Kebenaran: super_admin lulus semua; admin biasa dilingkup pada Bandar-nya sendiri. */
    private function assertBolehAkses(User $user, string $kawasanType, int $kawasanId): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $bandarId = $kawasanType === Borang14Form::KAWASAN_PARLIMEN
            ? $kawasanId
            : Kadun::whereKey($kawasanId)->value('bandar_id');

        if ($user->bandar_id === null || (int) $user->bandar_id !== (int) $bandarId) {
            abort(403, 'Unauthorized action.');
        }
    }

    /** Labelkan semula jawatan seluruh Saluran mengikut urutan (CA kekal terakhir). */
    private function relabelSaluran(PacaSaluran $saluran): void
    {
        $slots = $saluran->slots()->orderBy('urutan')->get();

        $relabelled = $this->planner->relabel(
            $slots->map(fn ($s) => ['id' => $s->id, 'urutan' => $s->urutan])->all(),
        );

        foreach ($relabelled as $i => $r) {
            PacaSlot::whereKey($r['id'])->update(['jawatan' => $r['jawatan'], 'urutan' => $i + 1]);
        }
    }

    /** Pokok penuh Pusat->Saluran->slot bagi satu PacaForm — dikongsi oleh data()/simpan()/tambah.../pulih() dan snapshot. */
    private function treePayload(PacaForm $form): array
    {
        return [
            'id' => $form->id,
            'kawasan_type' => $form->kawasan_type,
            'kawasan_id' => $form->kawasan_id,
            'jenis_pr' => $form->jenis_pr,
            'tahun' => $form->tahun,
            'pusat' => $form->pusatList()->with('saluranList.slots')->get()
                ->map(fn ($p) => $this->pusatPayload($p))->all(),
        ];
    }

    private function pusatPayload(PacaPusat $pusat): array
    {
        return [
            'id' => $pusat->id,
            'dm' => $pusat->dm,
            'pusat' => $pusat->pusat,
            'ketua_nama' => $pusat->ketua_nama,
            'ketua_tel' => $pusat->ketua_tel,
            'public_token' => $pusat->public_token,
            // Laluan awam per-Pusat (Tugasan 5) — URL mentah, bukan route()
            // bernama, kerana laluan itu belum wujud lagi pada Tugasan 4 ini.
            'public_url' => url('/paca/'.$pusat->public_token),
            'urutan' => $pusat->urutan,
            'saluran' => $pusat->saluranList->map(fn ($s) => $this->saluranPayload($s))->all(),
        ];
    }

    private function saluranPayload(PacaSaluran $saluran): array
    {
        return [
            'id' => $saluran->id,
            'label' => $saluran->label,
            'urutan' => $saluran->urutan,
            'slot' => $saluran->slots->map(fn ($s) => $this->slotPayload($s))->all(),
        ];
    }

    private function slotPayload(PacaSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'jawatan' => $slot->jawatan,
            'masa_mula' => $slot->masa_mula,
            'masa_tamat' => $slot->masa_tamat,
            'urutan' => $slot->urutan,
            'petugas_nama' => $slot->petugas_nama,
            'petugas_kp' => $slot->petugas_kp,
            'petugas_tel' => $slot->petugas_tel,
            'petugas_parti' => $slot->petugas_parti,
        ];
    }
}
