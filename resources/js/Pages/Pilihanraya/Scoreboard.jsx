import { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Trophy, Crown, Settings, X, Upload, Info, Loader2, Radio, Maximize2, Minimize2,
    AlertTriangle, CheckCircle2,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';

// Nilai null/NaN bermakna TIDAK DIKETAHUI, bukan sifar — papar "—" supaya
// papan markah tidak mendakwa "0" bagi angka yang sebenarnya belum ada.
const fmt = (n) => (n == null || Number.isNaN(n) ? '—' : Number(n).toLocaleString('en-MY'));
const POLL_MS = 4000;

const PARTY_COLOR = {
    KEADILAN: '#D71920', PKR: '#D71920', DAP: '#DE0000', AMANAH: '#F58220', MUDA: '#111827',
    UMNO: '#00529B', PPBM: '#4C1D95', BERSATU: '#4C1D95', PAS: '#0B6E4F', MCA: '#003C71',
    MIC: '#00529B', GERAKAN: '#E4002B', PBM: '#6B21A8', PUTRA: '#166534', PEJUANG: '#7C2D12',
};
// Match on the leading word so names carrying a coalition suffix (e.g.
// "UMNO (BN)") still resolve to the party colour.
const partyColor = (nama) => PARTY_COLOR[((nama || '').toUpperCase().match(/[A-Z]+/) || [''])[0]] || '#64748b';

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

/* ------------------------------- settings ------------------------------ */

function SettingsModal({ seat, board, onClose, onSaved }) {
    const { t } = usePilihanrayaTheme();
    const rows = board?.rows || [];
    const [title, setTitle] = useState(board?.title || 'SCOREBOARD');
    const [minima, setMinima] = useState(board?.minima ?? '');
    const [sumber, setSumber] = useState(board?.sumber?.id ?? '');
    const [kami, setKami] = useState(() => rows.filter((r) => r.is_kami).map((r) => r.slot));
    const [names, setNames] = useState(() => rows.map((r) => r.calon || ''));
    const [logoFile, setLogoFile] = useState(null);
    const [saving, setSaving] = useState(false);
    const [ralat, setRalat] = useState(null);

    const toggleKami = (slot) =>
        setKami((prev) => (prev.includes(slot) ? prev.filter((s) => s !== slot) : [...prev, slot]));

    const submit = () => {
        setSaving(true);
        setRalat(null);
        const fd = new FormData();
        fd.append('kawasan_type', seat.type);
        fd.append('kawasan_id', seat.id);
        fd.append('title', title || 'SCOREBOARD');
        // Minima kosong bermakna TIADA sasaran, bukan sifar.
        if (minima !== '') fd.append('minima', minima);
        if (sumber !== '') fd.append('borang14_form_id', sumber);
        kami.forEach((slot, i) => fd.append(`pihak_kami[${i}]`, slot));
        rows.forEach((r, i) => {
            fd.append(`candidates[${i}][slot]`, r.slot);
            fd.append(`candidates[${i}][nama]`, names[i] || '');
        });
        if (logoFile) fd.append('logo', logoFile);

        axios.post(route('pilihanraya.scoreboard.settings'), fd, { headers: { 'Content-Type': 'multipart/form-data' } })
            .then(() => { onSaved(); onClose(); })
            .catch((e) => { setRalat(e.response?.data?.message || 'Gagal menyimpan tetapan.'); setSaving(false); });
    };

    const field = 'w-full px-3 py-2 border border-slate-300 rounded-lg text-sm';

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-base font-semibold text-slate-900">Tetapan Scoreboard</h3>
                    <button onClick={onClose}><X className="h-5 w-5 text-slate-400" /></button>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Tajuk</label>
                        <input value={title} onChange={(e) => setTitle(e.target.value)} className={field} placeholder="SCOREBOARD" />
                    </div>
                    <div className="sm:col-span-2">
                        <label className="block text-sm font-medium text-slate-700 mb-1">Logo (pilihan)</label>
                        <div className="flex items-center gap-3">
                            <img src={logoFile ? URL.createObjectURL(logoFile) : board?.logo_url} alt="logo" className="h-12 w-12 object-contain rounded border border-slate-200 bg-white" />
                            <label className="inline-flex items-center gap-2 px-3 py-2 border border-slate-300 rounded-lg text-sm cursor-pointer hover:bg-slate-50">
                                <Upload className="h-4 w-4" /> Muat Naik Logo
                                <input type="file" accept="image/*" className="hidden" onChange={(e) => setLogoFile(e.target.files?.[0] || null)} />
                            </label>
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Sumber Undi</label>
                        <select value={sumber} onChange={(e) => setSumber(e.target.value)} className={field}>
                            <option value="">Belum pilih sumber</option>
                            {(board?.sumberList || []).map((s) => (
                                <option key={s.id} value={s.id}>{s.label}</option>
                            ))}
                        </select>
                        <p className="text-xs text-slate-500 mt-1">Papan membaca undi daripada Borang 14 yang dipilih di sini.</p>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Undi Minima (pilihan)</label>
                        <input type="number" min="0" value={minima} onChange={(e) => setMinima(e.target.value)} className={field} placeholder="Kosongkan jika tiada sasaran" />
                    </div>
                </div>

                <div className="border-t border-slate-200 pt-4">
                    <p className="text-sm font-semibold text-slate-800 mb-3">Calon</p>
                    <div className="space-y-3">
                        {rows.map((r, i) => (
                            <div key={r.slot} className="p-3 rounded-lg border border-slate-200">
                                <span className="text-xs font-semibold" style={{ color: partyColor(r.parti) }}>{r.parti}</span>
                                <input
                                    value={names[i] || ''}
                                    onChange={(e) => setNames((prev) => prev.map((v, idx) => (idx === i ? e.target.value : v)))}
                                    className={field}
                                    placeholder="Nama calon"
                                />
                                <label className="flex items-center gap-2 text-sm text-slate-700 mt-2">
                                    <input type="checkbox" checked={kami.includes(r.slot)} onChange={() => toggleKami(r.slot)} className="rounded border-slate-300" />
                                    Pihak kami
                                </label>
                            </div>
                        ))}
                    </div>
                </div>

                {board?.dikemaskini?.nama && (
                    <p className="text-xs text-slate-500 mt-3">
                        Dikemaskini oleh {board.dikemaskini.nama}
                        {board.dikemaskini.pada ? ` · ${new Date(board.dikemaskini.pada).toLocaleString('ms-MY')}` : ''}
                    </p>
                )}
                {ralat && <p className="text-sm text-red-600 mt-3">{ralat}</p>}

                <div className="flex justify-end gap-2 mt-5">
                    <button onClick={onClose} className={t.buttonSecondary}>Batal</button>
                    <button onClick={submit} disabled={saving} className={t.buttonPrimary}>
                        {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null} Simpan
                    </button>
                </div>
            </div>
        </div>
    );
}

