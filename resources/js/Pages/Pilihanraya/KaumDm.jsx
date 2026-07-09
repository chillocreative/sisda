import { useMemo } from 'react';
import { Head } from '@inertiajs/react';
import {
    Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer,
    Tooltip, XAxis, YAxis,
} from 'recharts';
import { Users } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import KpiCard from './components/KpiCard';
import { KAUM, KAUM_LABEL, fmt, pct, safeDiv } from './analisa/shared';

function CompositionBars({ rows }) {
    const { t } = usePilihanrayaTheme();
    const data = useMemo(
        () => [...rows].sort((a, b) => b.jumlah - a.jumlah).map((r) => ({
            dm: r.dm, Melayu: r.melayu, Cina: r.cina, India: r.india, 'Lain-lain': r.lain,
        })),
        [rows],
    );

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Komposisi Kaum Mengikut Daerah Mengundi</h3>
            <ResponsiveContainer width="100%" height={Math.max(420, data.length * 34)}>
                <BarChart data={data} layout="vertical" margin={{ left: 20, right: 16 }}>
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke={t.chartGrid} />
                    <XAxis type="number" stroke={t.chartTick} style={{ fontSize: '11px' }} />
                    <YAxis type="category" dataKey="dm" stroke={t.chartTick} style={{ fontSize: '10px' }} width={165} />
                    <Tooltip contentStyle={t.tooltip} formatter={(v) => fmt(v)} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <Bar dataKey="Melayu" stackId="k" fill={KAUM.melayu} />
                    <Bar dataKey="Cina" stackId="k" fill={KAUM.cina} />
                    <Bar dataKey="India" stackId="k" fill={KAUM.india} />
                    <Bar dataKey="Lain-lain" stackId="k" fill={KAUM.lain} radius={[0, 3, 3, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

function CompositionDonut({ totals }) {
    const { t } = usePilihanrayaTheme();
    const data = [
        { name: KAUM_LABEL.melayu, value: totals.melayu, color: KAUM.melayu },
        { name: KAUM_LABEL.cina, value: totals.cina, color: KAUM.cina },
        { name: KAUM_LABEL.india, value: totals.india, color: KAUM.india },
        { name: KAUM_LABEL.lain, value: totals.lain, color: KAUM.lain },
    ].filter((d) => d.value > 0);

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Komposisi Kaum Keseluruhan DUN</h3>
            <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                    <Pie data={data} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={65} outerRadius={110} paddingAngle={2}>
                        {data.map((d) => <Cell key={d.name} fill={d.color} stroke="transparent" />)}
                    </Pie>
                    <Tooltip contentStyle={t.tooltip} formatter={(v) => `${fmt(v)} (${pct(safeDiv(v, totals.jumlah))})`} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                </PieChart>
            </ResponsiveContainer>
        </div>
    );
}

function KaumTable({ rows, totals }) {
    const { t } = usePilihanrayaTheme();
    const cellNum = 'px-3 py-2 text-sm text-right tabular-nums';
    const kaumCell = (val, jumlah, color) => (
        <>
            <td className={`${cellNum} font-semibold ${t.text}`}>{fmt(val)}</td>
            <td className={`${cellNum} ${t.subtext}`}>
                <span style={{ color }}>{pct(safeDiv(val, jumlah))}</span>
            </td>
        </>
    );

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Jadual Anggaran Kaum Mengikut Daerah Mengundi</h3>
            <div className="overflow-x-auto">
                <table className="min-w-full">
                    <thead>
                        <tr>
                            <th className={t.tableHead}>Bil</th>
                            <th className={t.tableHead}>Daerah Mengundi</th>
                            <th className={t.tableHead + ' text-right'}>Melayu</th>
                            <th className={t.tableHead + ' text-right'}>%</th>
                            <th className={t.tableHead + ' text-right'}>Cina</th>
                            <th className={t.tableHead + ' text-right'}>%</th>
                            <th className={t.tableHead + ' text-right'}>India</th>
                            <th className={t.tableHead + ' text-right'}>%</th>
                            <th className={t.tableHead + ' text-right'}>Lain-lain</th>
                            <th className={t.tableHead + ' text-right'}>%</th>
                            <th className={t.tableHead + ' text-right'}>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.dm} className={t.tableRow}>
                                <td className={`${t.tableCell} ${t.subtext}`}>{r.bil}</td>
                                <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{r.dm}</td>
                                {kaumCell(r.melayu, r.jumlah, KAUM.melayu)}
                                {kaumCell(r.cina, r.jumlah, KAUM.cina)}
                                {kaumCell(r.india, r.jumlah, KAUM.india)}
                                {kaumCell(r.lain, r.jumlah, KAUM.lain)}
                                <td className={`${cellNum} font-bold ${t.text}`}>{fmt(r.jumlah)}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className={`border-t-2 ${t.border} font-bold ${t.text}`}>
                            <td className="px-3 py-3" colSpan={2}>JUMLAH KESELURUHAN</td>
                            <td className={cellNum}>{fmt(totals.melayu)}</td>
                            <td className={cellNum} style={{ color: KAUM.melayu }}>{pct(safeDiv(totals.melayu, totals.jumlah))}</td>
                            <td className={cellNum}>{fmt(totals.cina)}</td>
                            <td className={cellNum} style={{ color: KAUM.cina }}>{pct(safeDiv(totals.cina, totals.jumlah))}</td>
                            <td className={cellNum}>{fmt(totals.india)}</td>
                            <td className={cellNum} style={{ color: KAUM.india }}>{pct(safeDiv(totals.india, totals.jumlah))}</td>
                            <td className={cellNum}>{fmt(totals.lain)}</td>
                            <td className={cellNum} style={{ color: KAUM.lain }}>{pct(safeDiv(totals.lain, totals.jumlah))}</td>
                            <td className={cellNum}>{fmt(totals.jumlah)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p className={`${t.subtext} text-xs mt-4`}>
                Nota: Kaum dianggar daripada corak nama pengundi (BIN/BINTI = Melayu; A/L, A/P, S/O, D/O = India;
                ANAK = Lain-lain; selebihnya = Cina). Angka sebenar mungkin berbeza sedikit kerana nama mualaf,
                kahwin campur dan Bumiputera lain.
            </p>
        </div>
    );
}

export default function KaumDm({ context, rows, totals }) {
    return (
        <AuthenticatedLayout>
            <Head title="Pilihanraya — Kaum Mengikut DM" />
            <PilihanrayaShell
                title="Analisa Kaum Mengikut Daerah Mengundi"
                subtitle={`${context.dun} · ${context.parlimen}, ${context.negeri} — anggaran DPPR SPR`}
            >
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                    <KpiCard label="Jumlah Pengundi" value={fmt(totals.jumlah)} icon={Users} sub={`${context.dun}`} />
                    <KpiCard label="Melayu" value={fmt(totals.melayu)} sub={pct(safeDiv(totals.melayu, totals.jumlah))} icon={Users} iconBg="bg-green-500/15" iconColor="text-green-500" />
                    <KpiCard label="Cina" value={fmt(totals.cina)} sub={pct(safeDiv(totals.cina, totals.jumlah))} icon={Users} iconBg="bg-red-500/15" iconColor="text-red-500" />
                    <KpiCard label="India" value={fmt(totals.india)} sub={pct(safeDiv(totals.india, totals.jumlah))} icon={Users} iconBg="bg-amber-500/15" iconColor="text-amber-500" />
                </div>

                {/* Konsep 1 — Graf di atas */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <div className="lg:col-span-2">
                        <CompositionBars rows={rows} />
                    </div>
                    <CompositionDonut totals={totals} />
                </div>

                {/* Konsep 2 — Jadual di bawah */}
                <KaumTable rows={rows} totals={totals} />
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
