// Shared filter plumbing for the Pilihanraya pages — one definition of
// the filter shape so WarRoom, Simulasi and FilterBar can't drift.

export const EMPTY_FILTERS = {
    negeri_id: '',
    parlimen_id: '',
    kadun_id: '',
    tarikh_dari: '',
    tarikh_hingga: '',
    umur_dari: '',
    umur_hingga: '',
    status_pengundi: '', // '' | 'baru' | 'lama'
};

export function cleanParams(filters) {
    return Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v != null));
}

/**
 * Parameter permintaan untuk pengambilan data tab.
 *
 * Apabila SEMUA penapis kosong, cleanParams() memulangkan {} — permintaan
 * KOSONG, yang tidak dapat dibezakan oleh middleware daripada navigasi biasa
 * dan oleh itu MEMULIHKAN penapis yang baru sahaja dibuang pengguna. Hantar
 * isyarat reset yang jelas dalam keadaan itu supaya "tiada penapis dipilih"
 * bermakna "lupakan", bukan "ingat semula".
 */
export function requestParams(filters) {
    const bersih = cleanParams(filters);

    return Object.keys(bersih).length === 0 ? { reset_filters: 1 } : bersih;
}

/**
 * Semai keadaan penapis awal daripada nilai yang diingat pelayan.
 *
 * Hanya kunci yang WUJUD dalam bentuk lalai diterima, jadi entri sesi lama
 * tidak boleh menyuntik medan yang halaman ini tidak faham. Nilai kosong
 * dikekalkan sebagai kosong — "dibersihkan" ialah pilihan yang sah, bukan
 * ketiadaan pilihan.
 */
export function initialFilters(remembered, defaults = EMPTY_FILTERS) {
    if (!remembered) return { ...defaults };

    return Object.fromEntries(
        Object.entries(defaults).map(([k, v]) => [k, remembered[k] ?? v]),
    );
}
