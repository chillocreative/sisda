import { CheckCircle2, CircleDashed } from 'lucide-react';

/**
 * Papan pemantauan status pendaftaran PACA bagi kerusi terpilih.
 *
 * Semua angka diterbitkan terus daripada `draft` (pusat->saluran->slot) yang
 * sudah dimuatkan oleh PacaEditor — tiada panggilan server, jadi ia sentiasa
 * sepadan dengan suntingan yang admin sedang taip (walaupun belum Simpan).
 *
 * Takrifan (lihat perbincangan dengan pengguna):
 *  - Satu slot dikira DIDAFTARKAN apabila ia mempunyai Nama.
 *  - Satu Pusat LENGKAP apabila SEMUA slotnya mempunyai Nama.
 * `petugas_nama` yang null/kosong = 0 didaftarkan (bukan diandai sebagai isi).
 */

const slotTerisi = (slot) => ((slot.petugas_nama ?? '').toString().trim() !== '');

function kiraPusat(pusat) {
    let terisi = 0;
    let jumlah = 0;
    const saluran = pusat.saluran.map((s) => {
        const t = s.slot.filter(slotTerisi).length;
        const j = s.slot.length;
        terisi += t;
        jumlah += j;
        return { id: s.id, label: s.label, terisi: t, jumlah: j };
    });
    return { terisi, jumlah, saluran, lengkap: jumlah > 0 && terisi === jumlah };
}

// Warna cip Saluran ikut kemajuan: hijau=penuh, kuning=separa, kelabu=kosong.
function warnaCip(terisi, jumlah) {
    if (jumlah > 0 && terisi === jumlah) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (terisi > 0) return 'bg-amber-50 text-amber-700 border-amber-200';
    return 'bg-slate-50 text-slate-500 border-slate-200';
}

export default function RingkasanPaca({ pusatList, onLompat }) {
    if (!pusatList || pusatList.length === 0) return null;

    const ringkas = pusatList.map((p) => ({ id: p.id, pusat: p.pusat, dm: p.dm, ...kiraPusat(p) }));
    const jumlahPusat = ringkas.length;
    const lengkap = ringkas.filter((r) => r.lengkap).length;
    const belum = jumlahPusat - lengkap;
    const totalTerisi = ringkas.reduce((a, r) => a + r.terisi, 0);
    const totalSlot = ringkas.reduce((a, r) => a + r.jumlah, 0);
    const peratus = totalSlot > 0 ? Math.round((totalTerisi / totalSlot) * 100) : 0;

    return (
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-5">
            <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h3 className="text-base font-semibold text-slate-900">Status Pendaftaran PACA</h3>
                    <p className="text-xs text-slate-500 mt-0.5">
                        {totalTerisi} / {totalSlot} petugas didaftarkan ({peratus}%)
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold">
                        <CheckCircle2 className="h-3.5 w-3.5" /> {lengkap} Lengkap
                    </span>
                    <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-xs font-semibold">
                        <CircleDashed className="h-3.5 w-3.5" /> {belum} Belum Lengkap
                    </span>
                </div>
            </div>

            {/* Bar kemajuan keseluruhan */}
            <div className="h-2 w-full rounded-full bg-slate-100 overflow-hidden mb-5">
                <div className="h-full bg-emerald-500 transition-all" style={{ width: `${peratus}%` }} />
            </div>

            <ul className="divide-y divide-slate-100">
                {ringkas.map((r) => (
                    <li key={r.id}>
                        <button
                            type="button"
                            onClick={() => onLompat?.(r.id)}
                            className="w-full text-left py-3 flex flex-col gap-2 hover:bg-slate-50 rounded-lg px-2 -mx-2 transition"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="text-sm font-semibold text-slate-900 truncate">{r.pusat}</div>
                                    {r.dm && <div className="text-xs text-slate-400 truncate">{r.dm}</div>}
                                </div>
                                <div className="flex items-center gap-2 shrink-0">
                                    <span className="text-xs font-medium text-slate-500 tabular-nums">{r.terisi}/{r.jumlah}</span>
                                    {r.lengkap ? (
                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold">
                                            <CheckCircle2 className="h-3 w-3" /> Lengkap
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-xs font-semibold">
                                            <CircleDashed className="h-3 w-3" /> Belum
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {r.saluran.map((s) => (
                                    <span
                                        key={s.id}
                                        className={`inline-flex items-center px-2 py-0.5 rounded-md border text-[11px] font-medium tabular-nums ${warnaCip(s.terisi, s.jumlah)}`}
                                        title={`Saluran ${s.label}: ${s.terisi} daripada ${s.jumlah} petugas`}
                                    >
                                        S{s.label} {s.terisi}/{s.jumlah}
                                    </span>
                                ))}
                            </div>
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
