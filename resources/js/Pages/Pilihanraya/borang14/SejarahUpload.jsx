import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { Download, FileClock, Loader2, RotateCcw, ShieldAlert, Trash2 } from 'lucide-react';
import useDragScroll from '@/Hooks/useDragScroll';
import ConfirmDialog from './ConfirmDialog';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';

/**
 * Sejarah muat naik scoresheet.
 *
 * Panel ini wujud kerana muat naik dahulunya tidak meninggalkan sebarang jejak:
 * tiada senarai apa yang dimuat naik, oleh siapa, untuk kerusi mana, dan tiada
 * cara membaca semula fail asal apabila angka dipertikaikan.
 *
 * Lajur "Jumlah" sengaja memaparkan angka BERCETAK di sebelah angka DICAMPUR.
 * Perbandingan itulah nilai auditnya — kegagalan produksi (98 dicampur lawan
 * 4,471 bercetak) akan terpampang terus di sini, bukan tersembunyi.
 */

/** Angka yang tidak diketahui dipaparkan sebagai "—", TIDAK PERNAH sebagai 0. */
function num(v) {
    return v === null || v === undefined ? '—' : Number(v).toLocaleString('ms-MY');
}

function undiList(undi) {
    if (!Array.isArray(undi) || undi.length === 0) return '—';
    return undi.map((v) => num(v)).join(' / ');
}

const SOURCE_LABEL = {
    deterministic: { text: 'Baca terus', hint: 'Dibaca terus daripada borang SPR 760 — boleh dibuktikan, tiada AI.', cls: 'bg-emerald-50 text-emerald-700 border-emerald-300' },
    ai: { text: 'AI', hint: 'Dibaca oleh Claude AI — sahkan angka sebelum diterbitkan.', cls: 'bg-sky-50 text-sky-700 border-sky-300' },
};

