import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { CHART_COLORS } from '../theme';
import { usePilihanrayaTheme } from './PilihanrayaShell';

const COLOR_BY_KEY = {
    putih: '#dc2626',
    hitam: '#0f172a',
    kelabu: CHART_COLORS.kelabu,
};

const STROKE_BY_KEY = {
    putih: 'none',
    hitam: '#f8fafc',
    kelabu: 'none',
};

const RADIAN = Math.PI / 180;

export default function SentimentDonut({ data, title = 'Sentimen Politik', height = 320 }) {
    const { t } = usePilihanrayaTheme();
    const total = data.reduce((sum, d) => sum + d.value, 0);

    const renderLabel = ({ cx, cy, midAngle, outerRadius, value }) => {
        if (!value || total === 0) return null;
        const sin = Math.sin(-RADIAN * midAngle);
        const cos = Math.cos(-RADIAN * midAngle);
        const x = cx + (outerRadius + 32) * cos;
        const y = cy + (outerRadius + 32) * sin;
        return (
            <text x={x} y={y} textAnchor={cos >= 0 ? 'start' : 'end'} dominantBaseline="central" fontSize={11} fill={t.chartTick}>
                {((value / total) * 100).toFixed(1)}%
            </text>
        );
    };

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>{title}</h3>
            <ResponsiveContainer width="100%" height={height}>
                <PieChart>
                    <Pie
                        data={data}
                        cx="50%"
                        cy="50%"
                        outerRadius={95}
                        dataKey="value"
                        label={renderLabel}
                        labelLine
                    >
                        {data.map((entry) => (
                            <Cell key={entry.key} fill={COLOR_BY_KEY[entry.key] ?? CHART_COLORS.blue} stroke={STROKE_BY_KEY[entry.key] ?? 'none'} strokeWidth={2} />
                        ))}
                    </Pie>
                    <Tooltip
                        contentStyle={t.tooltip}
                        formatter={(value, name) => [
                            `${value.toLocaleString()} (${total > 0 ? ((value / total) * 100).toFixed(1) : 0}%)`,
                            name,
                        ]}
                    />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                </PieChart>
            </ResponsiveContainer>
        </div>
    );
}
