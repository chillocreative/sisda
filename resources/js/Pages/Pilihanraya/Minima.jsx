import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ReferenceLine, ResponsiveContainer,
    Tooltip, XAxis, YAxis,
} from 'recharts';
import { RotateCcw, SlidersHorizontal, Target, TrendingDown, Users, Vote } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import KpiCard from './components/KpiCard';
import { KawasanSelect, FilterBarCard } from './analisa/FilterControls';
import DragScroll from './analisa/DragScroll';
import { PARTY, STATUS_STYLES, fmt, pct } from './analisa/shared';

/* --------------------------- Model (from Excel) --------------------------- */
const TURNOUTS_J1 = [0.5, 0.6, 0.7, 0.75, 0.8, 0.85, 0.9];
const SOKONGAN_J2 = [0.15, 0.2, 0.25, 0.27, 0.3, 0.35];
const SCENARIOS_J3 = [[0.9, 0, 0.1], [0.7, 0.1, 0.2], [0.6, 0.15, 0.25], [0.5, 0.2, 0.3], [0.4, 0.25, 0.35], [0.3, 0.3, 0.4]];

const statusSM = (s) => (s >= 0.275 ? 'SANGAT SUKAR' : s >= 0.18 ? 'SUKAR' : s >= 0.08 ? 'BOLEH DICAPAI' : 'MUDAH');
const statusTCI = (t) => (t > 0.85 ? 'TIDAK REALISTIK' : t > 0.78 ? 'SUKAR' : 'BOLEH DICAPAI');

function computeJadual1(a) {
    const keluarM = a.M * a.tM;
    return TURNOUTS_J1.map((tCI) => {
        const keluarC = a.C * tCI;
        const keluarI = a.I * tCI;
        const total = keluarM + keluarC + keluarI;
        const sM = keluarM > 0 ? (0.5 * total - keluarC * a.sC - keluarI * a.sI) / keluarM : 0;
        return { turnout_ci: tCI, sokongan_min: sM, anjakan: sM - 0.02, status: statusSM(sM) };
    });
}
function computeJadual2(a) {
    const denom = a.C * a.sC + a.I * a.sI - 0.5 * (a.C + a.I);
    const keluarM = a.M * a.tM;
    return SOKONGAN_J2.map((sM) => {
        const tCI = denom !== 0 ? (keluarM * (0.5 - sM)) / denom : 0;
        return { sokongan_melayu: sM, turnout_min: tCI, status: statusTCI(tCI) };
    });
}
function computeJadual3(a) {
    // Undi Melayu kekal pada paras sebenar 2022 (mPH2022/mBN2022, boleh diubah per kawasan).
    const basePH = a.mPH2022 + a.C * a.tTarget * a.sC + a.I * a.tTarget * a.sI;
    const baseBN = a.mBN2022 + a.C * a.tTarget * (1 - a.sC) + a.I * a.tTarget * (1 - a.sI);
    return SCENARIOS_J3.map(([bn, ph, tak]) => {
        const undiPH = basePH + a.undiPN * ph;
        const undiBN = baseBN + a.undiPN * bn;
        return { pn_bn: bn, pn_ph: ph, pn_tak_keluar: tak, undi_ph: undiPH, undi_bn: undiBN, keputusan: undiPH > undiBN ? 'PH MENANG' : 'BN MENANG' };
    });
}

