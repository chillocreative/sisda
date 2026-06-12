import { AlertTriangle, BellRing, Info } from 'lucide-react';
import { SEVERITY_STYLES } from '../theme';
import { usePilihanrayaTheme } from './PilihanrayaShell';

const ICONS = { high: AlertTriangle, medium: BellRing, low: Info };

export default function AlertList({ alerts }) {
    const { t } = usePilihanrayaTheme();

    if (!alerts.length) {
        return (
            <div className={t.card}>
                <p className={`${t.subtext} text-sm`}>Tiada amaran awal — keadaan stabil berdasarkan data semasa.</p>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {alerts.map((alert, i) => {
                const style = SEVERITY_STYLES[alert.severity] || SEVERITY_STYLES.low;
                const Icon = ICONS[alert.severity] || Info;

                return (
                    <div key={`${alert.rule_code}-${alert.kawasan}-${i}`} className={t.card}>
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex items-start gap-3 min-w-0">
                                <Icon className={`h-5 w-5 shrink-0 mt-0.5 ${alert.severity === 'high' ? 'text-red-500 animate-pulse' : alert.severity === 'medium' ? 'text-amber-500' : 'text-blue-500'}`} />
                                <div className="min-w-0">
                                    <p className={`text-sm font-semibold ${t.text}`}>{alert.label} — {alert.kawasan}</p>
                                    <p className={`${t.subtext} text-sm mt-1`}>{alert.message}</p>
                                    <p className={`text-xs mt-2 font-medium ${t.text}`}>
                                        <span className={t.subtext}>Tindakan disyorkan:</span> {alert.recommended_action}
                                    </p>
                                </div>
                            </div>
                            <span className={`${t.badge} ${style.chip} shrink-0`}>{style.label}</span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
