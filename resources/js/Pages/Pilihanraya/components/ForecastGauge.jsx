import { PolarAngleAxis, RadialBar, RadialBarChart, ResponsiveContainer } from 'recharts';
import { usePilihanrayaTheme } from './PilihanrayaShell';

export default function ForecastGauge({ label, value, color = '#10b981', suffix = '%', height = 180 }) {
    const { t } = usePilihanrayaTheme();

    return (
        <div className={`${t.cardTight} text-center`}>
            <div className="relative">
                <ResponsiveContainer width="100%" height={height}>
                    <RadialBarChart
                        cx="50%"
                        cy="60%"
                        innerRadius="70%"
                        outerRadius="100%"
                        startAngle={210}
                        endAngle={-30}
                        data={[{ value }]}
                    >
                        <PolarAngleAxis type="number" domain={[0, 100]} angleAxisId={0} tick={false} />
                        <RadialBar
                            dataKey="value"
                            cornerRadius={8}
                            background={{ fill: t.chartGrid }}
                            fill={color}
                        />
                    </RadialBarChart>
                </ResponsiveContainer>
                <div className="absolute inset-0 flex flex-col items-center justify-center pt-6 pointer-events-none">
                    <span className="text-3xl font-bold" style={{ color }}>{value}{suffix}</span>
                </div>
            </div>
            <p className={`${t.kpiLabel} -mt-4`}>{label}</p>
        </div>
    );
}
