<?php

namespace App\Http\Requests;

use App\Models\DataPengundi;
use App\Services\VoterDataMasker;
use App\Services\VoterScopeService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Mirrors the conditional rules in ReportsController::hasilCulaanStore.
 *
 * The client mirrors these rules again in Dart to validate before queuing
 * offline. That duplication is unavoidable — the phone cannot call this —
 * so any change here MUST be mirrored in the Flutter validator, and the
 * 422 path in the sync inbox is the safety net when they drift.
 */
class StoreMobileCulaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Parlimen check runs in the controller; it needs the record.
    }

    /**
     * Masked-create: the draft carries '****' placeholders for sensitive
     * fields the field agent was never shown, plus locked_source_id
     * pointing at the record to swap the real values in from.
     *
     * This MUST run here, before rules() is evaluated, not in the
     * controller after validated(). Three of the SENSITIVE_FIELDS have
     * strict rules — no_ic (digits:12), umur (integer),
     * pendapatan_isi_rumah (numeric) — that '****' itself always fails.
     * Swapping post-validation left those three fields validated against
     * the mask, not the truth, so a genuine masked-create with any of
     * them present 422'd and the controller's swap code was unreachable
     * for those fields. Mirrors ReportsController::hasilCulaanStore's
     * merge-before-validate ordering (see that method's docblock), but
     * keeps this class's scoped VoterScopeService lookup rather than that
     * method's unscoped DataPengundi::find().
     *
     * The lookup MUST be scoped through VoterScopeService, the same rule
     * MobileVoterController::show() uses. Without it, an arbitrary
     * locked_source_id would load ANY voter row by ID regardless of the
     * caller's Kadun/Parlimen, letting a 'user' caller launder a
     * stranger's real no_ic / no_tel / alamat / poskod / negeri / bandar /
     * umur / bangsa / pendapatan_isi_rumah into a new record under their
     * own Parlimen. An admin in the caller's Parlimen could then unmask
     * it, and VoterSyncService propagates it into data_pengundi — a
     * cross-Parlimen PII laundering channel plus a data-integrity attack
     * on the voter roll.
     *
     * Missing-vs-out-of-scope MUST return the identical 409 response.
     * Distinguishing them turns this into an existence oracle over
     * sequential DataPengundi IDs, the same class of bug just fixed on
     * MobileVoterController's `q` (see escapeLike()/Finding 2 there).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('locked_source_id')) {
            return;
        }

        $query = DataPengundi::where('id', $this->input('locked_source_id'));
        VoterScopeService::apply($query, $this->user());
        $source = $query->first();

        if (! $source) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'errors' => ['locked_source_id' => ['Rekod sumber tidak lagi wujud. Sila cari semula pengundi ini.']],
            ], 409));
        }

        $merge = [];
        foreach (VoterDataMasker::SENSITIVE_FIELDS as $field) {
            if ($this->input($field) === VoterDataMasker::MASK) {
                $merge[$field] = $source->{$field};
            }
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        $has = $this->boolean('has_sumbangan');
        $req = fn (string $rule) => $has ? "required|{$rule}" : "nullable|{$rule}";

        return [
            'idempotency_key' => 'required|string|max:64',

            'nama' => 'required|string|max:255',
            'no_ic' => 'required|string|digits:12',
            'umur' => 'required|integer|min:1|max:150',
            'no_tel' => 'required|string|max:255',
            'bangsa' => 'required|string|max:255',
            'alamat' => 'required|string',
            'poskod' => 'required|string|max:255',
            'negeri' => 'required|string|max:255',
            'bandar' => 'required|string|max:255',
            'parlimen' => 'required|string|max:255',
            'kadun' => 'required|string|max:255',

            'mpkk' => 'nullable|string|max:255',
            'daerah_mengundi' => 'nullable|string|max:255',
            'lokaliti' => 'nullable|string|max:255',

            'has_sumbangan' => 'boolean',
            // No `exists:data_pengundi,id` here: that check is itself an
            // unscoped existence oracle (a 422 reveals whether an arbitrary
            // ID exists anywhere in the table) and it shadows the scoped 409
            // in MobileCulaanController::store() by firing first. The
            // controller's VoterScopeService-backed lookup is the only
            // check on this field.
            'locked_source_id' => 'nullable|integer',

            'bil_isi_rumah' => $req('integer|min:1'),
            'pendapatan_isi_rumah' => 'nullable|numeric|min:0',
            'pekerjaan' => $req('in:Kerajaan,Swasta,Bekerja Sendiri,Tidak Bekerja'),
            'jenis_pekerjaan' => $req('array|min:1'),
            'jenis_pekerjaan.*' => 'string|max:255',
            'jenis_pekerjaan_lain' => 'nullable|string|max:255',
            'pemilik_rumah' => $req('string|max:255'),
            'pemilik_rumah_lain' => 'nullable|string|max:255',
            'jenis_sumbangan' => $req('array|min:1'),
            'jenis_sumbangan_lain' => 'nullable|string|max:255',
            'tujuan_sumbangan' => $req('array|min:1'),
            'tujuan_sumbangan_lain' => 'nullable|string|max:255',
            'bantuan_lain' => $req('array|min:1'),
            'bantuan_lain_lain' => 'nullable|string|max:255',
            'perkeso_bantuan' => 'nullable|array',
            'perkeso_bantuan_lain' => 'nullable|string|max:255',
            'zpp_jenis_bantuan' => 'nullable|array',
            'isejahtera_program' => 'nullable|string|max:255',
            'bkb_program' => 'nullable|string|max:255',
            'jumlah_bantuan_tunai' => 'nullable|numeric|min:0',
            'jumlah_wang_tunai' => 'nullable|numeric|min:0',

            'keahlian_parti' => 'nullable|string|max:255',
            'kecenderungan_politik' => 'nullable|string|max:255',
            'status_pengundi' => 'nullable|string|max:255',
            'nota' => 'nullable|string',
        ];
    }

    /**
     * Bahasa Melayu messages for the rules a field agent can actually trip:
     * the required fields, digits:12 on no_ic, and the has_sumbangan-
     * conditional fields. Laravel's default messages are English (there is
     * no lang/ directory; config/app.php sets locale => en) and the mobile
     * client's classifier needs BM strings, not just the success/errors
     * envelope shape.
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Kunci idempotency diperlukan.',
            'idempotency_key.string' => 'Kunci idempotency tidak sah.',
            'idempotency_key.max' => 'Kunci idempotency terlalu panjang.',

            'nama.required' => 'Sila masukkan nama.',
            'nama.string' => 'Nama tidak sah.',
            'nama.max' => 'Nama tidak boleh melebihi 255 aksara.',

            'no_ic.required' => 'Sila masukkan nombor IC.',
            'no_ic.digits' => 'Nombor IC mesti 12 digit.',

            'umur.required' => 'Sila masukkan umur.',
            'umur.integer' => 'Umur tidak sah.',
            'umur.min' => 'Umur tidak sah.',
            'umur.max' => 'Umur tidak sah.',

            'no_tel.required' => 'Sila masukkan nombor telefon.',

            'bangsa.required' => 'Sila masukkan bangsa.',

            'alamat.required' => 'Sila masukkan alamat.',

            'poskod.required' => 'Sila masukkan poskod.',

            'negeri.required' => 'Sila masukkan negeri.',

            'bandar.required' => 'Sila masukkan bandar.',

            'parlimen.required' => 'Sila masukkan Parlimen.',

            'kadun.required' => 'Sila masukkan DUN.',

            'bil_isi_rumah.required' => 'Sila masukkan bilangan isi rumah.',
            'bil_isi_rumah.integer' => 'Bilangan isi rumah tidak sah.',
            'bil_isi_rumah.min' => 'Bilangan isi rumah tidak sah.',

            'pekerjaan.required' => 'Sila pilih pekerjaan.',
            'pekerjaan.in' => 'Pekerjaan yang dipilih tidak sah.',

            'jenis_pekerjaan.required' => 'Sila pilih sekurang-kurangnya satu Jenis Pekerjaan.',
            'jenis_pekerjaan.min' => 'Sila pilih sekurang-kurangnya satu Jenis Pekerjaan.',

            'pemilik_rumah.required' => 'Sila pilih Pemilik Rumah.',

            'jenis_sumbangan.required' => 'Sila pilih sekurang-kurangnya satu Jenis Sumbangan.',
            'jenis_sumbangan.min' => 'Sila pilih sekurang-kurangnya satu Jenis Sumbangan.',

            'tujuan_sumbangan.required' => 'Sila pilih sekurang-kurangnya satu Tujuan Sumbangan.',
            'tujuan_sumbangan.min' => 'Sila pilih sekurang-kurangnya satu Tujuan Sumbangan.',

            'bantuan_lain.required' => 'Sila pilih sekurang-kurangnya satu Bantuan Lain Yang Telah Diterima.',
            'bantuan_lain.min' => 'Sila pilih sekurang-kurangnya satu Bantuan Lain Yang Telah Diterima.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
