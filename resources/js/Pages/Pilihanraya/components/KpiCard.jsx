import { TrendingDown, TrendingUp } from 'lucide-react';
import { usePilihanrayaTheme } from './PilihanrayaShell';

export default function KpiCard({ label, value, sub = null, icon: Icon, iconBg = 'bg-emerald-500/15', iconColor = 'text-emerald-500', trend = null }) {
    const { t } = usePilihanrayaTheme();

    return (
        <div className={t.card}>
            <div className="flex items-center justify-between">
                <div className="min-w-0">
                    <p className={t.kpiLabel}>{label}</p>
                    <p className={t.kpiValue}>{value}</p>
                    {sub && <p className={`${t.subtext} text-xs mt-1`}>{sub}</p>}
                    {trend !== null && (
                        <p className={`text-xs mt-1 inline-flex items-center gap-1 ${trend >= 0 ? 'text-emerald-500' : 'text-red-500'}`}>
                            {trend >= 0 ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
                            {trend >= 0 ? '+' : ''}{trend}% berbanding 30 hari sebelumnya
                        </p>
                    )}
                </div>
                {Icon && (
                    <div className={`p-3 rounded-lg shrink-0 ${iconBg}`}>
                        <Icon className={`h-6 w-6 ${iconColor}`} />
                    </div>
                )}
            </div>
        </div>
    );
}
