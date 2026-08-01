import { useCallback, useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { Radio } from 'lucide-react';

const POLL_MS = 4000;

// Nilai null/NaN bermakna TIDAK DIKETAHUI, bukan sifar — papar "—".
// Kad di sini hanya dibina bagi papan yang SEDIA (ScoreboardPayload::kadAwam),
// jadi `undi` sentiasa angka sebenar: "0" di skrin bermakna belum ada undi
// dimasukkan, bukan angka yang direka.
const fmt = (n) => (n == null || Number.isNaN(n) ? '—' : Number(n).toLocaleString('en-MY'));

// Warna parti — sandaran apabila tiada logo bagi nama itu, dan jalur atas kad.
const PARTY_COLOR = {
    PAKATAN: '#D71920', KEADILAN: '#D71920', PKR: '#D71920', DAP: '#DE0000', AMANAH: '#F58220', MUDA: '#111827',
    BARISAN: '#00529B', UMNO: '#00529B', MCA: '#003C71', MIC: '#00529B',
    PERIKATAN: '#0B6E4F', PAS: '#0B6E4F', BERSATU: '#4C1D95', PPBM: '#4C1D95',
};
const partyColor = (nama) => PARTY_COLOR[((nama || '').toUpperCase().match(/[A-Z]+/) || [''])[0]] || '#64748b';

const labelKerusi = (b) => (b.jenis === 'parlimen' ? `PARLIMEN ${b.nama}` : `DUN ${b.nama}`);

/* -------------------------------- kad ---------------------------------- */

/**
 * Satu kerusi, satu kad. Dua lajur pada telefon, jadi setiap baris calon mesti
 * muat dalam kira-kira separuh lebar skrin: logo kecil, nama dipotong, dan
 * angka undi yang kekal boleh dibaca (tabular-nums supaya digit tidak
 * bergoyang setiap tinjauan).
 */
function KadKerusi({ board }) {
    const calon = board.calon || [];
    const jalur = partyColor(calon[0]?.parti);

    return (
        <Link
            href={board.url}
            className="group flex flex-col rounded-2xl bg-white border border-slate-200 overflow-hidden shadow-sm hover:border-slate-300 hover:shadow transition"
        >
            <div className="h-1.5 shrink-0" style={{ backgroundColor: jalur }} />

            <div className="px-3 pt-2.5 pb-2 border-b border-slate-100">
                <div className="text-[10px] font-mono text-slate-400 uppercase">{board.kod}</div>
                <div className="text-xs sm:text-sm font-bold text-slate-900 leading-tight break-words">
                    {labelKerusi(board)}
                </div>
                {board.jenis === 'dun' && board.parlimen && (
                    <div className="text-[10px] text-slate-400 truncate">P. {board.parlimen}</div>
                )}
            </div>

            <div className="p-2 sm:p-3 space-y-1.5 flex-1">
                {calon.map((c) => {
                    const warna = partyColor(c.parti);
                    const mendahului = c.slot === board.leader_slot && board.total_keluar > 0;
                    return (
                        <div
                            key={c.slot}
                            className={`flex items-center gap-2 rounded-lg px-1.5 py-1 ${mendahului ? 'bg-amber-50 ring-1 ring-amber-300' : ''}`}
                        >
                            {c.logo ? (
                                <img src={c.logo} alt={c.parti} className="h-6 w-6 shrink-0 object-contain" />
                            ) : (
                                <span
                                    className="h-6 w-6 shrink-0 rounded-full"
                                    style={{ backgroundColor: warna }}
                                    aria-hidden="true"
                                />
                            )}
                            <div className="min-w-0 flex-1">
                                <div className="text-[10px] font-bold uppercase leading-none truncate" style={{ color: warna }}>
                                    {c.parti}
                                </div>
                                <div className="text-[11px] text-slate-700 truncate leading-tight">{c.calon || '—'}</div>
                            </div>
                            <div className="text-sm sm:text-base font-black text-slate-900 tabular-nums shrink-0">
                                {fmt(c.undi)}
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="px-3 py-1.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                <span className="text-[10px] uppercase tracking-wide text-slate-400">Undi Keluar</span>
                <span className="text-xs font-bold text-slate-700 tabular-nums">{fmt(board.total_keluar)}</span>
            </div>
        </Link>
    );
}

/* ------------------------------- halaman -------------------------------- */

/**
 * Senarai papan markah TERSIAR sahaja, dan hanya kerusi yang Borang 14-nya
 * hidup — papan draf dan papan tanpa sumber undi tidak pernah muncul di sini.
 */
export default function ScoreboardIndex({ boards = [] }) {
    const [senarai, setSenarai] = useState(boards);
    const [updatedAt, setUpdatedAt] = useState(boards.length ? new Date() : null);

    const fetchData = useCallback(() => {
        // Cache-buster supaya tiada lapisan pelayar/CDN menghidangkan tinjauan
        // basi pada malam keputusan.
        axios.get('/scoreboard/senarai', { params: { _t: Date.now() } })
            .then(({ data }) => { setSenarai(data.boards || []); setUpdatedAt(new Date()); })
            // Rangkaian tersekat sementara: kekalkan paparan terakhir, jangan
            // kosongkan senarai atau papar sifar.
            .catch(() => {});
    }, []);

    useEffect(() => {
        const id = setInterval(fetchData, POLL_MS);
        return () => clearInterval(id);
    }, [fetchData]);

    return (
        <>
            <Head title="Papan Markah" />
            <div className="min-h-screen bg-[#f5f6f8]">
                <header className="border-b border-slate-200 bg-white/80 backdrop-blur sticky top-0 z-10">
                    <div className="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
                        <span className="text-sm font-bold tracking-wide text-slate-900">Sistem Data Pengundi</span>
                        <span className="inline-flex items-center gap-2 text-xs text-slate-500">
                            <span className="inline-flex items-center gap-1.5 font-semibold text-emerald-600">
                                <Radio className="h-3.5 w-3.5 animate-pulse" /> Live
                            </span>
                            {updatedAt && <span className="tabular-nums">· {updatedAt.toLocaleTimeString('ms-MY')}</span>}
                        </span>
                    </div>
                </header>

                <main className="max-w-5xl mx-auto px-3 sm:px-4 py-6 sm:py-10">
                    <h1 className="text-xl sm:text-2xl font-bold text-slate-900 mb-1">Papan Markah</h1>
                    <p className="text-sm text-slate-500 mb-5">
                        Kiraan undi secara langsung dari Borang 14.
                    </p>

                    {senarai.length === 0 ? (
                        <p className="text-sm text-slate-500">Tiada papan markah disiarkan buat masa ini.</p>
                    ) : (
                        // Dua lajur pada telefon — permintaan pemilik sistem.
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 items-start">
                            {senarai.map((b) => <KadKerusi key={b.kod} board={b} />)}
                        </div>
                    )}
                </main>

                <footer className="max-w-5xl mx-auto px-4 pb-10 text-center">
                    <p className="text-xs text-slate-400">Dikuasakan oleh <span className="font-semibold text-slate-500">SISDA</span></p>
                </footer>
            </div>
        </>
    );
}
