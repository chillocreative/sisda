import { Head } from '@inertiajs/react';
import {
    Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ReferenceLine, ResponsiveContainer,
    Tooltip, XAxis, YAxis,
} from 'recharts';
import { Target, TrendingDown, Users, Vote } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import KpiCard from './components/KpiCard';
import { PARTY, STATUS_STYLES, fmt, pct } from './analisa/shared';

function StatusBadge({ status }) {
    const cls = STATUS_STYLES[status] || 'bg-slate-500/15 text-slate-500 border border-slate-500/40';
    return <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${cls}`}>{status}</span>;
}

function AndaianCard({ a }) {
    const { t } = usePilihanrayaTheme();
    const items = [
        { label: 'Pengundi Melayu', value: fmt(a.pengundi_melayu) },
        { label: 'Pengundi Cina', value: fmt(a.pengundi_cina) },
        { label: 'Pengundi India', value: fmt(a.pengundi_india) },
        { label: 'Turnout Melayu', value: pct(a.turnout_melayu, 0) },
        { label: 'Sokongan PH — Cina', value: pct(a.sokongan_ph_cina, 0) },
        { label: 'Sokongan PH — India', value: pct(a.sokongan_ph_india, 0) },
    ];
    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Andaian Asas Model</h3>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                {items.map((it) => (
                    <div key={it.label} className={`rounded-lg border ${t.border} p-3`}>
                        <p className={`${t.subtext} text-xs`}>{it.label}</p>
                        <p className={`${t.text} text-lg font-bold mt-0.5`}>{it.value}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

/* --- Jadual 1: sokongan Melayu minimum ikut turnout Cina+India --- */
function Jadual1({ data }) {
    const { t } = usePilihanrayaTheme();
    const chart = data.map((r) => ({
        turnout: +(r.turnout_ci * 100).toFixed(0),
        min: +(r.sokongan_min * 100).toFixed(1),
        anjakan: +(r.anjakan * 100).toFixed(1),
    }));
    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Jadual 1 — Sokongan Melayu Minimum untuk PH Menang</h3>
            <p className={`${t.subtext} text-sm mb-4`}>Mengikut peningkatan turnout pengundi Cina + India.</p>
            <ResponsiveContainer width="100%" height={280}>
                <LineChart data={chart} margin={{ left: 0, right: 16, top: 8 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke={t.chartGrid} />
                    <XAxis dataKey="turnout" stroke={t.chartTick} style={{ fontSize: '11px' }} unit="%" label={{ value: 'Turnout Cina+India', position: 'insideBottom', offset: -4, fill: t.chartTick, fontSize: 11 }} />
                    <YAxis stroke={t.chartTick} style={{ fontSize: '11px' }} unit="%" />
                    <Tooltip contentStyle={t.tooltip} formatter={(v) => `${v}%`} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <ReferenceLine y={2} stroke={PARTY.ditolak} strokeDasharray="5 5" label={{ value: 'Sokongan 2022 (2%)', fill: t.chartTick, fontSize: 10, position: 'insideTopLeft' }} />
                    <Line type="monotone" dataKey="min" name="Sokongan Melayu MIN" stroke={PARTY.PH} strokeWidth={3} dot={{ r: 4, fill: '#fff', stroke: PARTY.PH, strokeWidth: 2 }} />
                </LineChart>
            </ResponsiveContainer>
            <div className="overflow-x-auto mt-4">
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
                                <td className={`${t.tableCell} text-right tabular-nums ${t.subtext}`}>+{pct(r.anjakan)}</td>
                                <td className={t.tableCell}><StatusBadge status={r.status} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/* --- Jadual 2: turnout Cina+India minimum ikut sokongan Melayu --- */
function Jadual2({ data }) {
    const { t } = usePilihanrayaTheme();
    const chart = data.map((r) => ({
        sokongan: +(r.sokongan_melayu * 100).toFixed(0),
        min: +(r.turnout_min * 100).toFixed(1),
    }));
    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Jadual 2 — Turnout Cina+India Minimum untuk PH Menang</h3>
            <p className={`${t.subtext} text-sm mb-4`}>Mengikut tahap sokongan pengundi Melayu kepada PH. Nilai &gt; 100% bermakna mustahil dicapai.</p>
            <ResponsiveContainer width="100%" height={280}>
                <LineChart data={chart} margin={{ left: 0, right: 16, top: 8 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke={t.chartGrid} />
                    <XAxis dataKey="sokongan" stroke={t.chartTick} style={{ fontSize: '11px' }} unit="%" label={{ value: 'Sokongan Melayu kpd PH', position: 'insideBottom', offset: -4, fill: t.chartTick, fontSize: 11 }} />
                    <YAxis stroke={t.chartTick} style={{ fontSize: '11px' }} unit="%" />
                    <Tooltip contentStyle={t.tooltip} formatter={(v) => `${v}%`} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <ReferenceLine y={100} stroke={PARTY.ditolak} strokeDasharray="5 5" label={{ value: 'Had realistik 100%', fill: t.chartTick, fontSize: 10, position: 'insideTopRight' }} />
                    <Line type="monotone" dataKey="min" name="Turnout C+I MIN" stroke={PARTY.PN} strokeWidth={3} dot={{ r: 4, fill: '#fff', stroke: PARTY.PN, strokeWidth: 2 }} />
                </LineChart>
            </ResponsiveContainer>
            <div className="overflow-x-auto mt-4">
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
            </div>
        </div>
    );
}

/* --- Jadual 3: kesan peralihan undi PN 2022 --- */
function Jadual3({ data }) {
    const { t } = usePilihanrayaTheme();
    const chart = data.map((r) => ({
        name: `PN→PH ${pct(r.pn_ph, 0)}`,
        PH: Math.round(r.undi_ph),
        BN: Math.round(r.undi_bn),
    }));
    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Jadual 3 — Kesan Peralihan Undi PN 2022 (±2,999 undi)</h3>
            <p className={`${t.subtext} text-sm mb-4`}>
                Asas: undi 2022 — PH 3,579 / BN 8,956. Turnout Cina/India naik ke 75% memberi PH ±3,650 undi tambahan
                (sokongan 90%/40%). Undi PN dibahagi ikut nisbah senario di bawah.
            </p>
            <ResponsiveContainer width="100%" height={300}>
                <BarChart data={chart} margin={{ left: 10, right: 16 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={t.chartGrid} />
                    <XAxis dataKey="name" stroke={t.chartTick} style={{ fontSize: '10px' }} />
                    <YAxis stroke={t.chartTick} style={{ fontSize: '11px' }} />
                    <Tooltip contentStyle={t.tooltip} formatter={(v) => `${fmt(v)} undi`} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <ReferenceLine y={10897} stroke={PARTY.PEJUANG} strokeDasharray="6 3" label={{ value: 'Ambang menang', fill: t.chartTick, fontSize: 10, position: 'insideTopRight' }} />
                    <Bar dataKey="PH" name="Undi PH" fill={PARTY.PH} radius={[3, 3, 0, 0]} />
                    <Bar dataKey="BN" name="Undi BN" fill={PARTY.BN} radius={[3, 3, 0, 0]} />
                </BarChart>
            </ResponsiveContainer>
            <div className="overflow-x-auto mt-4">
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
            </div>
        </div>
    );
}

export default function Minima({ context, minima }) {
    const a = minima.andaian;
    return (
        <AuthenticatedLayout>
            <Head title="Pilihanraya — Minima Untuk Menang" />
            <PilihanrayaShell
                title="Minima Untuk PH Menang"
                subtitle={`${context.dun} · ${context.parlimen}, ${context.negeri} — pertandingan 1 lawan 1 (PH vs BN)`}
            >
                <div className={`${STATUS_STYLES['SUKAR']} rounded-xl px-4 py-3 text-sm mb-6 flex items-start gap-2`}>
                    <Target className="h-5 w-5 shrink-0 mt-0.5" />
                    <span>
                        <strong>Syarat menang:</strong> PH mesti memperoleh &gt; 50% undi keluar. Analisa di bawah menunjukkan
                        gabungan minimum turnout dan sokongan yang diperlukan mengikut kaum, serta kesan peralihan undi PN 2022.
                    </span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                    <KpiCard label="Jumlah Pengundi 2026" value={fmt(a.pengundi_melayu + a.pengundi_cina + a.pengundi_india)} icon={Users} sub="DPPR 2026" />
                    <KpiCard label="Pengundi Melayu" value={fmt(a.pengundi_melayu)} icon={Users} sub={`Turnout andaian ${pct(a.turnout_melayu, 0)}`} iconBg="bg-green-500/15" iconColor="text-green-500" />
                    <KpiCard label="Sokongan PH Cina" value={pct(a.sokongan_ph_cina, 0)} icon={Vote} sub="Andaian asas" iconBg="bg-rose-500/15" iconColor="text-rose-500" />
                    <KpiCard label="Sokongan PH India" value={pct(a.sokongan_ph_india, 0)} icon={TrendingDown} sub="Andaian asas" iconBg="bg-amber-500/15" iconColor="text-amber-500" />
                </div>

                <div className="mb-6">
                    <AndaianCard a={a} />
                </div>

                <div className="grid grid-cols-1 gap-6">
                    <Jadual1 data={minima.jadual1} />
                    <Jadual2 data={minima.jadual2} />
                    <Jadual3 data={minima.jadual3} />
                </div>
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
