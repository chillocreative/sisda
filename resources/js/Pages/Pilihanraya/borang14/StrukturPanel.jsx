import { useMemo, useState } from 'react';
import axios from 'axios';
import { Loader2, Plus, Trash2, X } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import ConfirmDialog from './ConfirmDialog';

// Bentuk sahaja — panel ini tidak tahu apa-apa tentang undi. Undi mana yang
// akan hilang dijawab oleh server (endpoint .kesan), kerana hanya server tahu
// apa yang benar-benar tersimpan (lihat Borang14StrukturService).
let seq = 0;
const newRowId = () => `pm_new_${Date.now()}_${seq++}`;

// Baris UNDI AWAL / UNDI POS disimpan dengan `pusat` kosong secara sengaja
// (Borang14StrukturService::expand()) — itulah sentinel bagi baris pra-hari,
// bukan Pusat Mengundi sebenar. Endpoint .kesan menghantar balik nama pusat
// APA ADANYA, jadi rentetan kosong itu perlu dilabel di sini supaya senarai
// tidak memaparkan jurang kosong atau koma bersendirian.
const labelPusat = (nama) => (nama === '' ? 'Undi Awal / Undi Pos' : nama);

/**
 * Panel penyuntingan struktur Borang 14 (Pusat Mengundi + bilangan saluran +
 * UNDI AWAL/POS) untuk pilihan raya akan datang yang tiada scoresheet/DPT
 * untuk dibaca. Induk (KeyinTab, akan disambungkan pada Task 7) yang
 * mengawal `boleh_sunting_struktur` — panel ini tidak melakukan sebarang
 * pengesahan peranan sendiri.
 */
