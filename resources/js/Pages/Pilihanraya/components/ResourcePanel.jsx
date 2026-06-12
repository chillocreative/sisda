import { usePilihanrayaTheme } from './PilihanrayaShell';

export default function ResourcePanel({ data }) {
    const { t } = usePilihanrayaTheme();

    if (!data) return null;

    return (
        <div className="space-y-4">
            {data.summary && (
                <div className={t.card}>
                    <h3 className={t.cardTitle}>Rumusan Strategi</h3>
                    <p className={`${t.subtext} text-sm whitespace-pre-line`}>{data.summary}</p>
                </div>
            )}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {data.allocations.map((alloc, i) => (
                    <div key={alloc.kawasan} className={t.card}>
                        <div className="flex items-center justify-between mb-2">
                            <p className={`text-sm font-semibold ${t.text}`}>
                                <span className={`${t.subtext} mr-2`}>#{i + 1}</span>{alloc.kawasan}
                            </p>
                            <span className="text-sm font-bold text-emerald-500">{alloc.priority_score}</span>
                        </div>
                        <div className="h-2 rounded-full overflow-hidden mb-3" style={{ backgroundColor: t.chartGrid }}>
                            <div className="h-full rounded-full bg-emerald-500" style={{ width: `${alloc.priority_score}%` }} />
                        </div>
                        <p className={`${t.subtext} text-sm`}><span className="font-medium">Impak dijangka:</span> {alloc.expected_impact}</p>
                        <p className={`text-sm mt-1 ${t.text}`}><span className={`${t.subtext} font-medium`}>Tindakan:</span> {alloc.recommended_action}</p>
                    </div>
                ))}
            </div>
            {data.allocations.length === 0 && (
                <div className={t.card}>
                    <p className={`${t.subtext} text-sm`}>Tiada cadangan peruntukan untuk skop semasa.</p>
                </div>
            )}
        </div>
    );
}
