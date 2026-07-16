import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Download, Eye, Info, Landmark, Loader2, MapPin, RotateCcw, Vote } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import ConfirmDialog from './ConfirmDialog';

const JENIS_LABEL = { pru: 'PRU', prn: 'PRN', prk: 'PRK' };
const STATUS_BADGE = {
    draft: 'bg-amber-100 text-amber-800',
    published: 'bg-emerald-100 text-emerald-800',
};
const KAWASAN_BADGE = {
    parlimen: 'bg-sky-100 text-sky-800',
    dun: 'bg-violet-100 text-violet-800',
};
const SUMBER_LABEL = { manual: 'Manual', scoresheet: 'Scoresheet' };

/**
 * Tab "Papar" — sejarah rekod Borang 14 disimpan, dengan penapis lata
 * Negeri > Parlimen > DUN.
 *
 * Kontrak backend sebenar (app/Http/Controllers/Borang14Controller.php,
 * method senarai/pdf/revert) berbeza daripada brief Task 11 asal:
 *   - Parameter kedua ialah `bandar_id` (BUKAN `parlimen_id` seperti dalam
 *     brief) — jadual `bandar` = Parlimen dalam domain sistem ini.
 *   - Baris yang dipulangkan TIADA kawasan_id/negeri_id/parlimen_id/kadun_id
 *     atau has_snapshot — hanya { id, tahun, jenis_pr, kawasan_type,
 *     kawasan_nama, penjuru, status, source, source_filename, needs_review,
 *     published_at }. ID kawasan diselesaikan di sini secara nama, ikut
 *     senarai negeri/parlimen/kadun yang sedia ada pada props halaman.
 *   - route('pilihanraya.borang-14.pdf') hanya menyokong `kadun_id` (peringkat
 *     DUN sahaja) — bukan `kawasan_type`/`kawasan_id` seperti brief nyatakan.
 *     Rekod peringkat Parlimen tidak boleh dimuat turun PDF melalui laluan ini.
 *   - Revert tiada penanda has_snapshot pada baris; butang Revert sentiasa
 *     dipaparkan dan pelayan memulangkan 422 dengan mesej BM jika tiada
 *     snapshot untuk dipulihkan.
 */
