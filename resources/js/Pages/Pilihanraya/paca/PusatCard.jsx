import { useState } from 'react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import DragScroll from '../analisa/DragScroll';

/**
 * Satu kad Pusat Mengundi: pautan awam per-Pusat
 * (token unik — lihat PacaPublicController), dan setiap Saluran dengan
 * jadual slot (PA1..PAn + CA). `onChange`/`onTambahSaluran`/`onTambahSlot`
 * semuanya diselaraskan oleh Paca.jsx supaya penggabungan (merge) pokok
 * selepas tindakan server berlaku di SATU tempat sahaja.
 */
/**
 * Label paparan slot — pelayan menghantar `jawatan_papar` ('PA4 / CA' bagi
 * slot terakhir). `jawatan` mentah kekal untuk logik, bukan paparan.
 */
const labelSlot = (slot) => slot.jawatan_papar ?? slot.jawatan;

/**
 * Kawalan medan slot diekstrak supaya susun atur JADUAL (>=md) dan susun atur
 * KAD BERTINDAN (mobil) berkongsi SATU takrifan. Menduplikasi <input> mentah
 * dalam dua susun atur ialah cara pasti ia terpesong — lihat amaran pertindihan
 * HasilCulaan Create/Edit dalam CLAUDE.md.
 */
function MedanTeks({ t, slot, name, type = 'text', placeholder, saving, onUbah, className = '' }) {
    return (
        <input
            type={type}
            className={`${t.input} ${className}`}
            value={slot[name] ?? ''}
            disabled={saving}
            placeholder={placeholder}
            onChange={(e) => onUbah({ [name]: e.target.value })}
        />
    );
}

function MedanParti({ t, slot, parti, saving, onUbah, className = '' }) {
    return (
        <select
            className={`${t.input} ${className}`}
            value={slot.petugas_parti ?? ''}
            disabled={saving}
            onChange={(e) => onUbah({ petugas_parti: e.target.value || null })}
        >
            <option value="">— Pilih Parti —</option>
            {/* Kekalkan nilai tersimpan walaupun ia tiada dalam Data Induk
                (mis. data lama) supaya ia tidak hilang secara senyap. */}
            {slot.petugas_parti && !parti.includes(slot.petugas_parti) && (
                <option value={slot.petugas_parti}>{slot.petugas_parti}</option>
            )}
            {parti.map((p) => <option key={p} value={p}>{p}</option>)}
        </select>
    );
}

/** Nota di bawah Masa Tamat — hanya bagi slot CA yang masih kosong. */
function NotaCa({ t, slot }) {
    if (slot.jawatan !== 'CA' || slot.masa_tamat) return null;

    return <p className={`${t.subtext} text-xs mt-0.5`}>kosong = selesai</p>;
}

