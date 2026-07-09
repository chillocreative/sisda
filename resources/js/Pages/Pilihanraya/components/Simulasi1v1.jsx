import { useMemo, useState } from 'react';
import {
    Bar, BarChart, CartesianGrid, Legend, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import { RotateCcw, Trophy } from 'lucide-react';
import { usePilihanrayaTheme } from './PilihanrayaShell';
import KpiCard from './KpiCard';
import { PARTY, fmt, pct, safeDiv } from '../analisa/shared';

// Baseline dikalibrasi kpd PRN 2022 — DUN N01 Buloh Kasap (workbook SIMULASI-2026).
const BASELINE = {
    melayu: { label: 'Melayu', pengundi: 16668, turnout: 0.68, sokongan: 0.228, color: PARTY.PH },
    cina: { label: 'Cina', pengundi: 9443, turnout: 0.85, sokongan: 0.795, color: PARTY.BN },
    india: { label: 'India', pengundi: 2862, turnout: 0.85, sokongan: 0.795, color: PARTY.PN },
};

const KAUM_KEYS = ['melayu', 'cina', 'india'];

function Slider({ label, value, onChange, color }) {
    const { t } = usePilihanrayaTheme();
    return (
        <div>
            <div className="flex items-center justify-between mb-1">
                <span className={`${t.subtext} text-xs`}>{label}</span>
                <span className={`${t.text} text-sm font-bold tabular-nums`}>{pct(value, 1)}</span>
            </div>
            <input
                type="range" min="0" max="1" step="0.005" value={value}
                onChange={(e) => onChange(parseFloat(e.target.value))}
                className="w-full h-2 rounded-lg appearance-none cursor-pointer"
                style={{ accentColor: color }}
            />
        </div>
    );
}

export default function Simulasi1v1() {
    const { t } = usePilihanrayaTheme();
    const [state, setState] = useState(() => JSON.parse(JSON.stringify(BASELINE)));

    const set = (kaum, field, val) => setState((s) => ({ ...s, [kaum]: { ...s[kaum], [field]: val } }));
    const reset = () => setState(JSON.parse(JSON.stringify(BASELINE)));

    const result = useMemo(() => {
        const perKaum = KAUM_KEYS.map((k) => {
            const r = state[k];
            const keluar = r.pengundi * r.turnout;
            const ph = keluar * r.sokongan;
            const bn = keluar - ph;
            return { key: k, label: r.label, keluar, ph, bn, sokongan: r.sokongan };
        });
        const pengundi = KAUM_KEYS.reduce((s, k) => s + state[k].pengundi, 0);
        const keluar = perKaum.reduce((s, r) => s + r.keluar, 0);
        const ph = perKaum.reduce((s, r) => s + r.ph, 0);
        const bn = perKaum.reduce((s, r) => s + r.bn, 0);
        const perlu = Math.floor(keluar / 2) + 1;
        return {
            perKaum, pengundi, keluar, ph, bn, perlu,
            phPct: safeDiv(ph, keluar),
            turnoutAll: safeDiv(keluar, pengundi),
            majoriti: ph - bn,
            phMenang: ph > bn,
        };
    }, [state]);

    const chartData = result.perKaum.map((r) => ({ name: r.label, PH: Math.round(r.ph), BN: Math.round(r.bn) }));
    const cellNum = 'px-3 py-2 text-sm text-right tabular-nums';

    return (
        <div className="space-y-6">
            <div className={`${result.phMenang ? 'bg-emerald-500/10 border-emerald-500/40' : 'bg-blue-500/10 border-blue-500/40'} border rounded-xl px-4 py-3 flex items-center justify-between gap-4`}>
                <div className="flex items-center gap-3">
                    <Trophy className={`h-6 w-6 ${result.phMenang ? 'text-emerald-500' : 'text-blue-500'}`} />
                    <div>
                        <p className={`text-lg font-bold ${result.phMenang ? 'text-emerald-600' : 'text-blue-600'}`}>
                            {result.phMenang ? 'PH MENANG' : 'BN MENANG'}
                        </p>
                        <p className={`${t.subtext} text-xs`}>
                            Simulasi pertandingan 1 lawan 1 (PH vs BN) — kalibrasi PRN 2022
                        </p>
                    </div>
                </div>
                <div className="text-right">
                    <p className={`${t.subtext} text-xs`}>Majoriti</p>
                    <p className={`text-xl font-bold tabular-nums ${result.majoriti >= 0 ? 'text-emerald-600' : 'text-blue-600'}`}>
                        {result.majoriti >= 0 ? '+' : ''}{fmt(result.majoriti)}
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <KpiCard label="Undi Keluar" value={fmt(result.keluar)} sub={`${pct(result.turnoutAll, 1)} turnout keseluruhan`} />
                <KpiCard label="Undi PH" value={fmt(result.ph)} sub={pct(result.phPct)} iconBg="bg-rose-500/15" iconColor="text-rose-500" />
                <KpiCard label="Undi BN" value={fmt(result.bn)} sub={pct(1 - result.phPct)} iconBg="bg-blue-500/15" iconColor="text-blue-500" />
                <KpiCard label="Undi Diperlukan (50%+1)" value={fmt(result.perlu)} sub={`Jumlah pengundi ${fmt(result.pengundi)}`} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Input panel */}
                <div className={t.card}>
                    <div className="flex items-center justify-between mb-4">
                        <h3 className={t.cardTitle + ' mb-0'}>Andaian Senario 2026</h3>
                        <button type="button" onClick={reset} className={t.buttonSecondary}>
                            <RotateCcw className="h-4 w-4" /> Set Semula
                        </button>
                    </div>
                    <p className={`${t.subtext} text-sm mb-4`}>
                        Laraskan turnout dan sokongan PH mengikut kaum. Baki peratus sokongan dikira sebagai undi BN.
                    </p>
                    <div className="space-y-5">
                        {KAUM_KEYS.map((k) => (
                            <div key={k} className={`rounded-lg border ${t.border} p-4`}>
                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-sm font-semibold" style={{ color: state[k].color }}>{state[k].label}</span>
                                    <span className={`${t.subtext} text-xs`}>{fmt(state[k].pengundi)} pengundi</span>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <Slider label="Turnout" value={state[k].turnout} onChange={(v) => set(k, 'turnout', v)} color={state[k].color} />
                                    <Slider label="Sokongan PH" value={state[k].sokongan} onChange={(v) => set(k, 'sokongan', v)} color={state[k].color} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Chart */}
                <div className={t.card}>
                    <h3 className={t.cardTitle}>Undi PH vs BN Mengikut Kaum</h3>
                    <ResponsiveContainer width="100%" height={340}>
                        <BarChart data={chartData} margin={{ left: 10, right: 16 }}>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={t.chartGrid} />
                            <XAxis dataKey="name" stroke={t.chartTick} style={{ fontSize: '12px' }} />
                            <YAxis stroke={t.chartTick} style={{ fontSize: '11px' }} />
                            <Tooltip contentStyle={t.tooltip} formatter={(v) => `${fmt(v)} undi`} />
                            <Legend wrapperStyle={{ fontSize: '12px' }} />
                            <Bar dataKey="PH" name="Undi PH" fill={PARTY.PH} radius={[3, 3, 0, 0]} />
                            <Bar dataKey="BN" name="Undi BN" fill={PARTY.BN} radius={[3, 3, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </div>

            {/* Results table */}
            <div className={t.card}>
                <h3 className={t.cardTitle}>Keputusan Simulasi</h3>
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead>
                            <tr>
                                <th className={t.tableHead}>Kaum</th>
                                <th className={t.tableHead + ' text-right'}>Undi Keluar</th>
                                <th className={t.tableHead + ' text-right'}>Undi PH</th>
                                <th className={t.tableHead + ' text-right'}>Undi BN</th>
                                <th className={t.tableHead + ' text-right'}>% PH (kaum)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {result.perKaum.map((r) => (
                                <tr key={r.key} className={t.tableRow}>
                                    <td className={`${t.tableCell} font-medium`}>{r.label}</td>
                                    <td className={`${cellNum} ${t.text}`}>{fmt(r.keluar)}</td>
                                    <td className={cellNum} style={{ color: PARTY.PH }}>{fmt(r.ph)}</td>
                                    <td className={cellNum} style={{ color: PARTY.BN }}>{fmt(r.bn)}</td>
                                    <td className={`${cellNum} ${t.subtext}`}>{pct(r.sokongan)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className={`border-t-2 ${t.border} font-bold ${t.text}`}>
                                <td className="px-3 py-3">JUMLAH</td>
                                <td className={cellNum}>{fmt(result.keluar)}</td>
                                <td className={cellNum} style={{ color: PARTY.PH }}>{fmt(result.ph)}</td>
                                <td className={cellNum} style={{ color: PARTY.BN }}>{fmt(result.bn)}</td>
                                <td className={cellNum}>{pct(result.phPct)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p className={`${t.subtext} text-xs mt-4`}>
                    Nota: Dalam pertandingan 1 lawan 1, pengundi PN 2022 (majoriti Melayu) perlu memilih BN, PH, atau tidak
                    keluar — laraskan turnout / sokongan Melayu di atas untuk menguji senario.
                </p>
            </div>
        </div>
    );
}