export default function PaparTab({ negeriList, parlimenList, kadunList, onOpenKeyin }) {
    const { t } = usePilihanrayaTheme();
    const [negeriId, setNegeriId] = useState('');
    const [parlimenId, setParlimenId] = useState('');
    const [kadunId, setKadunId] = useState('');
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [revertTarget, setRevertTarget] = useState(null);
    const [reverting, setReverting] = useState(false);

    const parlimenOptions = negeriId
        ? parlimenList.filter((p) => String(p.negeri_id) === String(negeriId)) : [];
    const kadunOptions = parlimenId
        ? kadunList.filter((k) => String(k.bandar_id) === String(parlimenId)) : [];

    // Senarai Parlimen & DUN dalam negeri terpilih — dipakai untuk padan
    // kawasan_nama pulangan pelayan kembali kepada ID sebenar (lihat nota di
    // atas: pelayan tidak pulangkan ID kawasan bagi setiap baris).
    const negeriBandarIds = useMemo(
        () => new Set(parlimenList.filter((p) => String(p.negeri_id) === String(negeriId)).map((p) => String(p.id))),
        [parlimenList, negeriId],
    );
    const kadunDalamNegeri = useMemo(
        () => kadunList.filter((k) => negeriBandarIds.has(String(k.bandar_id))),
        [kadunList, negeriBandarIds],
    );

    const resolveKawasan = (r) => {
        if (r.kawasan_type === 'dun') {
            const match = kadunDalamNegeri.find((k) => k.nama === r.kawasan_nama);
            if (!match) return null;
            return { negeriId, parlimenId: String(match.bandar_id), kadunId: String(match.id) };
        }
        const match = parlimenList.find((p) => String(p.negeri_id) === String(negeriId) && p.nama === r.kawasan_nama);
        if (!match) return null;
        return { negeriId, parlimenId: String(match.id), kadunId: '' };
    };

    const load = () => {
        if (!negeriId) { setRows([]); return; }
        setLoading(true); setError(null);
        axios.get(route('pilihanraya.borang-14.senarai'), {
            params: {
                negeri_id: negeriId,
                bandar_id: parlimenId || undefined,
                kadun_id: kadunId || undefined,
            },
        })
            .then(({ data }) => setRows(
                // Pelayan sudah tersusun; kekalkan susunan client-side sebagai jaminan tambahan.
                [...data.rows].sort((a, b) =>
                    b.tahun - a.tahun
                    || a.jenis_pr.localeCompare(b.jenis_pr)
                    || a.kawasan_type.localeCompare(b.kawasan_type)),
            ))
            .catch(() => setError('Gagal memuatkan senarai Borang 14.'))
            .finally(() => setLoading(false));
    };

    useEffect(load, [negeriId, parlimenId, kadunId]); // eslint-disable-line react-hooks/exhaustive-deps

    const openPapar = (r) => {
        const kw = resolveKawasan(r);
        if (!kw) {
            setError(`Tidak dapat mengenal pasti kawasan "${r.kawasan_nama}" untuk paparan di Keyin.`);
            return;
        }
        onOpenKeyin({
            negeriId: kw.negeriId,
            parlimenId: kw.parlimenId,
            kadunId: kw.kadunId,
            kawasanType: r.kawasan_type,
            jenisPr: r.jenis_pr,
            tahun: r.tahun,
            formId: r.id,
        });
    };

    const openPdf = (r) => {
        const kw = resolveKawasan(r);
        if (!kw || !kw.kadunId) {
            setError('Muat turun PDF ketika ini hanya disokong untuk rekod peringkat DUN.');
            return;
        }
        window.open(route('pilihanraya.borang-14.pdf', {
            kadun_id: kw.kadunId, jenis_pr: r.jenis_pr, tahun: r.tahun, penjuru: r.penjuru,
        }), '_blank');
    };

    const revert = async () => {
        setReverting(true);
        try {
            await axios.post(route('pilihanraya.borang-14.revert'), { form_id: revertTarget.id });
            setRevertTarget(null);
            load();
        } catch (e) {
            setError(e.response?.data?.message || 'Gagal memulihkan snapshot.');
            setRevertTarget(null);
        } finally {
            setReverting(false);
        }
    };

    return (
        <>
            <div className={`${t.cardTight} mb-4`}>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" /> Negeri</span></label>
                        <select value={negeriId} className={t.input}
                            onChange={(e) => { setNegeriId(e.target.value); setParlimenId(''); setKadunId(''); }}>
                            <option value="">Pilih Negeri</option>
                            {negeriList.map((n) => <option key={n.id} value={n.id}>{n.nama}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><Landmark className="h-3.5 w-3.5" /> Parlimen (pilihan)</span></label>
                        <select value={parlimenId} className={t.input} disabled={!negeriId}
                            onChange={(e) => { setParlimenId(e.target.value); setKadunId(''); }}>
                            <option value="">Semua Parlimen</option>
                            {parlimenOptions.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><Vote className="h-3.5 w-3.5" /> DUN (pilihan)</span></label>
                        <select value={kadunId} className={t.input} disabled={!parlimenId}
                            onChange={(e) => setKadunId(e.target.value)}>
                            <option value="">Semua DUN</option>
                            {kadunOptions.map((k) => <option key={k.id} value={k.id}>{k.nama}</option>)}
                        </select>
                    </div>
                </div>
            </div>

            {!negeriId && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Pilih Negeri untuk memaparkan senarai Borang 14. Parlimen sahaja: rekod Parlimen itu dan semua DUN di bawahnya.</span>
                </div>
            )}
            {error && <div className="bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3 text-sm mb-4">{error}</div>}
            {loading && (
                <div className={`flex items-center gap-2 ${t.subtext} py-8 justify-center`}>
                    <Loader2 className="h-5 w-5 animate-spin" /> Memuatkan…
                </div>
            )}

            {negeriId && !loading && (
                <div className={`${t.cardTight} overflow-x-auto`}>
                    <table className="min-w-full border-collapse">
                        <thead>
                            <tr>
                                <th className={t.tableHead}>Tahun</th>
                                <th className={t.tableHead}>Jenis PR</th>
                                <th className={t.tableHead}>Kawasan</th>
                                <th className={`${t.tableHead} text-right`}>Penjuru</th>
                                <th className={t.tableHead}>Status</th>
                                <th className={t.tableHead}>Sumber</th>
                                <th className={`${t.tableHead} text-right`}>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr><td colSpan={7} className={`${t.tableCell} text-center py-8 ${t.subtext}`}>Tiada rekod Borang 14 untuk penapis ini.</td></tr>
                            )}
                            {rows.map((r) => {
                                const isDun = r.kawasan_type === 'dun';
                                return (
                                    <tr key={r.id} className={t.tableRow}>
                                        <td className={`${t.tableCell} font-semibold`}>{r.tahun}</td>
                                        <td className={t.tableCell}>{JENIS_LABEL[r.jenis_pr] ?? r.jenis_pr}</td>
                                        <td className={t.tableCell}>
                                            <span className={`${t.badge} ${KAWASAN_BADGE[r.kawasan_type]} mr-2`}>{r.kawasan_type.toUpperCase()}</span>
                                            {r.kawasan_nama}
                                            {r.needs_review && <span className={`${t.badge} bg-amber-100 text-amber-800 ml-2`}>Perlu Semakan</span>}
                                        </td>
                                        <td className={`${t.tableCell} text-right`}>{r.penjuru}</td>
                                        <td className={t.tableCell}>
                                            <span className={`${t.badge} ${STATUS_BADGE[r.status]}`}>{r.status === 'draft' ? 'DRAF' : 'DITERBITKAN'}</span>
                                            {r.status === 'published' && r.published_at && (
                                                <div className="text-xs text-slate-400 mt-0.5">{new Date(r.published_at).toLocaleDateString('ms-MY')}</div>
                                            )}
                                        </td>
                                        <td className={t.tableCell} title={r.source_filename || undefined}>{SUMBER_LABEL[r.source] ?? r.source}</td>
                                        <td className={`${t.tableCell} text-right whitespace-nowrap`}>
                                            <button type="button" title="Papar dalam Keyin"
                                                onClick={() => openPapar(r)}
                                                className="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900 mr-3">
                                                <Eye className="h-4 w-4" /> Papar
                                            </button>
                                            <button type="button"
                                                title={isDun ? 'Muat turun PDF' : 'PDF hanya disokong untuk rekod peringkat DUN'}
                                                onClick={() => isDun && openPdf(r)}
                                                disabled={!isDun}
                                                className="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900 mr-3 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:text-slate-600">
                                                <Download className="h-4 w-4" /> PDF
                                            </button>
                                            <button type="button" title="Pulih keadaan sebelum scoresheet menimpa"
                                                onClick={() => setRevertTarget(r)}
                                                className="inline-flex items-center gap-1 text-sm text-rose-600 hover:text-rose-700">
                                                <RotateCcw className="h-4 w-4" /> Revert
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            <ConfirmDialog
                open={!!revertTarget}
                title="Pulih dari snapshot?"
                confirmLabel="Revert"
                busy={reverting}
                onClose={() => setRevertTarget(null)}
                onConfirm={revert}
            >
                {revertTarget && (
                    <p>
                        Undi, struktur dan pemetaan parti untuk <strong>{revertTarget.kawasan_nama} · {JENIS_LABEL[revertTarget.jenis_pr]} {revertTarget.tahun}</strong> akan
                        dikembalikan kepada keadaan sebelum scoresheet menimpanya. Tindakan ini tidak boleh dibuat asal.
                        Jika tiada snapshot tersimpan, permintaan ini akan gagal dengan mesej ralat.
                    </p>
                )}
            </ConfirmDialog>
        </>
    );
}
