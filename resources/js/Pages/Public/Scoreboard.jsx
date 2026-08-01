import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, CheckCircle2, Crown, Radio } from 'lucide-react';

/* ------------------------------- helpers ------------------------------- */

const POLL_MS = 4000;
// Nilai null/NaN bermakna TIDAK DIKETAHUI, bukan sifar — papar "—" supaya
// papan markah tidak mendakwa "0" bagi angka yang sebenarnya belum ada.
const fmt = (n) => (n == null || Number.isNaN(n) ? '—' : Number(n).toLocaleString('en-MY'));

// Papan boleh milik DUN ATAU Parlimen. Bagi kerusi Parlimen, `dun` memang null
// (Borang14Reference::deriveFromDptForBandar) — mengekod "DUN {dun}" secara
// tetap akan mencetak teks "null". Label mesti mengikut jenis kerusi.
const labelKerusi = (d) => {
    if (d?.dun) return `DUN ${d.dun}`;
    if (d?.parlimen) return `Parlimen ${d.parlimen}`;
    return 'Kerusi';
};

// Rangkaian konteks sebelum label kerusi. Nama Parlimen digugurkan pada papan
// Parlimen supaya ia tidak muncul dua kali.
const konteksKerusi = (d) => [d?.negeri, d?.dun ? d?.parlimen : null].filter(Boolean);

// Coalition / party colours. Names carry the coalition (e.g. "PAKATAN HARAPAN")
// so match on the leading word; PH stays red, BN blue.
const PARTY_COLOR = {
    PAKATAN: '#D71920', KEADILAN: '#D71920', PKR: '#D71920', DAP: '#DE0000', AMANAH: '#F58220', MUDA: '#111827',
    BARISAN: '#00529B', UMNO: '#00529B', MCA: '#003C71', MIC: '#00529B',
    PERIKATAN: '#0B6E4F', PAS: '#0B6E4F', BERSATU: '#4C1D95', PPBM: '#4C1D95',
};
const partyColor = (nama) => PARTY_COLOR[((nama || '').toUpperCase().match(/[A-Z]+/) || [''])[0]] || '#64748b';

/* ---------------------------- share tug-bar ---------------------------- */

// Signature element: one full-width bar split by live vote share. The seam
// between colours is where the race stands right now.
function ShareBar({ rows, totalKeluar }) {
    if (totalKeluar <= 0) {
        return (
            <div className="rounded-full h-3 bg-slate-200 overflow-hidden" aria-hidden="true" />
        );
    }
    return (
        <div className="flex rounded-full h-3 overflow-hidden shadow-inner bg-slate-200">
            {rows.map((r) => {
                const share = (r.undi / totalKeluar) * 100;
                if (share <= 0) return null;
                return (
                    <div
                        key={r.slot}
                        className="h-full transition-[width] duration-700 ease-out"
                        style={{ width: `${share}%`, backgroundColor: partyColor(r.parti) }}
                        title={`${r.parti}: ${share.toFixed(1)}%`}
                    />
                );
            })}
        </div>
    );
}

/* ------------------------------- liputan -------------------------------- */

// `liputan` is null for DUN boards and standalone-PRU Parlimen boards — those
// read votes straight off their own form and have no partial-coverage concept,
// so this renders nothing for them (unchanged from today). It is only present
// on a Parlimen roll-up: the totals below are SUMMED across linked DUN forms,
// and this is the only line telling a voter whether that sum is complete.
// `jumlah === 0` (no DUN form linked yet) is a real state too, but "0 daripada
// 0 DUN melapor" reads as a typo to a voter — worded separately instead.
//
// Sized to compete with the numbers below it, not caption them: this is the
// PUBLIC board, read by a voter with zero other context, possibly projected
// on a wall on results night. A small line under a giant vote count reads as
// final regardless of colour — so weight goes up to the seat-title register
// (text-xl+/font-black/border-2), heavier than the owner board's copy of
// this same component, deliberately.
function LiputanBadge({ liputan }) {
    if (liputan == null) return null;
    const { melapor, jumlah } = liputan;

    if (jumlah === 0) {
        return (
            <div className="rounded-2xl bg-amber-100 border-2 border-amber-400 text-amber-900 px-5 py-4 sm:py-5 text-xl sm:text-2xl font-black uppercase tracking-wide flex items-center justify-center gap-3 text-center">
                <AlertTriangle className="h-6 w-6 sm:h-7 sm:w-7 shrink-0" />
                <span>SEMENTARA · Belum ada borang DUN dipautkan lagi</span>
            </div>
        );
    }

    if (melapor < jumlah) {
        return (
            <div className="rounded-2xl bg-amber-100 border-2 border-amber-400 text-amber-900 px-5 py-4 sm:py-5 text-xl sm:text-2xl font-black uppercase tracking-wide flex items-center justify-center gap-3 text-center">
                <AlertTriangle className="h-6 w-6 sm:h-7 sm:w-7 shrink-0" />
                <span>SEMENTARA · {melapor} daripada {jumlah} DUN melapor</span>
            </div>
        );
    }

    return (
        <div className="rounded-2xl bg-emerald-100 border-2 border-emerald-400 text-emerald-900 px-5 py-4 sm:py-5 text-xl sm:text-2xl font-black uppercase tracking-wide flex items-center justify-center gap-3 text-center">
            <CheckCircle2 className="h-6 w-6 sm:h-7 sm:w-7 shrink-0" />
            <span>LENGKAP · Semua {jumlah} DUN telah melapor</span>
        </div>
    );
}

/* ----------------------------- the board ------------------------------- */

