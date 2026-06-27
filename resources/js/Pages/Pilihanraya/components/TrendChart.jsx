import { Area, AreaChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { CHART_COLORS } from '../theme';
import { usePilihanrayaTheme } from './PilihanrayaShell';

export default function TrendChart({ data, title = 'Tren Sentimen Mingguan', height = 300 }) {
    const { t } = usePilihanrayaTheme();

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>{title}</h3>
            {data.length === 0 ? (
                <p className={`${t.subtext} text-sm py-12 text-center`}>Tiada data culaan dalam 12 minggu terakhir.</p>
            ) : (
                <ResponsiveContainer width="100%" height={height}>
                    <AreaChart data={data}>
                        <defs>
                            <linearGradient id="gradPutih" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#dc2626" stopOpacity={0.2} />
                                <stop offset="95%" stopColor="#dc2626" stopOpacity={0} />
                            </linearGradient>
                            <linearGradient id="gradHitam" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#0f172a" stopOpacity={0.15} />
                                <stop offset="95%" stopColor="#0f172a" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={t.chartGrid} />
                        <XAxis dataKey="minggu" stroke={t.chartTick} style={{ fontSize: '11px' }} />
                        <YAxis stroke={t.chartTick} style={{ fontSize: '11px' }} unit="%" />
                        <Tooltip contentStyle={t.tooltip} formatter={(v) => `${v}%`} />
                        <Legend wrapperStyle={{ fontSize: '12px' }} />
                        <Area type="monotone" dataKey="putih_pct" name="PH (Putih)" stroke="#dc2626" strokeWidth={3} fillOpacity={1} fill="url(#gradPutih)" dot={{ r: 3, fill: '#ffffff', stroke: '#dc2626', strokeWidth: 1.5 }} activeDot={{ r: 5, fill: '#ffffff', stroke: '#dc2626', strokeWidth: 2 }} />
                        <Area type="monotone" dataKey="hitam_pct" name="Pembangkang (Hitam)" stroke="#111827" strokeWidth={2} strokeDasharray="6 3" fillOpacity={1} fill="url(#gradHitam)" dot={{ r: 3, fill: '#111827', stroke: '#ffffff', strokeWidth: 1.5 }} activeDot={{ r: 5, fill: '#111827', stroke: '#ffffff', strokeWidth: 2 }} />
                        <Area type="monotone" dataKey="kelabu_pct" name="Atas Pagar" stroke={CHART_COLORS.kelabu} strokeWidth={2} strokeDasharray="5 5" fillOpacity={0} dot={{ r: 3, fill: CHART_COLORS.kelabu, stroke: '#ffffff', strokeWidth: 1 }} />
                    </AreaChart>
                </ResponsiveContainer>
            )}
        </div>
    );
}
