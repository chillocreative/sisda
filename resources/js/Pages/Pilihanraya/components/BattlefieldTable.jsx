import { useState } from 'react';
import { Crosshair, ShieldAlert, Scale, Users2, MapPinOff } from 'lucide-react';
import { usePilihanrayaTheme } from './PilihanrayaShell';

const LISTS = [
    { key: 'topSwing', label: 'Kerusi Berayun Utama', icon: Scale, metric: (r) => `Skor ${r.score}` },
    { key: 'vulnerable', label: 'Paling Terdedah', icon: ShieldAlert, metric: (r) => `Skor ${r.score}` },
    { key: 'fenceSitters', label: 'Atas Pagar Tertinggi', icon: Crosshair, metric: (r) => `${r.kelabu.toLocaleString()} kelabu (${r.kelabu_pct}%)` },
    { key: 'youthSeats', label: 'Pengundi Muda Tertinggi', icon: Users2, metric: (r) => `${r.youth_pct}% umur 18-29` },
    { key: 'lowCoverage', label: 'Liputan Terendah', icon: MapPinOff, metric: (r) => `${r.coverage_pct}% liputan` },
];

export default function BattlefieldTable({ data }) {
    const { t } = usePilihanrayaTheme();
    const [active, setActive] = useState('topSwing');

    const rows = data[active] || [];
    const activeDef = LISTS.find((l) => l.key === active);

    return (
        <div className={t.card}>
            <div className="flex flex-wrap gap-2 mb-4">
                {LISTS.map((list) => {
                    const Icon = list.icon;

                    return (
                        <button
                            key={list.key}
                            type="button"
                            onClick={() => setActive(list.key)}
                            className={active === list.key ? t.tabActive : t.tabInactive}
                        >
                            <Icon className="h-4 w-4" />
                            {list.label}
                        </button>
                    );
                })}
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full">
                    <thead>
                        <tr>
                            <th className={t.tableHead}>#</th>
                            <th className={t.tableHead}>KADUN</th>
                            <th className={t.tableHead}>Parlimen</th>
                            <th className={t.tableHead}>Metrik</th>
                            <th className={t.tableHead}>Putih / Kelabu / Hitam</th>
                            <th className={t.tableHead}>Liputan</th>
                            <th className={t.tableHead}>Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, i) => (
                            <tr key={row.name} className={t.tableRow}>
                                <td className={`${t.tableCell} font-semibold`}>{i + 1}</td>
                                <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{row.name}</td>
                                <td className={`${t.tableCell} whitespace-nowrap`}>{row.parlimen || '-'}</td>
                                <td className={`${t.tableCell} whitespace-nowrap`}>{activeDef.metric(row)}</td>
                                <td className={`${t.tableCell} whitespace-nowrap`}>
                                    <span style={{ color: '#10b981' }}>{row.putih_pct}%</span>{' / '}
                                    <span style={{ color: '#94a3b8' }}>{row.kelabu_pct}%</span>{' / '}
                                    <span style={{ color: '#ef4444' }}>{row.hitam_pct}%</span>
                                </td>
                                <td className={t.tableCell}>{row.coverage_pct}%</td>
                                <td className={t.tableCell}>
                                    <span
                                        className={t.badge}
                                        style={{ backgroundColor: `${row.category_color}26`, color: row.category_color, border: `1px solid ${row.category_color}66` }}
                                    >
                                        {row.category}
                                    </span>
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={7} className={`${t.tableCell} text-center py-8`}>Tiada kerusi memenuhi kriteria ini.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