/* ------------------------------ UI helpers ------------------------------- */
function StatusBadge({ status }) {
    const cls = STATUS_STYLES[status] || 'bg-slate-500/15 text-slate-500 border border-slate-500/40';
    return <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${cls}`}>{status}</span>;
}

function EditField({ label, value, onChange, suffix, step = 1, min = 0, max }) {
    const { t } = usePilihanrayaTheme();
    return (
        <div>
            <label className={`${t.subtext} text-xs block mb-1`}>{label}</label>
            <div className="relative">
                <input
                    type="number" value={value} step={step} min={min} max={max}
                    onChange={(e) => onChange(e.target.value)}
                    className={`${t.input} ${suffix ? 'pr-8' : ''}`}
                />
                {suffix && <span className={`${t.subtext} absolute right-3 top-1/2 -translate-y-1/2 text-sm`}>{suffix}</span>}
            </div>
        </div>
    );
}

/** Editable assumptions — the blue cells from the workbook. */
function AndaianEditor({ a, set, reset }) {
    const { t } = usePilihanrayaTheme();
    const setPct = (key) => (v) => set(key, Math.min(100, Math.max(0, parseFloat(v) || 0)) / 100);
    const setNum = (key) => (v) => set(key, Math.max(0, parseInt(v, 10) || 0));

    return (
        <div className={`${t.card} border-l-4`} style={{ borderLeftColor: PARTY.PN }}>
            <div className="flex items-center justify-between mb-1">
                <div className="flex items-center gap-2">
                    <SlidersHorizontal className="h-5 w-5" style={{ color: PARTY.PN }} />
                    <h3 className={t.cardTitle + ' mb-0'}>Pemboleh Ubah Andaian</h3>
                </div>
                <button type="button" onClick={reset} className={t.buttonSecondary}>
                    <RotateCcw className="h-4 w-4" /> Set Semula
                </button>
            </div>
            <p className={`${t.subtext} text-sm mb-4`}>
                Ubah nilai di bawah (sepadan dengan sel biru dalam fail Excel). Ketiga-tiga jadual dikira semula secara automatik.
            </p>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <EditField label="Pengundi Melayu" value={a.M} onChange={setNum('M')} step={1} />
                <EditField label="Pengundi Cina" value={a.C} onChange={setNum('C')} step={1} />
                <EditField label="Pengundi India" value={a.I} onChange={setNum('I')} step={1} />
                <EditField label="Turnout Melayu" value={Math.round(a.tM * 1000) / 10} onChange={setPct('tM')} suffix="%" step={0.5} max={100} />
                <EditField label="Sokongan PH — Cina" value={Math.round(a.sC * 1000) / 10} onChange={setPct('sC')} suffix="%" step={0.5} max={100} />
                <EditField label="Sokongan PH — India" value={Math.round(a.sI * 1000) / 10} onChange={setPct('sI')} suffix="%" step={0.5} max={100} />
                <EditField label="Turnout Sasaran C+I (Jadual 3)" value={Math.round(a.tTarget * 1000) / 10} onChange={setPct('tTarget')} suffix="%" step={0.5} max={100} />
            </div>
            <p className={`${t.subtext} text-xs mt-4 mb-2`}>
                Keputusan 2022 kawasan ini (untuk Jadual 3) — nilai lalai ialah tally sebenar Buloh Kasap; sila ubah untuk kawasan lain.
            </p>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                <EditField label="Undi PH Melayu 2022" value={a.mPH2022} onChange={setNum('mPH2022')} step={1} />
                <EditField label="Undi BN Melayu 2022" value={a.mBN2022} onChange={setNum('mBN2022')} step={1} />
                <EditField label="Jumlah Undi PN 2022" value={a.undiPN} onChange={setNum('undiPN')} step={1} />
            </div>
        </div>
    );
}

/* ------------------------------- Jadual 1 -------------------------------- */
function Jadual1({ data }) {
    const { t } = usePilihanrayaTheme();
    const chart = data.map((r) => ({ turnout: +(r.turnout_ci * 100).toFixed(0), min: +(r.sokongan_min * 100).toFixed(1) }));
    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Jadual 1 — Sokongan Melayu Minimum untuk PH Menang</h3>
            <p className={`${t.subtext} text-sm mb-4`}>Mengikut peningkatan turnout pengundi Cina + India.</p>
            <ResponsiveContainer width="100%" height={280}>
                <LineChart data={chart} margin={{ left: 0, right: 16, top: 8 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke={t.chartGrid} />
                    <XAxis dataKey="turnout" stroke={t.chartTick} style={{ fontSize: '11px' }} unit="%" />
                    <YAxis stroke={t.chartTick} style={{ fontSize: '11px' }} unit="%" />
                    <Tooltip contentStyle={t.tooltip} formatter={(v) => `${v}%`} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <ReferenceLine y={2} stroke={PARTY.ditolak} strokeDasharray="5 5" label={{ value: 'Sokongan 2022 (2%)', fill: t.chartTick, fontSize: 10, position: 'insideTopLeft' }} />
                    <Line type="monotone" dataKey="min" name="Sokongan Melayu MIN" stroke={PARTY.PH} strokeWidth={3} label={{ position: 'top', fill: t.chartTick, fontSize: 11, formatter: (v) => `${v}%` }} dot={{ r: 4, fill: '#fff', stroke: PARTY.PH, strokeWidth: 2 }} />
                </LineChart>
            </ResponsiveContainer>
            <DragScroll className="mt-4">
                <table className="min-w-full">
                    <thead>
                        <tr>
                            <th className={t.tableHead + ' text-right'}>Turnout Cina+India</th>
                            <th className={t.tableHead + ' text-right'}>Sokongan Melayu MIN</th>
                            <th className={t.tableHead + ' text-right'}>Anjakan drpd 2022</th>
                            <th className={t.tableHead}>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((r) => (
                            <tr key={r.turnout_ci} className={t.tableRow}>
                                <td className={`${t.tableCell} text-right tabular-nums`}>{pct(r.turnout_ci, 0)}</td>
                                <td className={`${t.tableCell} text-right tabular-nums font-semibold`} style={{ color: PARTY.PH }}>{pct(r.sokongan_min)}</td>
                                <td className={`${t.tableCell} text-right tabular-nums ${t.subtext}`}>{r.anjakan >= 0 ? '+' : ''}{pct(r.anjakan)}</td>
                                <td className={t.tableCell}><StatusBadge status={r.status} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

/* ------------------------------- Jadual 2 -------------------------------- */
function Jadual2({ data }) {
    const { t } = usePilihanrayaTheme();
    const chart = data.map((r) => ({ sokongan: +(r.sokongan_melayu * 100).toFixed(0), min: +(r.turnout_min * 100).toFixed(1) }));
    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Jadual 2 — Turnout Cina+India Minimum untuk PH Menang</h3>
            <p className={`${t.subtext} text-sm mb-4`}>Mengikut tahap sokongan pengundi Melayu kepada PH. Nilai &gt; 100% bermakna mustahil dicapai.</p>
            <ResponsiveContainer width="100%" height={280}>
                <LineChart data={chart} margin={{ left: 0, right: 16, top: 8 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke={t.chartGrid} />
                    <XAxis dataKey="sokongan" stroke={t.chartTick} style={{ fontSize: '11px' }} unit="%" />
                    <YAxis stroke={t.chartTick} style={{ fontSize: '11px' }} unit="%" />
                    <Tooltip contentStyle={t.tooltip} formatter={(v) => `${v}%`} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <ReferenceLine y={100} stroke={PARTY.ditolak} strokeDasharray="5 5" label={{ value: 'Had realistik 100%', fill: t.chartTick, fontSize: 10, position: 'insideTopRight' }} />
                    <Line type="monotone" dataKey="min" name="Turnout C+I MIN" stroke={PARTY.PN} strokeWidth={3} label={{ position: 'top', fill: t.chartTick, fontSize: 11, formatter: (v) => `${v}%` }} dot={{ r: 4, fill: '#fff', stroke: PARTY.PN, strokeWidth: 2 }} />
                </LineChart>
            </ResponsiveContainer>
            <DragScroll className="mt-4">
                <table className="min-w-full">
                    <thead>
                        <tr>
                            <th className={t.tableHead + ' text-right'}>% Sokongan Melayu kpd PH</th>
                            <th className={t.tableHead + ' text-right'}>Turnout C+I MINIMUM</th>
                            <th className={t.tableHead}>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((r) => (
                            <tr key={r.sokongan_melayu} className={t.tableRow}>
                                <td className={`${t.tableCell} text-right tabular-nums`}>{pct(r.sokongan_melayu, 0)}</td>
                                <td className={`${t.tableCell} text-right tabular-nums font-semibold`} style={{ color: PARTY.PN }}>{pct(r.turnout_min)}</td>
                                <td className={t.tableCell}><StatusBadge status={r.status} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

/* ------------------------------- Jadual 3 -------------------------------- */
function Jadual3({ data }) {
    const { t } = usePilihanrayaTheme();
    const chart = data.map((r) => ({ name: `PN→PH ${pct(r.pn_ph, 0)}`, PH: Math.round(r.undi_ph), BN: Math.round(r.undi_bn) }));
    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Jadual 3 — Kesan Peralihan Undi PN 2022 (±2,999 undi)</h3>
            <p className={`${t.subtext} text-sm mb-4`}>
                Asas: undi Melayu kekal pada paras 2022; turnout Cina/India dinaikkan ke sasaran di atas. Undi PN dibahagi
                ikut nisbah senario di bawah.
            </p>
            <ResponsiveContainer width="100%" height={320}>
                <BarChart data={chart} margin={{ left: 10, right: 16, top: 20 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={t.chartGrid} />
                    <XAxis dataKey="name" stroke={t.chartTick} style={{ fontSize: '10px' }} />
                    <YAxis stroke={t.chartTick} style={{ fontSize: '11px' }} />
                    <Tooltip contentStyle={t.tooltip} formatter={(v) => `${fmt(v)} undi`} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <Bar dataKey="PH" name="Undi PH" fill={PARTY.PH} radius={[3, 3, 0, 0]} label={{ position: 'top', fill: t.chartTick, fontSize: 10, formatter: (v) => fmt(v) }} />
                    <Bar dataKey="BN" name="Undi BN" fill={PARTY.BN} radius={[3, 3, 0, 0]} label={{ position: 'top', fill: t.chartTick, fontSize: 10, formatter: (v) => fmt(v) }} />
                </BarChart>
            </ResponsiveContainer>
            <DragScroll className="mt-4">
                <table className="min-w-full">
                    <thead>
                        <tr>
                            <th className={t.tableHead + ' text-right'}>% PN → BN</th>
                            <th className={t.tableHead + ' text-right'}>% PN → PH</th>
                            <th className={t.tableHead + ' text-right'}>% PN Tak Keluar</th>
                            <th className={t.tableHead + ' text-right'}>Undi PH</th>
                            <th className={t.tableHead + ' text-right'}>Undi BN</th>
                            <th className={t.tableHead}>Keputusan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((r, i) => (
                            <tr key={i} className={t.tableRow}>
                                <td className={`${t.tableCell} text-right tabular-nums ${t.subtext}`}>{pct(r.pn_bn, 0)}</td>
                                <td className={`${t.tableCell} text-right tabular-nums ${t.subtext}`}>{pct(r.pn_ph, 0)}</td>
                                <td className={`${t.tableCell} text-right tabular-nums ${t.subtext}`}>{pct(r.pn_tak_keluar, 0)}</td>
                                <td className={`${t.tableCell} text-right tabular-nums font-semibold`} style={{ color: PARTY.PH }}>{fmt(r.undi_ph)}</td>
                                <td className={`${t.tableCell} text-right tabular-nums font-semibold`} style={{ color: PARTY.BN }}>{fmt(r.undi_bn)}</td>
                                <td className={t.tableCell}><StatusBadge status={r.keputusan} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

export default function Minima({ context, minima }) {
    const src = minima.andaian;
    const initial = {
        M: src.pengundi_melayu, C: src.pengundi_cina, I: src.pengundi_india,
        tM: src.turnout_melayu, sC: src.sokongan_ph_cina, sI: src.sokongan_ph_india, tTarget: 0.75,
        mPH2022: src.melayu_ph_2022, mBN2022: src.melayu_bn_2022, undiPN: src.undi_pn_2022,
    };
    const [kawasan, setKawasan] = useState(context.selectedId ?? '');
    const [a, setA] = useState(initial);
    const set = (key, val) => setA((s) => ({ ...s, [key]: val }));
    const reset = () => setA(initial);

    const hasKawasan = (context.kawasanList?.length ?? 0) > 0;

    // Changing the DUN refetches its real voter counts from the server.
    const handleKawasan = (id) => {
        setKawasan(id);
        router.get(route('pilihanraya.minima'), { kawasan: id }, {
            only: ['context', 'minima'],
            preserveScroll: true,
        });
    };

    const j1 = useMemo(() => computeJadual1(a), [a]);
    const j2 = useMemo(() => computeJadual2(a), [a]);
    const j3 = useMemo(() => computeJadual3(a), [a]);

    if (!hasKawasan) {
        return (
            <AuthenticatedLayout>
                <Head title="Pilihanraya — Minima Untuk Menang" />
                <PilihanrayaShell title="Minima Untuk PH Menang" subtitle="pertandingan 1 lawan 1 (PH vs BN)">
                    <div className="rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-6 text-center">
                        Tiada kawasan (Parlimen/DUN) dalam sistem lagi. Muat naik dan aktifkan pangkalan data pengundi di Upload Database.
                    </div>
                </PilihanrayaShell>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout>
            <Head title="Pilihanraya — Minima Untuk Menang" />
            <PilihanrayaShell
                title="Minima Untuk PH Menang"
                subtitle={`${context.dun} · ${context.parlimen}, ${context.negeri} — pertandingan 1 lawan 1 (PH vs BN)`}
            >
                <FilterBarCard>
                    <KawasanSelect list={context.kawasanList} value={kawasan} onChange={handleKawasan} />
                    <div className="text-sm">
                        <span className="block text-xs opacity-60 mb-1">Model</span>
                        <span className="font-semibold">Pertandingan 1 lawan 1 (PH vs BN)</span>
                    </div>
                </FilterBarCard>

                <div className={`${STATUS_STYLES['SUKAR']} rounded-xl px-4 py-3 text-sm mb-6 flex items-start gap-2`}>
                    <Target className="h-5 w-5 shrink-0 mt-0.5" />
                    <span>
                        <strong>Syarat menang:</strong> PH mesti memperoleh &gt; 50% undi keluar. Ubah pemboleh ubah di bawah untuk
                        menguji gabungan minimum turnout dan sokongan yang diperlukan mengikut kaum.
                    </span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                    <KpiCard label="Jumlah Pengundi 2026" value={fmt(a.M + a.C + a.I)} icon={Users} sub="DPPR 2026" />
                    <KpiCard label="Pengundi Melayu" value={fmt(a.M)} icon={Users} sub={`Turnout andaian ${pct(a.tM, 0)}`} iconBg="bg-green-500/15" iconColor="text-green-500" />
                    <KpiCard label="Sokongan PH Cina" value={pct(a.sC, 0)} icon={Vote} sub="Andaian asas" iconBg="bg-rose-500/15" iconColor="text-rose-500" />
                    <KpiCard label="Sokongan PH India" value={pct(a.sI, 0)} icon={TrendingDown} sub="Andaian asas" iconBg="bg-amber-500/15" iconColor="text-amber-500" />
                </div>

                <div className="mb-6">
                    <AndaianEditor a={a} set={set} reset={reset} />
                </div>

                <div className="grid grid-cols-1 gap-6">
                    <Jadual1 data={j1} />
                    <Jadual2 data={j2} />
                    <Jadual3 data={j3} />
                </div>
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