function Board({ data }) {
    const { rows, total_keluar: totalKeluar, total_berdaftar: totalBerdaftar, leader_slot: leaderSlot } = data;
    // total_berdaftar boleh jadi null secara SAH (roll kerusi tidak diketahui).
    const turnout = totalBerdaftar != null && totalBerdaftar > 0
        ? (totalKeluar / totalBerdaftar) * 100
        : null;

    return (
        <div className="space-y-6">
            {/* Hero band */}
            <div className="rounded-3xl bg-slate-900 text-white px-6 py-8 sm:py-10 text-center">
                {/* Baris besar ialah IDENTITI KERUSI daripada pelayan, bukan
                    teks yang ditaip. Sepanduk bebas (`title`) duduk di atasnya
                    dengan berat yang jauh lebih ringan, supaya papan tidak
                    boleh mengisytiharkan dirinya kerusi lain. */}
                <div className="flex flex-col items-center gap-3">
                    {data.logo_url && <img src={data.logo_url} alt="" className="h-14 sm:h-16 w-auto object-contain" />}
                    <p className="text-slate-400 text-xs sm:text-sm font-semibold uppercase tracking-[0.3em]">
                        {data.title || 'SCOREBOARD'}
                    </p>
                    <h1 className="text-2xl sm:text-4xl lg:text-5xl font-black uppercase tracking-[0.14em] leading-tight">
                        {data.identiti?.label || labelKerusi(data)}
                    </h1>
                    <p className="text-slate-300 text-xs sm:text-sm">
                        {konteksKerusi(data).map((teks) => <span key={teks}>{teks} · </span>)}
                        <span className="font-semibold text-white">{labelKerusi(data)}</span>
                        {data.penjuru_label ? ` · ${data.penjuru_label}` : ''}
                    </p>
                </div>
            </div>

            <LiputanBadge liputan={data.liputan} />

            {/* Live share bar */}
            <ShareBar rows={rows} totalKeluar={totalKeluar} />

            {/* Candidate cards */}
            <div className="flex flex-wrap justify-center gap-4">
                {rows.map((r) => {
                    const isLeader = r.slot === leaderSlot && totalKeluar > 0;
                    const color = partyColor(r.parti);
                    return (
                        <div
                            key={r.slot}
                            className={`relative w-full sm:w-[360px] rounded-2xl bg-white border shadow-sm overflow-hidden ${isLeader ? 'ring-2 ring-amber-400 border-amber-300' : 'border-slate-200'}`}
                        >
                            <div className="h-2" style={{ backgroundColor: color }} />
                            {isLeader && (
                                <div className="absolute top-3 right-3 inline-flex items-center gap-1 bg-amber-400 text-amber-950 text-xs font-bold px-2 py-0.5 rounded-full">
                                    <Crown className="h-3.5 w-3.5" /> MENDAHULUI
                                </div>
                            )}
                            <div className="p-5">
                                <div className="min-w-0 flex-1">
                                    <div className="text-xs font-bold uppercase tracking-wide" style={{ color }}>{r.parti}{r.is_kami ? ' · KAMI' : ''}</div>
                                    <div className="text-lg font-bold text-slate-900 truncate">{r.calon || '—'}</div>
                                    <div className="mt-1 text-3xl font-black text-slate-900 tabular-nums">{fmt(r.undi)}</div>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Footer stats */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {[
                    ['Jumlah Undi Keluar', fmt(totalKeluar)],
                    ['Pengundi Berdaftar', fmt(totalBerdaftar)],
                    ['% Keluar Mengundi', turnout == null ? '—' : `${turnout.toFixed(1)}%`],
                ].map(([label, value]) => (
                    <div key={label} className="rounded-2xl bg-white border border-slate-200 p-5 text-center">
                        <div className="text-xs uppercase tracking-wider text-slate-500">{label}</div>
                        <div className="text-2xl font-black text-slate-900 mt-1 tabular-nums">{value}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}

/* -------------------------------- page --------------------------------- */

export default function PublicScoreboard({ kod, board }) {
    const [data, setData] = useState(board);
    const [updatedAt, setUpdatedAt] = useState(board ? new Date() : null);

    const fetchData = useCallback(() => {
        // Cache-buster so no browser/CDN layer serves a stale poll response —
        // the board must reflect Borang 14 key-ins within one poll.
        axios.get(`/scoreboard/${kod}/data`, { params: { _t: Date.now() } })
            .then(({ data: d }) => { setData(d); setUpdatedAt(new Date()); })
            // Rangkaian tersekat sementara: biarkan paparan terakhir kekal,
            // jangan kosongkan atau papar sifar pada malam keputusan.
            .catch(() => {});
    }, [kod]);

    useEffect(() => {
        const id = setInterval(fetchData, POLL_MS);
        return () => clearInterval(id);
    }, [fetchData]);

    const ready = data?.ready;
    // Tajuk tab pelayar mengikut KERUSI juga — pautan yang dikongsi mesti
    // membawa nama kerusi yang betul walaupun sepanduk papan diubah.
    const pageTitle = ready ? `${data.identiti?.label || labelKerusi(data)} — Papan Markah` : 'Live Scoreboard';

    return (
        <>
            <Head title={pageTitle} />
            <div className="min-h-screen bg-[#f5f6f8]">
                {/* Top bar */}
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

                <main className="max-w-5xl mx-auto px-4 py-8 sm:py-12">
                    {ready ? (
                        <Board data={data} />
                    ) : (
                        <div className="max-w-md mx-auto rounded-2xl bg-white border border-slate-200 p-8 text-center text-slate-500">
                            Papan markah belum bersedia. Sila cuba sebentar lagi.
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
