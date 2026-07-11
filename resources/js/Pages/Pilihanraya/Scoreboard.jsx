import { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Trophy, Crown, Settings, X, Upload, Info, MapPin, Landmark, Vote, Loader2, Radio, Maximize2, Minimize2,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';

const fmt = (n) => (n == null || Number.isNaN(n) ? '0' : Number(n).toLocaleString('en-MY'));
const POLL_MS = 4000;

const PARTY_COLOR = {
    KEADILAN: '#D71920', PKR: '#D71920', DAP: '#DE0000', AMANAH: '#F58220', MUDA: '#111827',
    UMNO: '#00529B', PPBM: '#4C1D95', BERSATU: '#4C1D95', PAS: '#0B6E4F', MCA: '#003C71',
    MIC: '#00529B', GERAKAN: '#E4002B', PBM: '#6B21A8', PUTRA: '#166534', PEJUANG: '#7C2D12',
};
// Match on the leading word so names carrying a coalition suffix (e.g.
// "UMNO (BN)") still resolve to the party colour.
const partyColor = (nama) => PARTY_COLOR[((nama || '').toUpperCase().match(/[A-Z]+/) || [''])[0]] || '#64748b';

/* ------------------------------- settings ------------------------------ */

function SettingsModal({ kadunId, penjuru, board, onClose, onSaved }) {
    const { t } = usePilihanrayaTheme();
    const rows = board?.rows || [];
    const [title, setTitle] = useState(board?.title || 'SCOREBOARD');
    const [names, setNames] = useState(() => rows.map((r) => r.calon || ''));
    const [logoFile, setLogoFile] = useState(null);
    const [photoFiles, setPhotoFiles] = useState({}); // slot -> File
    const [saving, setSaving] = useState(false);

    const submit = () => {
        setSaving(true);
        const fd = new FormData();
        fd.append('kadun_id', kadunId);
        fd.append('penjuru', penjuru);
        fd.append('title', title || 'SCOREBOARD');
        rows.forEach((r, i) => {
            fd.append(`candidates[${i}][slot]`, r.slot);
            fd.append(`candidates[${i}][nama]`, names[i] || '');
            if (photoFiles[r.slot]) fd.append(`photos[${r.slot}]`, photoFiles[r.slot]);
        });
        if (logoFile) fd.append('logo', logoFile);

        axios.post(route('pilihanraya.scoreboard.settings'), fd, { headers: { 'Content-Type': 'multipart/form-data' } })
            .then(() => { onSaved(); onClose(); })
            .catch(() => setSaving(false));
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
                </div>

                <div className="border-t border-slate-200 pt-4">
                    <p className="text-sm font-semibold text-slate-800 mb-3">Calon</p>
                    <div className="space-y-3">
                        {rows.map((r, i) => (
                            <div key={r.slot} className="flex items-center gap-3 p-3 rounded-lg border border-slate-200">
                                <img
                                    src={photoFiles[r.slot] ? URL.createObjectURL(photoFiles[r.slot]) : (r.gambar || '')}
                                    alt=""
                                    className="h-14 w-14 object-cover rounded-full border-2 bg-slate-100"
                                    style={{ borderColor: partyColor(r.parti) }}
                                    onError={(e) => { e.currentTarget.style.visibility = 'hidden'; }}
                                />
                                <div className="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div>
                                        <span className="text-xs font-semibold" style={{ color: partyColor(r.parti) }}>{r.parti}</span>
                                        <input
                                            value={names[i] || ''}
                                            onChange={(e) => setNames((prev) => prev.map((v, idx) => (idx === i ? e.target.value : v)))}
                                            className={field}
                                            placeholder="Nama calon"
                                        />
                                    </div>
                                    <label className="flex items-end">
                                        <span className="inline-flex items-center gap-2 px-3 py-2 border border-slate-300 rounded-lg text-sm cursor-pointer hover:bg-slate-50 w-full justify-center">
                                            <Upload className="h-4 w-4" /> {photoFiles[r.slot] ? 'Tukar Gambar' : 'Gambar Calon'}
                                            <input type="file" accept="image/*" className="hidden"
                                                onChange={(e) => setPhotoFiles((prev) => ({ ...prev, [r.slot]: e.target.files?.[0] || null }))} />
                                        </span>
                                    </label>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

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

/* -------------------------------- board -------------------------------- */

function Board({ data }) {
    const { t } = usePilihanrayaTheme();
    const { rows, total_keluar: totalKeluar, total_berdaftar: totalBerdaftar, leader_slot: leaderSlot } = data;

    const turnout = totalBerdaftar > 0 ? (totalKeluar / totalBerdaftar) * 100 : 0;

    return (
        <>
            {/* Header band */}
            <div className="rounded-2xl bg-slate-900 text-white px-6 py-6 mb-5 text-center relative overflow-hidden">
                <div className="flex flex-col items-center gap-2">
                    {data.logo_url && <img src={data.logo_url} alt="logo" className="h-16 w-auto object-contain" />}
                    <h2 className="text-3xl sm:text-4xl font-black tracking-[0.2em]">{data.title || 'SCOREBOARD'}</h2>
                    <p className="text-slate-300 text-sm">
                        {data.negeri} · {data.parlimen} · <span className="font-semibold text-white">DUN {data.dun}</span> · {data.penjuru_label}
                    </p>
                </div>
            </div>

            {/* Candidate cards */}
            <div className="flex flex-wrap justify-center gap-4">
                {rows.map((r) => {
                    const isLeader = r.slot === leaderSlot && totalKeluar > 0;
                    const share = totalKeluar > 0 ? (r.undi / totalKeluar) * 100 : 0;
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
                            <div className="p-5 flex items-center gap-4">
                                <div className="h-20 w-20 shrink-0 rounded-full bg-slate-100 border-2 flex items-center justify-center overflow-hidden" style={{ borderColor: color }}>
                                    {r.gambar
                                        ? <img src={r.gambar} alt={r.calon || r.parti} className="h-full w-full object-cover" />
                                        : <Vote className="h-8 w-8 text-slate-300" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="text-xs font-bold uppercase tracking-wide" style={{ color }}>{r.parti}{r.is_ph ? ' · PH' : ''}</div>
                                    <div className="text-lg font-bold text-slate-900 truncate">{r.calon || '—'}</div>
                                    <div className="mt-1 text-3xl font-black text-slate-900">{fmt(r.undi)}</div>
                                    <div className="text-xs text-slate-500">{share.toFixed(1)}% undi keluar</div>
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
                    ['% Keluar Mengundi', `${turnout.toFixed(1)}%`],
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

/* --------------------------------- page -------------------------------- */

export default function Scoreboard(props) {
    return (
        <AuthenticatedLayout>
            <Head title="Scoreboard" />
            <ScoreboardBody {...props} />
        </AuthenticatedLayout>
    );
}

function ScoreboardBody({ negeriList, parlimenList, kadunList }) {
    const [settingsOpen, setSettingsOpen] = useState(false);
    const [negeriId, setNegeriId] = useState('');
    const [parlimenId, setParlimenId] = useState('');
    const [kadunId, setKadunId] = useState('');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [updatedAt, setUpdatedAt] = useState(null);
    const [fullscreen, setFullscreen] = useState(false);

    const parlimenOptions = negeriId ? parlimenList.filter((p) => String(p.negeri_id) === String(negeriId)) : [];
    const kadunOptions = parlimenId ? kadunList.filter((k) => String(k.bandar_id) === String(parlimenId)) : [];
    const ready = data?.ready;

    // The penjuru is taken automatically from the Borang 14 data for the DUN.
    const fetchData = useCallback((showSpinner = false) => {
        if (!kadunId) { setData(null); return; }
        if (showSpinner) setLoading(true);
        axios.get(route('pilihanraya.scoreboard.data'), { params: { kadun_id: kadunId } })
            .then(({ data: d }) => { setData(d); setUpdatedAt(new Date()); })
            .finally(() => setLoading(false));
    }, [kadunId]);

    // Initial fetch + live polling.
    useEffect(() => {
        fetchData(true);
        if (!kadunId) return undefined;
        const id = setInterval(() => fetchData(false), POLL_MS);
        return () => clearInterval(id);
    }, [fetchData, kadunId]);

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
        <>
        <PilihanrayaShell
            title="Scoreboard"
            subtitle="Papan markah pilihanraya secara langsung dari Borang 14"
            actions={ready ? (
                <button type="button" onClick={() => setSettingsOpen(true)} className="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg text-sm font-medium">
                    <Settings className="h-4 w-4" /> Tetapan
                </button>
            ) : null}
        >
            {/* Filters */}
            <div className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm mb-5">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1"><span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" /> Negeri</span></label>
                        <select value={negeriId} onChange={(e) => { setNegeriId(e.target.value); setParlimenId(''); setKadunId(''); }} className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm">
                            <option value="">Pilih Negeri</option>
                            {negeriList.map((n) => <option key={n.id} value={n.id}>{n.nama}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1"><span className="inline-flex items-center gap-1"><Landmark className="h-3.5 w-3.5" /> Parlimen</span></label>
                        <select value={parlimenId} onChange={(e) => { setParlimenId(e.target.value); setKadunId(''); }} className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm" disabled={!negeriId}>
                            <option value="">Pilih Parlimen</option>
                            {parlimenOptions.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1"><span className="inline-flex items-center gap-1"><Vote className="h-3.5 w-3.5" /> DUN</span></label>
                        <select value={kadunId} onChange={(e) => setKadunId(e.target.value)} className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm" disabled={!parlimenId}>
                            <option value="">Pilih DUN</option>
                            {kadunOptions.map((k) => <option key={k.id} value={k.id}>{k.nama}</option>)}
                        </select>
                    </div>
                </div>
                {ready && (
                    <div className="mt-3 flex items-center gap-2 text-xs text-slate-500">
                        <span className="inline-flex items-center gap-1 text-emerald-600 font-semibold">
                            <Radio className="h-3.5 w-3.5 animate-pulse" /> LANGSUNG
                        </span>
                        {updatedAt && <span>· Dikemaskini {updatedAt.toLocaleTimeString('ms-MY')}</span>}
                    </div>
                )}
            </div>

            {/* States */}
            {!kadunId ? (
                <div className="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Pilih Negeri &gt; Parlimen &gt; DUN untuk memaparkan scoreboard.</span>
                </div>
            ) : loading && !data ? (
                <div className="flex items-center gap-2 text-slate-500 py-10 justify-center">
                    <Loader2 className="h-5 w-5 animate-spin" /> Memuatkan…
                </div>
            ) : data && !data.hasData ? (
                <div className="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Data Borang 14 belum tersedia untuk DUN ini.</span>
                </div>
            ) : data && data.needsBorang14 ? (
                <div className="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Sila isi Borang 14 dahulu untuk DUN ini — penjuru & parti diambil dari situ.</span>
                </div>
            ) : ready ? (
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
            ) : null}

            {settingsOpen && ready && (
                <SettingsModal
                    kadunId={kadunId}
                    penjuru={data.penjuru}
                    board={data}
                    onClose={() => setSettingsOpen(false)}
                    onSaved={() => fetchData(false)}
                />
            )}
        </PilihanrayaShell>

        {/* Skrin penuh — portalled to <body> so no ancestor CSS (transform,
            overflow, stacking) can corrupt the fixed overlay or the fullscreen
            top-layer render. Covers the sidebar and the whole viewport. */}
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
        </>
    );
}