export default function SejarahUpload({ refreshKey = 0 }) {
    const { t } = usePilihanrayaTheme();
    const scrollRef = useDragScroll();
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [hapusTarget, setHapusTarget] = useState(null);
    const [hapusing, setHapusing] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await axios.get(route('pilihanraya.borang-14.upload.sejarah'));
            setRows(data.rows || []);
        } catch {
            setError('Gagal memuatkan sejarah muat naik.');
        } finally {
            setLoading(false);
        }
    }, []);

    // refreshKey berubah selepas setiap commit berjaya, jadi baris baharu muncul
    // tanpa pengguna perlu memuat semula halaman.
    useEffect(() => { load(); }, [load, refreshKey]);

    // Memadam baris sejarah TIDAK menyentuh undi yang telah dimasukkan ke dalam
    // Borang 14 — gunakan Padam pada tab Papar untuk membuang keputusan itu.
    const hapus = async () => {
        setHapusing(true);
        try {
            await axios.delete(route('pilihanraya.borang-14.upload.hapus', hapusTarget.id));
            setHapusTarget(null);
            load();
        } catch (e) {
            setError(e.response?.status === 403
                ? 'Anda tiada kebenaran memadam rekod muat naik ini.'
                : 'Gagal memadam rekod muat naik.');
            setHapusTarget(null);
        } finally {
            setHapusing(false);
        }
    };

    return (
        <div className={`${t.card} space-y-3`}>
            <div className="flex items-center justify-between gap-3">
                <div>
                    <div className={`text-sm font-bold ${t.text} inline-flex items-center gap-1.5`}>
                        <FileClock className="h-4 w-4" /> Sejarah Muat Naik
                    </div>
                    <div className={`text-xs ${t.subtext} mt-1`}>
                        Setiap scoresheet yang disahkan disimpan di sini bersama fail asalnya.
                    </div>
                </div>
                <button type="button" onClick={load} disabled={loading} className={t.buttonSecondary}>
                    {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RotateCcw className="h-4 w-4" />} Muat Semula
                </button>
            </div>

            {error && (
                <div className="text-sm bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3">{error}</div>
            )}

            {!loading && !error && rows.length === 0 && (
                <div className={`text-sm ${t.subtext} py-6 text-center`}>Belum ada scoresheet dimuat naik.</div>
            )}

            {rows.length > 0 && (
                <div ref={scrollRef} className="overflow-x-auto cursor-grab">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className={`text-left ${t.subtext} border-b border-slate-200`}>
                                <th className="py-2 pr-4 font-medium whitespace-nowrap">Tarikh</th>
                                <th className="py-2 pr-4 font-medium whitespace-nowrap">Kawasan</th>
                                <th className="py-2 pr-4 font-medium whitespace-nowrap">Fail</th>
                                <th className="py-2 pr-4 font-medium whitespace-nowrap">PR</th>
                                <th className="py-2 pr-4 font-medium whitespace-nowrap">Sumber</th>
                                <th className="py-2 pr-4 font-medium whitespace-nowrap">Baris</th>
                                <th className="py-2 pr-4 font-medium whitespace-nowrap">Undi (dicetak / dicampur)</th>
                                <th className="py-2 pr-4 font-medium whitespace-nowrap">Pemilih</th>
                                <th className="py-2 pr-4 font-medium whitespace-nowrap">Oleh</th>
                                <th className="py-2 pr-4 font-medium whitespace-nowrap">Fail asal</th>
                                <th className="py-2 font-medium whitespace-nowrap">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => {
                                const src = SOURCE_LABEL[r.source] || SOURCE_LABEL.ai;
                                const dicetak = r.totals?.dicetak;
                                const dikira = r.totals?.dikira;
                                // Percanggahan antara angka bercetak dan angka
                                // dicampur ialah isyarat paling terus bahawa
                                // bacaan tidak lengkap — ditonjolkan, bukan dikira semula.
                                const beza = dicetak && dikira
                                    && JSON.stringify(dicetak.undi) !== JSON.stringify(dikira.undi);

                                return (
                                    <tr key={r.id} className="border-b border-slate-100 align-top">
                                        <td className="py-2 pr-4 whitespace-nowrap">{r.tarikh || '—'}</td>
                                        <td className="py-2 pr-4 whitespace-nowrap">
                                            <div className={t.text}>{r.kawasan}</div>
                                            <div className={`text-xs ${t.subtext}`}>{r.negeri}</div>
                                        </td>
                                        <td className="py-2 pr-4 max-w-[18rem] truncate" title={r.nama_fail}>{r.nama_fail}</td>
                                        <td className="py-2 pr-4 whitespace-nowrap">{(r.jenis_pr || '').toUpperCase()} {r.tahun || ''}</td>
                                        <td className="py-2 pr-4 whitespace-nowrap">
                                            <span title={src.hint} className={`inline-block rounded border px-1.5 py-0.5 text-xs ${src.cls}`}>{src.text}</span>
                                        </td>
                                        <td className="py-2 pr-4 whitespace-nowrap">
                                            {num(r.row_count)}
                                            {r.saluran_count ? <span className={`text-xs ${t.subtext}`}> / {num(r.saluran_count)}</span> : null}
                                        </td>
                                        <td className="py-2 pr-4 whitespace-nowrap">
                                            <div>{undiList(dicetak?.undi)}</div>
                                            <div className={`text-xs ${beza ? 'text-rose-700 font-semibold' : t.subtext}`}>
                                                {undiList(dikira?.undi)}
                                            </div>
                                        </td>
                                        <td className="py-2 pr-4 whitespace-nowrap">{num(r.totals?.pemilih)}</td>
                                        <td className="py-2 pr-4 whitespace-nowrap">
                                            {r.oleh}
                                            {r.needs_review && (
                                                <span title="Ditanda perlu semakan" className="ml-1.5 inline-flex align-middle text-amber-600">
                                                    <ShieldAlert className="h-3.5 w-3.5" />
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4 whitespace-nowrap">
                                            {r.boleh_muat_turun ? (
                                                <a href={route('pilihanraya.borang-14.upload.fail', r.id)}
                                                    className="inline-flex items-center gap-1 text-emerald-700 hover:underline">
                                                    <Download className="h-3.5 w-3.5" /> Muat turun
                                                </a>
                                            ) : (
                                                <span className={`text-xs ${t.subtext}`}>Tiada fail</span>
                                            )}
                                        </td>
                                        <td className="py-2 whitespace-nowrap">
                                            <button type="button" title="Padam rekod muat naik ini"
                                                onClick={() => setHapusTarget(r)}
                                                className="inline-flex items-center gap-1 text-rose-600 hover:text-rose-700">
                                                <Trash2 className="h-3.5 w-3.5" /> Padam
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
                open={!!hapusTarget}
                title="Padam rekod muat naik?"
                confirmLabel="Padam"
                busy={hapusing}
                onClose={() => setHapusTarget(null)}
                onConfirm={hapus}
            >
                {hapusTarget && (
                    <p>
                        Baris sejarah untuk <strong>{hapusTarget.nama_fail}</strong> dan fail scoresheet asalnya
                        akan dibuang secara kekal. Undi yang telah dimasukkan ke dalam Borang 14
                        <strong> tidak</strong> disentuh — gunakan Padam pada tab Papar jika anda mahu membuang keputusan itu juga.
                    </p>
                )}
            </ConfirmDialog>
        </div>
    );
}
