import { Bar, BarChart, CartesianGrid, LabelList, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { CHART_COLORS } from '../theme';
import { usePilihanrayaTheme } from './PilihanrayaShell';

/**
 * Horizontal population pyramid — male counts arrive negated from the
 * server so the bars mirror around zero; tick/tooltip formatters show
 * absolute values.
 */
export default function PopulationPyramid({ data, title = 'Piramid Penduduk (Umur × Jantina)', height = 320 }) {
    const { t } = usePilihanrayaTheme();
    const abs = (v) => Math.abs(v).toLocaleString();

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>{title}</h3>
            <ResponsiveContainer width="100%" height={height}>
                <BarChart data={data} layout="vertical" stackOffset="sign" margin={{ left: 10 }}>
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke={t.chartGrid} />
                    <XAxis type="number" stroke={t.chartTick} style={{ fontSize: '11px' }} tickFormatter={abs} />
                    <YAxis type="category" dataKey="band" stroke={t.chartTick} style={{ fontSize: '11px' }} width={50} />
                    <Tooltip contentStyle={t.tooltip} formatter={(v, name) => [abs(v), name]} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <Bar dataKey="lelaki" name="Lelaki" stackId="p" fill={CHART_COLORS.blue} radius={[4, 0, 0, 4]}>
                        <LabelList dataKey="lelaki" position="center" formatter={(v) => Math.abs(v).toLocaleString()} style={{ fill: '#ffffff', fontSize: '9px' }} />
                    </Bar>
                    <Bar dataKey="perempuan" name="Perempuan" stackId="p" fill={CHART_COLORS.violet} radius={[0, 4, 4, 0]}>
                        <LabelList dataKey="perempuan" position="center" formatter={(v) => v.toLocaleString()} style={{ fill: '#ffffff', fontSize: '9px' }} />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