export default function StrukturPanel({ picker, struktur, onSaved, onCancel }) {
    const { t } = usePilihanrayaTheme();
    const [pusat, setPusat] = useState(() => struktur?.pusat ?? []);
    const [undiAwal, setUndiAwal] = useState(Boolean(struktur?.undi_awal));
    const [undiPos, setUndiPos] = useState(Boolean(struktur?.undi_pos));
    const [saving, setSaving] = useState(false);
    const [ralat, setRalat] = useState('');
    const [kesan, setKesan] = useState(null); // { baris, undi, pusat[] } daripada endpoint .kesan (dry run)

    const params = useMemo(() => ({
        kawasan_type: picker.kawasanType,
        kawasan_id: picker.kawasanType === 'parlimen' ? picker.parlimenId : picker.kadunId,
        jenis_pr: picker.jenisPr,
        tahun: Number(picker.tahun),
    }), [picker]);

    const ubah = (i, patch) => setPusat((prev) => prev.map((p, j) => (j === i ? { ...p, ...patch } : p)));
    const buang = (i) => setPusat((prev) => prev.filter((_, j) => j !== i));
    const tambah = () => setPusat((prev) => [...prev, { row_id: newRowId(), dm: '', pusat: '', saluran_count: 1 }]);

    const payload = () => ({
        ...params,
        pusat: pusat.map((p) => ({
            row_id: p.row_id,
            dm: (p.dm || '').trim(),
            pusat: (p.pusat || '').trim(),
            saluran_count: Math.max(1, Math.min(20, Number(p.saluran_count) || 1)),
        })),
        undi_awal: undiAwal,
        undi_pos: undiPos,
    });

    // Laravel 422 membawa mesej generik ("The given data was invalid.") pada
    // `message`, dengan sebab sebenar pada `errors.<medan>[0]`. Ambil sebab
    // sebenar apabila ada supaya pengguna nampak mesej yang berguna; 403/500
    // hanya ada `message`.
    const extractError = (e, fallback) => {
        const data = e?.response?.data;
        if (!data) return fallback;
        const first = data.errors ? Object.values(data.errors)[0] : null;
        if (Array.isArray(first) && first[0]) return first[0];
        return data.message || fallback;
    };

    // Pengesahan sisi klien — kesilapan biasa (nama kosong/pendua) dapat mesej
    // segera tanpa pergi-balik ke server. Server menguatkuasakan peraturan
    // yang SAMA tanpa mengira semakan ini; semakan ini hanya budi bahasa.
    const semak = async () => {
        setRalat('');

        const kosong = pusat.some((p) => !(p.pusat || '').trim());
        if (kosong) { setRalat('Setiap Pusat Mengundi mesti bernama.'); return; }

        const nama = pusat.map((p) => (p.pusat || '').trim().toUpperCase());
        if (new Set(nama).size !== nama.length) {
            setRalat('Nama Pusat Mengundi mesti unik (tidak kira huruf besar/kecil).');
            return;
        }

        setSaving(true);
        try {
            const { data } = await axios.post(route('pilihanraya.borang-14.struktur.kesan'), payload());
            // Tiada undi terjejas → simpan terus, tanpa mengganggu pengguna
            // dengan amaran yang tidak bermakna apa-apa. Ada undi terjejas →
            // paksa pengesahan dengan ANGKA SEBENAR dahulu — amaran kabur
            // hanya diklik lalu, angka tidak.
            if (data.baris > 0) {
                setKesan(data);
                setSaving(false);
                return;
            }
            await simpan();
        } catch (e) {
            setRalat(extractError(e, 'Gagal menyemak kesan perubahan.'));
            setSaving(false);
        }
    };

    const simpan = async () => {
        setSaving(true);
        try {
            await axios.post(route('pilihanraya.borang-14.struktur'), payload());
            setKesan(null);
            onSaved();
        } catch (e) {
            // Simpan gagal — dialog pengesahan ditutup dan sebab kegagalan
            // dipaparkan; suntingan pengguna kekal di borang (tiada apa
            // dibuang), supaya pengguna boleh cuba semula tanpa menaip semula.
            setKesan(null);
            setRalat(extractError(e, 'Gagal menyimpan struktur.'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className={t.card}>
            <div className="flex items-center justify-between mb-3">
                <h3 className="text-lg font-semibold text-slate-900">Struktur Borang 14</h3>
                <button type="button" onClick={onCancel} disabled={saving} className={t.buttonSecondary}>
                    <X className="h-4 w-4" /> Tutup
                </button>
            </div>

            <p className={`${t.subtext} text-sm mb-4`}>
                Senaraikan Pusat Mengundi dan bilangan saluran bagi setiap satu. Bilangan
                pengundi berdaftar tidak diminta di sini — ia tidak diketahui bagi pilihan
                raya akan datang, dan angka yang tidak diketahui dibiarkan kosong, bukan sifar.
            </p>

            <div className="overflow-x-auto mb-3">
                <table className="min-w-full">
                    <thead>
                        <tr>
                            <th className={t.tableHead}>Pusat Mengundi</th>
                            <th className={t.tableHead}>Daerah Mengundi</th>
                            <th className={t.tableHead}>Saluran</th>
                            <th className={t.tableHead}><span className="sr-only">Tindakan</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {pusat.map((p, i) => (
                            <tr key={p.row_id} className={t.tableRow}>
                                <td className={t.tableCell}>
                                    <input
                                        value={p.pusat}
                                        onChange={(e) => ubah(i, { pusat: e.target.value })}
                                        placeholder="Nama Pusat Mengundi"
                                        disabled={saving}
                                        className={`${t.input} min-w-[200px]`}
                                    />
                                </td>
                                <td className={t.tableCell}>
                                    <input
                                        value={p.dm}
                                        onChange={(e) => ubah(i, { dm: e.target.value })}
                                        placeholder="Daerah Mengundi"
                                        disabled={saving}
                                        className={`${t.input} min-w-[160px]`}
                                    />
                                </td>
                                <td className={t.tableCell}>
                                    <input
                                        type="number" min="1" max="20"
                                        value={p.saluran_count}
                                        onChange={(e) => ubah(i, { saluran_count: e.target.value })}
                                        disabled={saving}
                                        className={`${t.input} w-20`}
                                    />
                                </td>
                                <td className={t.tableCell}>
                                    <button
                                        type="button"
                                        onClick={() => buang(i)}
                                        disabled={saving}
                                        title="Buang Pusat Mengundi ini"
                                        className="inline-flex items-center gap-1 text-sm text-red-500 hover:text-red-600 disabled:opacity-50"
                                    >
                                        <Trash2 className="h-4 w-4" /> Buang
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {pusat.length === 0 && (
                            <tr>
                                <td className={`${t.tableCell} ${t.subtext}`} colSpan={4}>
                                    Belum ada Pusat Mengundi disenaraikan. Tambah sekurang-kurangnya satu, atau
                                    tandakan UNDI AWAL / UNDI POS sahaja di bawah jika kerusi ini hanya mengundi pos.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <button type="button" onClick={tambah} disabled={saving} className={`${t.buttonSecondary} mb-4`}>
                <Plus className="h-4 w-4" /> Tambah Pusat Mengundi
            </button>

            <div className="flex items-center gap-6 mb-4 text-sm">
                <label className="flex items-center gap-2">
                    <input type="checkbox" checked={undiAwal} disabled={saving} onChange={(e) => setUndiAwal(e.target.checked)} />
                    UNDI AWAL
                </label>
                <label className="flex items-center gap-2">
                    <input type="checkbox" checked={undiPos} disabled={saving} onChange={(e) => setUndiPos(e.target.checked)} />
                    UNDI POS
                </label>
            </div>

            {ralat && (
                <div className="flex items-center gap-1.5 text-sm bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3 mb-4">
                    <X className="h-4 w-4 shrink-0" /> {ralat}
                </div>
            )}

            <div className="flex justify-end gap-2">
                <button type="button" onClick={onCancel} disabled={saving} className={t.buttonSecondary}>Batal</button>
                <button type="button" onClick={semak} disabled={saving} className={t.buttonPrimary}>
                    {saving && <Loader2 className="h-4 w-4 animate-spin" />} Simpan Struktur
                </button>
            </div>

            <ConfirmDialog
                open={!!kesan}
                title="Undi akan dipadam"
                confirmLabel="Ya, teruskan"
                busy={saving}
                onClose={() => setKesan(null)}
                onConfirm={simpan}
            >
                {kesan && (
                    <p>
                        Perubahan ini akan memadam <strong>{kesan.baris.toLocaleString('ms-MY')}</strong> baris undi
                        (jumlah <strong>{kesan.undi.toLocaleString('ms-MY')}</strong> undi) daripada:{' '}
                        <strong>{kesan.pusat.map(labelPusat).join(', ')}</strong>.
                        {' '}Tindakan ini tidak boleh dibuat asal. Teruskan?
                    </p>
                )}
            </ConfirmDialog>
        </div>
    );
}
