import { Fragment, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import {
    Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import { ChevronDown, ChevronRight, Loader2, UserX, Users } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';

const fmt = (n) => (n === null || n === undefined || Number.isNaN(Number(n)) ? '—'
    : Number(n).toLocaleString('en-MY'));

function Kpi({ label, value, sub, color }) {
    const { t } = usePilihanrayaTheme();
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div className="text-xs uppercase tracking-wide text-slate-500">{label}</div>
            <div className="mt-1 text-2xl font-bold" style={{ color: color || '#0f172a' }}>{value}</div>
            {sub && <div className={`mt-0.5 text-xs ${t.subtext}`}>{sub}</div>}
        </div>
    );
}

export default function KeanggotaanCard({ scope }) {
    const { t } = usePilihanrayaTheme();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [expanded, setExpanded] = useState({});

    const scopeKey = scope ? `${scope.level}-${scope.bandar_id}-${scope.kadun_id ?? 'all'}` : '';

    useEffect(() => {
        if (!scope?.bandar_id) return;
        let cancelled = false;
        setLoading(true);
        axios.get(route('pilihanraya.analisa.keanggotaan-card'), {
            params: { level: scope.level, bandar_id: scope.bandar_id, kadun_id: scope.kadun_id },
        })
            .then((res) => { if (!cancelled) setData(res.data); })
            .catch(() => { if (!cancelled) setData(null); })
            .finally(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
    }, [scopeKey]); // eslint-disable-line react-hooks/exhaustive-deps

    const chartData = useMemo(
        () => (data?.byDm || []).slice(0, 15).map((d) => ({ dm: d.nama, Anggota: d.anggota })),
        [data],
    );

    const lokByDm = useMemo(() => {
        const map = {};
        (data?.byLokaliti || []).forEach((l) => {
            (map[l.dm] = map[l.dm] || []).push(l);
        });
        return map;
    }, [data]);

    return (
        <div className={t.card}>
            <div className="mb-4 flex items-center gap-2">
                <Users className="h-5 w-5 text-emerald-500" />
                <h3 className={t.cardTitle + ' mb-0'}>Keanggotaan — {scope?.label || ''}</h3>
            </div>

            {loading ? (
                <div className="flex items-center gap-2 py-8 text-slate-500">
                    <Loader2 className="h-5 w-5 animate-spin" /> Memuatkan data keanggotaan…
                </div>
            ) : !data || data.no_batch ? (
                <div className="py-8 text-center text-slate-500">
                    <UserX className="mx-auto mb-2 h-8 w-8 text-slate-400" />
                    <p className="text-sm">Tiada batch keanggotaan aktif untuk kawasan ini.</p>
                </div>
            ) : (
                <div className="space-y-5">
                    {/* KPI row */}
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Kpi label="Jumlah Anggota" value={fmt(data.summary.anggota)} sub="dipadan dengan DPPR" />
                        <Kpi label="Dicula (Hitam)" value={fmt(data.summary.dicula)} sub={`${data.summary.pct_dicula}% daripada anggota`} color="#ef4444" />
                        <Kpi label="Penetrasi DPPR" value={data.summary.pct_penetrasi !== null ? `${data.summary.pct_penetrasi}%` : '—'} sub={`Daftar ${fmt(data.summary.roll)}`} color="#3b82f6" />
                        <Kpi label="Luar Kawasan / Tiada DPPR" value={fmt(data.summary.luar_kawasan)} sub="tidak dijumpai dalam roll" color="#f59e0b" />
                    </div>

                    {/* Anggota by DM chart */}
                    {chartData.length > 0 && (
                        <div>
                            <h4 className="mb-2 text-sm font-semibold text-slate-700">Anggota Mengikut Daerah Mengundi</h4>
                            <ResponsiveContainer width="100%" height={Math.max(240, chartData.length * 28)}>
                                <BarChart data={chartData} layout="vertical" margin={{ left: 20, right: 16 }}>
                                    <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke={t.chartGrid} />
                                    <XAxis type="number" stroke={t.chartTick} style={{ fontSize: '11px' }} />
                                    <YAxis type="category" dataKey="dm" stroke={t.chartTick} style={{ fontSize: '10px' }} width={150} />
                                    <Tooltip contentStyle={t.tooltip} />
                                    <Bar dataKey="Anggota" fill="#10b981" radius={[0, 3, 3, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    )}

                    {/* DM table with expandable lokaliti */}
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead>
                                <tr>
                                    <th className={t.tableHead}>Daerah Mengundi</th>
                                    <th className={`${t.tableHead} text-right`}>Anggota</th>
                                    <th className={`${t.tableHead} text-right`}>Dicula</th>
                                    <th className={`${t.tableHead} text-right`}>Daftar (DPPR)</th>
                                    <th className={`${t.tableHead} text-right`}>Penetrasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(data.byDm || []).map((d) => {
                                    const loks = lokByDm[d.nama] || [];
                                    const isOpen = expanded[d.nama];
                                    return (
                                        <Fragment key={d.nama}>
                                            <tr className={`${t.tableRow} ${loks.length ? 'cursor-pointer' : ''}`}
                                                onClick={() => loks.length && setExpanded((e) => ({ ...e, [d.nama]: !e[d.nama] }))}>
                                                <td className={`${t.tableCell} font-medium whitespace-nowrap`}>
                                                    <span className="inline-flex items-center gap-1">
                                                        {loks.length ? (isOpen ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />) : <span className="w-3.5" />}
                                                        {d.nama}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 text-sm text-right tabular-nums font-semibold text-slate-900">{fmt(d.anggota)}</td>
                                                <td className="px-3 py-2 text-sm text-right tabular-nums text-red-500">{fmt(d.dicula)}</td>
                                                <td className="px-3 py-2 text-sm text-right tabular-nums text-slate-500">{fmt(d.roll)}</td>
                                                <td className="px-3 py-2 text-sm text-right tabular-nums text-slate-600">{d.pct_penetrasi !== null ? `${d.pct_penetrasi}%` : '—'}</td>
                                            </tr>
                                            {isOpen && loks.map((l) => (
                                                <tr key={`${d.nama}-${l.nama}`} className="bg-slate-50">
                                                    <td className="px-3 py-1.5 pl-9 text-sm text-slate-600 whitespace-nowrap">{l.nama}</td>
                                                    <td className="px-3 py-1.5 text-sm text-right tabular-nums text-slate-700">{fmt(l.anggota)}</td>
                                                    <td className="px-3 py-1.5 text-sm text-right tabular-nums text-red-400">{fmt(l.dicula)}</td>
                                                    <td className="px-3 py-1.5" colSpan={2} />
                                                </tr>
                                            ))}
                                        </Fragment>
                                    );
                                })}
                                {(data.byDm || []).length === 0 && (
                                    <tr><td colSpan={5} className="px-3 py-6 text-center text-sm text-slate-400">Tiada anggota dipadan untuk kawasan ini.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Age bands */}
                    {(data.ageBands || []).some((b) => b.anggota > 0) && (
                        <div>
                            <h4 className="mb-2 text-sm font-semibold text-slate-700">Anggota Mengikut Jalur Umur</h4>
                            <div className="flex flex-wrap gap-2">
                                {data.ageBands.map((b) => (
                                    <div key={b.label} className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                                        <span className="font-semibold text-slate-900">{b.label}</span> · {fmt(b.anggota)}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