/* ------------------------------ penyiaran ------------------------------- */

function PenyiaranCard({ seat, board, onChanged }) {
    const { t } = usePilihanrayaTheme();
    const [busy, setBusy] = useState(false);
    const [ralat, setRalat] = useState(null);
    const tersiar = board?.status === 'tersiar';
    const url = board?.kod ? `${window.location.origin}/scoreboard/${board.kod.toLowerCase()}` : null;

    const togol = () => {
        setBusy(true);
        setRalat(null);
        axios.post(route('pilihanraya.scoreboard.publish'), {
            kawasan_type: seat.type,
            kawasan_id: seat.id,
            status: tersiar ? 'draf' : 'tersiar',
        })
            .then(() => onChanged())
            .catch((e) => setRalat(e.response?.data?.message || 'Gagal menukar status penyiaran.'))
            .finally(() => setBusy(false));
    };

    return (
        <div className={`${t.card} mb-5`}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-slate-800">
                        Status: {tersiar ? 'Tersiar' : 'Draf'}
                    </p>
                    <p className="text-xs text-slate-500">
                        {tersiar ? 'Sesiapa yang ada pautan boleh melihat papan ini.' : 'Hanya anda nampak papan ini.'}
                    </p>
                </div>
                <button onClick={togol} disabled={busy} className={tersiar ? t.buttonSecondary : t.buttonPrimary}>
                    {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                    {tersiar ? 'Tarik Balik Siaran' : 'Siarkan'}
                </button>
            </div>
            {tersiar && url && (
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <code className="text-xs bg-slate-100 px-2 py-1 rounded">{url}</code>
                    <button onClick={() => navigator.clipboard.writeText(url)} className="text-xs text-blue-600 hover:underline">
                        Salin Pautan
                    </button>
                </div>
            )}
            {ralat && <p className="text-sm text-red-600 mt-3">{ralat}</p>}
        </div>
    );
}

/* ------------------------------- liputan -------------------------------- */

// `liputan` is null for DUN boards and standalone-PRU Parlimen boards — those
// read votes straight off their own form and have no partial-coverage concept,
// so this renders nothing for them (unchanged from today). It is only present
// on a Parlimen roll-up: the totals below are SUMMED across linked DUN forms,
// and this is the only line telling the operator whether that sum is complete.
// `jumlah === 0` (no DUN form linked yet) is a real state too, but "0 daripada
// 0 DUN melapor" reads as a typo — worded separately instead.
//
// Sized to compete with the numbers below it, not caption them. This is the
// OWNER board — an operator already knows the count is in progress, so this
// copy is weighted a step lighter than the public board's (text-lg vs
// text-xl+, font-extrabold vs font-black) while still standing well clear of
// a caption: registerable even by someone who can't read the words.
function LiputanBadge({ liputan }) {
    if (liputan == null) return null;
    const { melapor, jumlah } = liputan;

    if (jumlah === 0) {
        return (
            <div className="rounded-2xl bg-amber-100 border-2 border-amber-400 text-amber-900 px-5 py-3.5 sm:py-4 text-lg sm:text-xl font-extrabold uppercase tracking-wide flex items-center justify-center gap-3 mb-5 text-center">
                <AlertTriangle className="h-5 w-5 sm:h-6 sm:w-6 shrink-0" />
                <span>SEMENTARA · Belum ada borang DUN dipautkan lagi</span>
            </div>
        );
    }

    if (melapor < jumlah) {
        return (
            <div className="rounded-2xl bg-amber-100 border-2 border-amber-400 text-amber-900 px-5 py-3.5 sm:py-4 text-lg sm:text-xl font-extrabold uppercase tracking-wide flex items-center justify-center gap-3 mb-5 text-center">
                <AlertTriangle className="h-5 w-5 sm:h-6 sm:w-6 shrink-0" />
                <span>SEMENTARA · {melapor} daripada {jumlah} DUN melapor</span>
            </div>
        );
    }

    return (
        <div className="rounded-2xl bg-emerald-100 border-2 border-emerald-400 text-emerald-900 px-5 py-3.5 sm:py-4 text-lg sm:text-xl font-extrabold uppercase tracking-wide flex items-center justify-center gap-3 mb-5 text-center">
            <CheckCircle2 className="h-5 w-5 sm:h-6 sm:w-6 shrink-0" />
            <span>LENGKAP · Semua {jumlah} DUN telah melapor</span>
        </div>
    );
}

/* -------------------------------- board -------------------------------- */

function Board({ data }) {
    const { t } = usePilihanrayaTheme();
    const { rows, total_keluar: totalKeluar, total_berdaftar: totalBerdaftar, leader_slot: leaderSlot } = data;

    // total_berdaftar boleh jadi null secara SAH (roll kerusi tidak diketahui).
    // Jangan anggap itu sifar — paparkan "—" dan bukan "0.0%" reka-reka.
    const turnout = totalBerdaftar != null && totalBerdaftar > 0
        ? (totalKeluar / totalBerdaftar) * 100
        : null;

    return (
        <>
            {/* Header band */}
            <div className="rounded-2xl bg-slate-900 text-white px-6 py-6 mb-5 text-center relative overflow-hidden">
                <div className="flex flex-col items-center gap-2">
                    {data.logo_url && <img src={data.logo_url} alt="logo" className="h-16 w-auto object-contain" />}
                    <h2 className="text-3xl sm:text-4xl font-black tracking-[0.2em]">{data.title || 'SCOREBOARD'}</h2>
                    <p className="text-slate-300 text-sm">
                        {konteksKerusi(data).map((teks) => <span key={teks}>{teks} · </span>)}
                        <span className="font-semibold text-white">{labelKerusi(data)}</span>
                        {data.penjuru_label ? ` · ${data.penjuru_label}` : ''}
                    </p>
                </div>
            </div>

            <LiputanBadge liputan={data.liputan} />

            {/* Candidate cards */}
            <div className="flex flex-wrap justify-center gap-4">
                {rows.map((r) => {
                    const isLeader = r.slot === leaderSlot && totalKeluar > 0;
                    const color = partyColor(r.parti);
                    return (
                        <div
                            key={r.slot}
                            className={`relative w-full sm:w-[380px] rounded-2xl bg-white border shadow-sm overflow-hidden ${isLeader ? 'ring-2 ring-amber-400 border-amber-300' : 'border-slate-200'}`}
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
                                    <div className="mt-1 text-3xl font-black text-slate-900">{fmt(r.undi)}</div>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Footer stats */}
            <div className="grid grid-cols-3 gap-4 mt-5">
                {[
                    ['Jumlah Undi Keluar', fmt(totalKeluar)],
                    ['Pengundi Berdaftar', fmt(totalBerdaftar)],
                    ['% Keluar Mengundi', turnout == null ? '—' : `${turnout.toFixed(1)}%`],
                ].map(([label, value]) => (
                    <div key={label} className={`${t.card} text-center`}>
                        <div className={`text-xs uppercase tracking-wider ${t.subtext}`}>{label}</div>
                        <div className="text-2xl font-black text-slate-900 mt-1">{value}</div>
                    </div>
                ))}
            </div>
        </>
    );
}

/* ----------------------------- seat picker ----------------------------- */

/**
 * Pemilih kerusi melata: Negeri > Parlimen > DUN.
 *
 * Dua cara memilih, sengaja:
 *  - Berhenti pada Parlimen  -> papan markah PARLIMEN kerusi itu.
 *  - Teruskan pilih DUN      -> papan markah DUN tersebut.
 * Dropdown DUN membawa pilihan "(Papan Parlimen)" supaya beralih antara
 * kedua-duanya tidak memerlukan pengguna mengosongkan pilihan.
 *
 * Senarai dibina SEMATA-MATA daripada `seats` yang dihantar pelayan, iaitu
 * kerusi yang SeatScope benarkan. Jangan sekali-kali bina daripada data induk
 * penuh — itu akan menyenaraikan kerusi yang pengguna tidak berhak sentuh.
 */
function PemilihKerusi({ seats, seat, onPilih, updatedAt }) {
    const kerusiParlimen = seats.filter((s) => s.type === 'parlimen');
    const kerusiDun = seats.filter((s) => s.type === 'dun');

    const [negeriId, setNegeriId] = useState(seat?.negeri_id ?? '');
    const [bandarId, setBandarId] = useState(seat?.bandar_id ?? '');

    const sama = (a, b) => String(a ?? '') === String(b ?? '');

    // Negeri yang benar-benar mempunyai kerusi milik pengguna ini.
    const senaraiNegeri = [];
    seats.forEach((s) => {
        if (s.negeri_id != null && !senaraiNegeri.some((n) => sama(n.id, s.negeri_id))) {
            senaraiNegeri.push({ id: s.negeri_id, nama: s.negeri });
        }
    });
    senaraiNegeri.sort((a, b) => (a.nama || '').localeCompare(b.nama || ''));

    const senaraiParlimen = [];
    seats.forEach((s) => {
        if (!sama(s.negeri_id, negeriId) || s.bandar_id == null) return;
        if (!senaraiParlimen.some((p) => sama(p.id, s.bandar_id))) {
            senaraiParlimen.push({ id: s.bandar_id, nama: s.parlimen });
        }
    });
    senaraiParlimen.sort((a, b) => (a.nama || '').localeCompare(b.nama || ''));

    const senaraiDun = kerusiDun.filter((s) => sama(s.bandar_id, bandarId));
    // Papan Parlimen hanya ditawarkan jika pengguna benar-benar memiliki kerusi itu.
    const parlimenDipilih = kerusiParlimen.find((s) => sama(s.id, bandarId)) || null;

    const pilihNegeri = (v) => { setNegeriId(v); setBandarId(''); onPilih(null); };
    const pilihParlimen = (v) => {
        setBandarId(v);
        // Berhenti di sini = papan Parlimen, jika pengguna memilikinya.
        onPilih(kerusiParlimen.find((s) => sama(s.id, v)) || null);
    };
    const pilihDun = (v) => {
        if (v === '') { onPilih(parlimenDipilih); return; }
        onPilih(kerusiDun.find((s) => sama(s.id, v)) || null);
    };

    const medan = 'w-full px-3 py-2 border border-slate-300 rounded-lg text-sm';

    return (
        <div className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm mb-5">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Negeri</label>
                    <select value={negeriId} onChange={(e) => pilihNegeri(e.target.value)} className={medan}>
                        <option value="">Pilih Negeri</option>
                        {senaraiNegeri.map((n) => <option key={n.id} value={n.id}>{n.nama}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Parlimen</label>
                    <select value={bandarId} onChange={(e) => pilihParlimen(e.target.value)} className={medan} disabled={!negeriId}>
                        <option value="">{negeriId ? 'Pilih Parlimen' : 'Pilih Negeri dahulu'}</option>
                        {senaraiParlimen.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">DUN</label>
                    <select
                        value={seat?.type === 'dun' ? seat.id : ''}
                        onChange={(e) => pilihDun(e.target.value)}
                        className={medan}
                        disabled={!bandarId || senaraiDun.length === 0}
                    >
                        <option value="">
                            {!bandarId
                                ? 'Pilih Parlimen dahulu'
                                : (parlimenDipilih ? '(Papan Parlimen)' : 'Pilih DUN')}
                        </option>
                        {senaraiDun.map((d) => (
                            <option key={d.id} value={d.id}>{d.nama}{d.kod ? ` (${d.kod})` : ''}</option>
                        ))}
                    </select>
                </div>
            </div>

            {seat && (
                <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span className="inline-flex items-center gap-1 text-emerald-600 font-semibold">
                        <Radio className="h-3.5 w-3.5 animate-pulse" /> LANGSUNG
                    </span>
                    <span className="font-semibold text-slate-700">
                        · {seat.type === 'parlimen' ? 'Parlimen' : 'DUN'} {seat.nama}{seat.kod ? ` (${seat.kod})` : ''}
                    </span>
                    {updatedAt && <span>· Dikemaskini {updatedAt.toLocaleTimeString('ms-MY')}</span>}
                </div>
            )}
        </div>
    );
}

/* --------------------------------- page -------------------------------- */

export default function Scoreboard(props) {
    return (
        <AuthenticatedLayout>
            <Head title="Scoreboard" />
            <ScoreboardBody {...props} />
        </AuthenticatedLayout>
    );
}

function ScoreboardBody({ seats }) {
    const [settingsOpen, setSettingsOpen] = useState(false);
    // Satu kerusi → terus dimuatkan, tiada pemilih. Inilah pembetulan skrin
    // tiga dropdown kosong bagi pengguna yang memiliki satu kerusi sahaja.
    const [seat, setSeat] = useState(seats.length === 1 ? seats[0] : null);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [updatedAt, setUpdatedAt] = useState(null);
    const [fullscreen, setFullscreen] = useState(false);

    const ready = data?.ready;
    // Tetapan mesti boleh dibuka SEBELUM papan "ready" — memilih Sumber Undi
    // di situ ialah satu-satunya cara papan menjadi ready pada mulanya.
    const bolehTetapan = !!(data && data.hasData);

    const fetchData = useCallback((showSpinner = false) => {
        if (!seat) { setData(null); return; }
        if (showSpinner) setLoading(true);
        // `_t` cache-buster keeps every poll fresh (no stale browser/CDN cache).
        axios.get(route('pilihanraya.scoreboard.data'), {
            params: { kawasan_type: seat.type, kawasan_id: seat.id, _t: Date.now() },
        })
            .then(({ data: d }) => { setData(d); setUpdatedAt(new Date()); })
            .finally(() => setLoading(false));
    }, [seat]);

    // Initial fetch + live polling.
    useEffect(() => {
        fetchData(true);
        if (!seat) return undefined;
        const id = setInterval(() => fetchData(false), POLL_MS);
        return () => clearInterval(id);
    }, [fetchData, seat]);

    // Enter/exit skrin penuh via a full-viewport CSS overlay (portalled to
    // <body>). We deliberately avoid the native Fullscreen API — promoting a
    // fixed element to the browser top layer rendered blank on this app — so
    // the overlay alone hides the sidebar and enlarges the board reliably.
    const enterFullscreen = () => setFullscreen(true);
    const exitFullscreen = () => setFullscreen(false);

    // Lock body scroll and allow Esc to close while the overlay is open.
    useEffect(() => {
        if (!fullscreen) return undefined;
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const onKey = (e) => { if (e.key === 'Escape') setFullscreen(false); };
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = prevOverflow;
            window.removeEventListener('keydown', onKey);
        };
    }, [fullscreen]);

    return (
        <PilihanrayaShell
            title="Scoreboard"
            subtitle="Papan markah pilihanraya secara langsung dari Borang 14"
            actions={bolehTetapan ? (
                <button type="button" onClick={() => setSettingsOpen(true)} className="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg text-sm font-medium">
                    <Settings className="h-4 w-4" /> Tetapan
                </button>
            ) : null}
        >
            {/* Seat picker — only rendered when the user holds more than one seat. */}
            {seats.length > 1 && (
                <PemilihKerusi seats={seats} seat={seat} onPilih={setSeat} updatedAt={updatedAt} />
            )}

            {/* States */}
            {seats.length === 0 ? (
                <div className="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Anda tiada kerusi ditugaskan untuk diuruskan.</span>
                </div>
            ) : !seat ? (
                <div className="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Pilih kerusi untuk memaparkan scoreboard.</span>
                </div>
            ) : loading && !data ? (
                <div className="flex items-center gap-2 text-slate-500 py-10 justify-center">
                    <Loader2 className="h-5 w-5 animate-spin" /> Memuatkan…
                </div>
            ) : data && !data.hasData ? (
                <div className="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Data Borang 14 belum tersedia untuk kerusi ini.</span>
                </div>
            ) : data && data.needsBorang14 ? (
                <>
                    <PenyiaranCard seat={seat} board={data} onChanged={() => fetchData(true)} />
                    <div className="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                        <Info className="h-4 w-4 shrink-0" />
                        <span>Sila pilih Sumber Undi dalam Tetapan untuk kerusi ini — penjuru & parti diambil dari situ.</span>
                    </div>
                </>
            ) : ready ? (
                <>
                    <PenyiaranCard seat={seat} board={data} onChanged={() => fetchData(true)} />
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="flex items-center justify-between px-4 py-2.5 border-b border-slate-200">
                            <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                                <Trophy className="h-4 w-4 text-slate-500" /> Papan Markah Langsung
                            </span>
                            <button
                                type="button"
                                onClick={enterFullscreen}
                                title="Besarkan ke skrin penuh"
                                className="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg text-sm font-medium"
                            >
                                <Maximize2 className="h-4 w-4" /> Besarkan
                            </button>
                        </div>
                        <div className="p-4 sm:p-5">
                            <Board data={data} />
                        </div>
                    </div>
                </>
            ) : null}

            {settingsOpen && seat && data && data.hasData && (
                <SettingsModal
                    seat={seat}
                    board={data}
                    onClose={() => setSettingsOpen(false)}
                    onSaved={() => fetchData(true)}
                />
            )}

            {/* Skrin penuh — portalled to <body> so no ancestor CSS can corrupt
                the fixed overlay, yet kept INSIDE PilihanrayaShell so the theme
                context still reaches <Board> (context flows by React tree, not
                DOM position). Covers the sidebar and the whole viewport. */}
            {fullscreen && ready && createPortal(
                <div className="fixed inset-0 z-[9999] bg-slate-50 overflow-y-auto">
                <div className="sticky top-0 z-10 flex items-center justify-between px-4 py-3 bg-slate-50/90 backdrop-blur border-b border-slate-200">
                    <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                        <Radio className="h-3.5 w-3.5 animate-pulse" /> LANGSUNG
                        {updatedAt && <span className="text-slate-400 font-normal">· Dikemaskini {updatedAt.toLocaleTimeString('ms-MY')}</span>}
                    </span>
                    <button type="button" onClick={exitFullscreen} className="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg text-sm font-medium bg-white">
                        <Minimize2 className="h-4 w-4" /> Keluar Skrin Penuh
                    </button>
                </div>
                <div className="p-4 sm:p-6 max-w-6xl mx-auto">
                    <Board data={data} />
                </div>
            </div>,
                document.body,
            )}
        </PilihanrayaShell>
    );
}
