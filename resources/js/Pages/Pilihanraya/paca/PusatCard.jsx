import { useState } from 'react';
import { Check, Copy, Loader2, Plus, Trash2 } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import DragScroll from '../analisa/DragScroll';

/**
 * Satu kad Pusat Mengundi: butiran Ketua PACABA, pautan awam per-Pusat
 * (token unik — lihat PacaPublicController), dan setiap Saluran dengan
 * jadual slot (PA1..PAn + CA). `onChange`/`onTambahSaluran`/`onTambahSlot`
 * semuanya diselaraskan oleh Paca.jsx supaya penggabungan (merge) pokok
 * selepas tindakan server berlaku di SATU tempat sahaja.
 */
export default function PusatCard({ pusat, saving, parti = [], onChangePusat, onChangeSlot, onTambahSaluran, onTambahSlot, onBuangSlot }) {
    const { t } = usePilihanrayaTheme();
    const [copied, setCopied] = useState(false);
    const [addingSaluran, setAddingSaluran] = useState(false);
    const [addingSlotFor, setAddingSlotFor] = useState(null);
    const [buangingSlot, setBuangingSlot] = useState(null);

    const buangSlot = async (slot) => {
        const adaData = slot.petugas_nama || slot.petugas_kp || slot.petugas_tel || slot.petugas_parti;
        if (adaData && !window.confirm(`Buang slot ${slot.jawatan}? Butiran petugas yang telah diisi akan hilang.`)) return;
        setBuangingSlot(slot.id);
        try {
            await onBuangSlot(slot.id);
        } finally {
            setBuangingSlot(null);
        }
    };

    const salinPautan = async () => {
        try {
            await navigator.clipboard.writeText(pusat.public_url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Papan klip tidak tersedia (mis. bukan konteks selamat) — pautan
            // masih boleh disalin secara manual daripada teks yang dipaparkan.
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
            <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h3 className="text-lg font-semibold text-slate-900">{pusat.pusat}</h3>
                    <p className={`${t.subtext} text-xs mt-0.5`}>{pusat.dm}</p>
                </div>
                <button type="button" onClick={salinPautan} className={t.buttonSecondary}>
                    {copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Copy className="h-4 w-4" />}
                    {copied ? 'Disalin!' : 'Salin Pautan Awam'}
                </button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                <div>
                    <label className={t.label}>Nama Ketua PACABA</label>
                    <input
                        className={t.input}
                        value={pusat.ketua_nama ?? ''}
                        disabled={saving}
                        onChange={(e) => onChangePusat(pusat.id, { ketua_nama: e.target.value })}
                        placeholder="Nama Ketua PACABA"
                    />
                </div>
                <div>
                    <label className={t.label}>No Tel Ketua PACABA</label>
                    <input
                        className={t.input}
                        value={pusat.ketua_tel ?? ''}
                        disabled={saving}
                        onChange={(e) => onChangePusat(pusat.id, { ketua_tel: e.target.value })}
                        placeholder="No Telefon"
                    />
                </div>
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
                                    {saluran.slot.map((slot) => (
                                        <tr key={slot.id} className={t.tableRow}>
                                            <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{slot.jawatan}</td>
                                            <td className={t.tableCell}>
                                                <input
                                                    type="time"
                                                    className={`${t.input} w-32`}
                                                    value={slot.masa_mula ?? ''}
                                                    disabled={saving}
                                                    onChange={(e) => onChangeSlot(pusat.id, saluran.id, slot.id, { masa_mula: e.target.value })}
                                                />
                                            </td>
                                            <td className={t.tableCell}>
                                                <input
                                                    type="time"
                                                    className={`${t.input} w-32`}
                                                    value={slot.masa_tamat ?? ''}
                                                    disabled={saving}
                                                    onChange={(e) => onChangeSlot(pusat.id, saluran.id, slot.id, { masa_tamat: e.target.value })}
                                                />
                                                {slot.jawatan === 'CA' && !slot.masa_tamat && (
                                                    <p className={`${t.subtext} text-xs mt-0.5`}>kosong = selesai</p>
                                                )}
                                            </td>
                                            <td className={t.tableCell}>
                                                <input
                                                    className={`${t.input} min-w-[140px]`}
                                                    value={slot.petugas_nama ?? ''}
                                                    disabled={saving}
                                                    onChange={(e) => onChangeSlot(pusat.id, saluran.id, slot.id, { petugas_nama: e.target.value })}
                                                    placeholder="Nama"
                                                />
                                            </td>
                                            <td className={t.tableCell}>
                                                <input
                                                    className={`${t.input} min-w-[130px]`}
                                                    value={slot.petugas_kp ?? ''}
                                                    disabled={saving}
                                                    onChange={(e) => onChangeSlot(pusat.id, saluran.id, slot.id, { petugas_kp: e.target.value })}
                                                    placeholder="No K/P"
                                                />
                                            </td>
                                            <td className={t.tableCell}>
                                                <input
                                                    className={`${t.input} min-w-[120px]`}
                                                    value={slot.petugas_tel ?? ''}
                                                    disabled={saving}
                                                    onChange={(e) => onChangeSlot(pusat.id, saluran.id, slot.id, { petugas_tel: e.target.value })}
                                                    placeholder="No Tel"
                                                />
                                            </td>
                                            <td className={t.tableCell}>
                                                <select
                                                    className={`${t.input} min-w-[150px]`}
                                                    value={slot.petugas_parti ?? ''}
                                                    disabled={saving}
                                                    onChange={(e) => onChangeSlot(pusat.id, saluran.id, slot.id, { petugas_parti: e.target.value || null })}
                                                >
                                                    <option value="">— Pilih Parti —</option>
                                                    {/* Kekalkan nilai tersimpan walaupun ia tiada dalam Data Induk
                                                        (mis. data lama) supaya ia tidak hilang secara senyap. */}
                                                    {slot.petugas_parti && !parti.includes(slot.petugas_parti) && (
                                                        <option value={slot.petugas_parti}>{slot.petugas_parti}</option>
                                                    )}
                                                    {parti.map((p) => <option key={p} value={p}>{p}</option>)}
                                                </select>
                                            </td>
                                            <td className={t.tableCell}>
                                                {saluran.slot.length > 1 && (
                                                    <button
                                                        type="button"
                                                        onClick={() => buangSlot(slot)}
                                                        disabled={saving || buangingSlot === slot.id}
                                                        title={`Buang ${slot.jawatan}`}
                                                        className="inline-flex items-center justify-center text-slate-400 hover:text-red-600 disabled:opacity-50"
                                                    >
                                                        {buangingSlot === slot.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DragScroll>
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