function ButangBuang({ slot, boleh, saving, buanging, onBuang, className = '' }) {
    if (!boleh) return null;

    return (
        <button
            type="button"
            onClick={() => onBuang(slot)}
            disabled={saving || buanging}
            title={`Buang ${labelSlot(slot)}`}
            className={`inline-flex items-center justify-center text-slate-400 hover:text-red-600 disabled:opacity-50 ${className}`}
        >
            {buanging ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
        </button>
    );
}

export default function PusatCard({ pusat, saving, parti = [], onChangeSlot, onTambahSaluran, onTambahSlot, onBuangSlot }) {
    const { t } = usePilihanrayaTheme();
    const [addingSaluran, setAddingSaluran] = useState(false);
    const [addingSlotFor, setAddingSlotFor] = useState(null);
    const [buangingSlot, setBuangingSlot] = useState(null);

    const buangSlot = async (slot) => {
        const adaData = slot.petugas_nama || slot.petugas_kp || slot.petugas_tel || slot.petugas_parti;
        if (adaData && !window.confirm(`Buang slot ${labelSlot(slot)}? Butiran petugas yang telah diisi akan hilang.`)) return;
        setBuangingSlot(slot.id);
        try {
            await onBuangSlot(slot.id);
        } finally {
            setBuangingSlot(null);
        }
    };

    const tambahSaluran = async () => {
        setAddingSaluran(true);
        try {
            await onTambahSaluran(pusat.id);
        } finally {
            setAddingSaluran(false);
        }
    };

    const tambahSlot = async (saluranId) => {
        setAddingSlotFor(saluranId);
        try {
            await onTambahSlot(saluranId);
        } finally {
            setAddingSlotFor(null);
        }
    };

    return (
        <div className={t.card}>
            <div className="mb-4">
                <h3 className="text-base sm:text-lg font-semibold text-slate-900 break-words">{pusat.pusat}</h3>
                <p className={`${t.subtext} text-xs mt-0.5 break-words`}>{pusat.dm}</p>
            </div>

            <div className="space-y-5">
                {pusat.saluran.map((saluran) => (
                    <div key={saluran.id}>
                        <div className="flex items-center justify-between mb-2">
                            <h4 className="text-sm font-semibold text-slate-800">Saluran {saluran.label}</h4>
                            <button
                                type="button"
                                onClick={() => tambahSlot(saluran.id)}
                                disabled={saving || addingSlotFor === saluran.id}
                                className="inline-flex items-center gap-1 text-xs text-slate-600 hover:text-slate-900 disabled:opacity-50"
                            >
                                {addingSlotFor === saluran.id ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Plus className="h-3.5 w-3.5" />}
                                Tambah PA
                            </button>
                        </div>

                        {/* >=md: jadual penuh. Seretan mendatar boleh diterima
                            apabila semua lajur hampir muat. */}
                        <div className="hidden md:block">
                            <DragScroll>
                                <table className="min-w-full">
                                    <thead>
                                        <tr>
                                            <th className={t.tableHead}>Jawatan</th>
                                            <th className={t.tableHead}>Masa Mula</th>
                                            <th className={t.tableHead}>Masa Tamat</th>
                                            <th className={t.tableHead}>Nama</th>
                                            <th className={t.tableHead}>No K/P</th>
                                            <th className={t.tableHead}>No Tel</th>
                                            <th className={t.tableHead}>Parti</th>
                                            <th className={t.tableHead}><span className="sr-only">Tindakan</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {saluran.slot.map((slot) => {
                                            const ubah = (patch) => onChangeSlot(pusat.id, saluran.id, slot.id, patch);

                                            return (
                                                <tr key={slot.id} className={t.tableRow}>
                                                    <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{labelSlot(slot)}</td>
                                                    <td className={t.tableCell}>
                                                        <MedanTeks t={t} slot={slot} name="masa_mula" type="time" saving={saving} onUbah={ubah} className="w-32" />
                                                    </td>
                                                    <td className={t.tableCell}>
                                                        <MedanTeks t={t} slot={slot} name="masa_tamat" type="time" saving={saving} onUbah={ubah} className="w-32" />
                                                        <NotaCa t={t} slot={slot} />
                                                    </td>
                                                    <td className={t.tableCell}>
                                                        <MedanTeks t={t} slot={slot} name="petugas_nama" placeholder="Nama" saving={saving} onUbah={ubah} className="min-w-[140px]" />
                                                    </td>
                                                    <td className={t.tableCell}>
                                                        <MedanTeks t={t} slot={slot} name="petugas_kp" placeholder="No K/P" saving={saving} onUbah={ubah} className="min-w-[130px]" />
                                                    </td>
                                                    <td className={t.tableCell}>
                                                        <MedanTeks t={t} slot={slot} name="petugas_tel" placeholder="No Tel" saving={saving} onUbah={ubah} className="min-w-[120px]" />
                                                    </td>
                                                    <td className={t.tableCell}>
                                                        <MedanParti t={t} slot={slot} parti={parti} saving={saving} onUbah={ubah} className="min-w-[150px]" />
                                                    </td>
                                                    <td className={t.tableCell}>
                                                        <ButangBuang
                                                            slot={slot}
                                                            boleh={saluran.slot.length > 1}
                                                            saving={saving}
                                                            buanging={buangingSlot === slot.id}
                                                            onBuang={buangSlot}
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </DragScroll>
                        </div>

                        {/* <md: satu kad bertindan setiap slot. Jadual 8 lajur
                            penuh input tidak boleh digunakan pada telefon —
                            setiap petugas akan memerlukan seretan mendatar
                            berulang kali. Medan dikongsi dengan jadual di atas. */}
                        <div className="md:hidden space-y-3">
                            {saluran.slot.map((slot) => {
                                const ubah = (patch) => onChangeSlot(pusat.id, saluran.id, slot.id, patch);

                                return (
                                    <div key={slot.id} className="rounded-lg border border-slate-200 p-3">
                                        <div className="flex items-center justify-between gap-2 mb-2">
                                            <span className="text-sm font-semibold text-slate-900">{labelSlot(slot)}</span>
                                            <ButangBuang
                                                slot={slot}
                                                boleh={saluran.slot.length > 1}
                                                saving={saving}
                                                buanging={buangingSlot === slot.id}
                                                onBuang={buangSlot}
                                                className="-m-1 p-1"
                                            />
                                        </div>

                                        <div className="grid grid-cols-2 gap-2 mb-2">
                                            <div>
                                                <label className={t.label}>Masa Mula</label>
                                                <MedanTeks t={t} slot={slot} name="masa_mula" type="time" saving={saving} onUbah={ubah} />
                                            </div>
                                            <div>
                                                <label className={t.label}>Masa Tamat</label>
                                                <MedanTeks t={t} slot={slot} name="masa_tamat" type="time" saving={saving} onUbah={ubah} />
                                                <NotaCa t={t} slot={slot} />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <div>
                                                <label className={t.label}>Nama</label>
                                                <MedanTeks t={t} slot={slot} name="petugas_nama" placeholder="Nama" saving={saving} onUbah={ubah} />
                                            </div>
                                            <div className="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label className={t.label}>No K/P</label>
                                                    <MedanTeks t={t} slot={slot} name="petugas_kp" placeholder="No K/P" saving={saving} onUbah={ubah} />
                                                </div>
                                                <div>
                                                    <label className={t.label}>No Tel</label>
                                                    <MedanTeks t={t} slot={slot} name="petugas_tel" placeholder="No Tel" saving={saving} onUbah={ubah} />
                                                </div>
                                            </div>
                                            <div>
                                                <label className={t.label}>Parti</label>
                                                <MedanParti t={t} slot={slot} parti={parti} saving={saving} onUbah={ubah} />
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>

            <button type="button" onClick={tambahSaluran} disabled={saving || addingSaluran} className={`${t.buttonSecondary} mt-4`}>
                {addingSaluran ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                Tambah Saluran
            </button>
        </div>
    );
}
