import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { CHART_COLORS } from '../theme';
import { usePilihanrayaTheme } from './PilihanrayaShell';

const COLOR_BY_KEY = {
    putih: CHART_COLORS.putih,
    hitam: CHART_COLORS.hitam,
    kelabu: CHART_COLORS.kelabu,
};

export default function SentimentDonut({ data, title = 'Sentimen Politik', height = 300 }) {
    const { t } = usePilihanrayaTheme();
    const total = data.reduce((sum, d) => sum + d.value, 0);

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>{title}</h3>
            <ResponsiveContainer width="100%" height={height}>
                <PieChart>
                    <Pie
                        data={data}
                        cx="50%"
                        cy="50%"
                        innerRadius={60}
                        outerRadius={100}
                        paddingAngle={2}
                        dataKey="value"
                    >
                        {data.map((entry) => (
                            <Cell key={entry.key} fill={COLOR_BY_KEY[entry.key] || CHART_COLORS.blue} />
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
