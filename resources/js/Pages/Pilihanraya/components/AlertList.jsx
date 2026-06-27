import { AlertTriangle, BellRing, Info } from 'lucide-react';
import { SEVERITY_STYLES } from '../theme';
import { usePilihanrayaTheme } from './PilihanrayaShell';

const ICONS = { high: AlertTriangle, medium: BellRing, low: Info };

const CARD_PALETTE = [
    { bg: '#ede9fe', border: '#c4b5fd' },
    { bg: '#e0f2fe', border: '#7dd3fc' },
    { bg: '#dcfce7', border: '#86efac' },
    { bg: '#fce7f3', border: '#f9a8d4' },
    { bg: '#ffedd5', border: '#fdba74' },
    { bg: '#f3e8ff', border: '#d8b4fe' },
    { bg: '#ccfbf1', border: '#5eead4' },
    { bg: '#fef9c3', border: '#fde047' },
];

function labelColor(str) {
    let h = 0;
    for (const c of (str || '')) h = (h * 31 + c.charCodeAt(0)) & 0xffffff;
    return CARD_PALETTE[Math.abs(h) % CARD_PALETTE.length];
}

export default function AlertList({ alerts }) {
    const { t, dark } = usePilihanrayaTheme();

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
                const color = !dark ? labelColor(alert.label || alert.rule_code) : null;

                return (
                    <div
                        key={`${alert.rule_code}-${alert.kawasan}-${i}`}
                        className={dark ? t.card : 'rounded-xl p-6 shadow-sm'}
                        style={color ? { backgroundColor: color.bg, border: `1px solid ${color.border}` } : undefined}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex items-start gap-3 min-w-0">
                                <Icon className={`h-5 w-5 shrink-0 mt-0.5 ${alert.severity === 'high' ? 'text-red-500 animate-pulse' : alert.severity === 'medium' ? 'text-amber-500' : 'text-blue-500'}`} />
                                <div className="min-w-0">
                                    <p className={`text-sm font-semibold ${dark ? t.text : 'text-slate-800'}`}>{alert.label} — {alert.kawasan}</p>
                                    <p className={`text-sm mt-1 ${dark ? t.subtext : 'text-slate-600'}`}>{alert.message}</p>
                                    <p className={`text-xs mt-2 font-medium ${dark ? t.subtext : 'text-slate-500'}`}>
                                        Tindakan disyorkan: <span className={dark ? t.text : 'text-slate-700'}>{alert.recommended_action}</span>
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
