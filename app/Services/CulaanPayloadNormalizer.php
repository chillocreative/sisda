<?php

namespace App\Services;

/**
 * Flattens Culaan checkbox arrays into the comma-separated strings the
 * hasil_culaan columns actually store, substituting the paired *_lain
 * free-text value wherever the user picked a "Lain-lain" option.
 *
 * Extracted verbatim from ReportsController::hasilCulaanStore so the web
 * form and the mobile API produce identical rows. Behaviour is preserved
 * exactly, including the deliberate inconsistency below.
 *
 * NOTE ON MATCHING: jenis_pekerjaan and pemilik_rumah match the literal
 * string 'Lain-lain'. The other four match any option merely CONTAINING
 * 'lain' (case-insensitive). This is how the live system already stores
 * user input; normalising the rule would retroactively change the meaning
 * of existing submissions, so it stays until that is decided separately.
 */
class CulaanPayloadNormalizer
{
    /** Fields matching any option containing 'lain', case-insensitive. */
    private const FUZZY_LAIN_FIELDS = [
        'jenis_sumbangan',
        'tujuan_sumbangan',
        'bantuan_lain',
        'perkeso_bantuan',
    ];

    public static function normalize(array $validated): array
    {
        // pemilik_rumah: scalar, exact match.
        if (($validated['pemilik_rumah'] ?? null) === 'Lain-lain' && ! empty($validated['pemilik_rumah_lain'])) {
            $validated['pemilik_rumah'] = $validated['pemilik_rumah_lain'];
        }
        unset($validated['pemilik_rumah_lain']);

        // jenis_pekerjaan: array, exact match.
        if (isset($validated['jenis_pekerjaan']) && is_array($validated['jenis_pekerjaan'])) {
            $items = $validated['jenis_pekerjaan'];
            if (in_array('Lain-lain', $items, true) && ! empty($validated['jenis_pekerjaan_lain'])) {
                $items = array_filter($items, fn ($i) => $i !== 'Lain-lain');
                $items[] = $validated['jenis_pekerjaan_lain'];
            }
            $validated['jenis_pekerjaan'] = implode(', ', $items);
            $validated['jenis_pekerjaan_lain'] = null;
        }

        // Four fields sharing the fuzzy 'lain' rule.
        foreach (self::FUZZY_LAIN_FIELDS as $field) {
            if (! isset($validated[$field]) || ! is_array($validated[$field])) {
                continue;
            }
            $items = $validated[$field];
            $lainKey = $field.'_lain';
            $hasLain = count(array_filter($items, fn ($i) => stripos($i, 'lain') !== false)) > 0;

            if ($hasLain && ! empty($validated[$lainKey])) {
                $items = array_filter($items, fn ($i) => stripos($i, 'lain') === false);
                $items[] = $validated[$lainKey];
            }
            $validated[$field] = implode(', ', $items);
        }

        // zpp_jenis_bantuan: plain flatten, no Lain-lain handling.
        if (isset($validated['zpp_jenis_bantuan']) && is_array($validated['zpp_jenis_bantuan'])) {
            $validated['zpp_jenis_bantuan'] = implode(', ', $validated['zpp_jenis_bantuan']);
        }

        unset(
            $validated['jenis_sumbangan_lain'],
            $validated['tujuan_sumbangan_lain'],
            $validated['bantuan_lain_lain'],
            $validated['perkeso_bantuan_lain'],
        );

        return $validated;
    }
}
