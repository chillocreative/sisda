import { usePilihanrayaTheme } from './PilihanrayaShell';

const LEGEND = [
    { label: 'Selamat', color: '#10b981' },
    { label: 'Cenderung Kuat', color: '#34d399' },
    { label: 'Cenderung', color: '#3b82f6' },
    { label: 'Berayun', color: '#f59e0b' },
    { label: 'Kritikal', color: '#f97316' },
    { label: 'Risiko Kalah', color: '#ef4444' },
];

/**
 * Visual seat map: Parlimen cards each containing their KADUN chips,
 * coloured by health category.
 */
export default function SeatHealthGrid({ parlimenRows, kadunRows }) {
    const { t } = usePilihanrayaTheme();

    const kadunByParlimen = kadunRows.reduce((acc, seat) => {
        const key = seat.parlimen || 'Lain-lain';
        (acc[key] = acc[key] || []).push(seat);

        return acc;
    }, {});

    const chip = (seat) => {
        const color = seat.low_data ? '#94a3b8' : seat.category_color;
        return (
            <div
                key={seat.name}
                className="rounded-lg px-3 py-2 text-xs font-medium"
                style={{
                    backgroundColor: `${color}22`,
                    border: `1px solid ${color}55`,
                    color,
                }}
                title={`Skor ${seat.score} — ${seat.category}${seat.low_data ? ' (data nipis)' : ''} | Liputan ${seat.coverage_pct}%`}
            >
                <div className="font-semibold">{seat.name}</div>
                <div className="opacity-80">
                    {seat.low_data ? 'Data nipis' : `Skor ${seat.score}`} · {seat.coverage_pct}% liputan
                </div>
            </div>
        );
    };

    return (
        <div className="space-y-6">
            <div className={t.cardTight}>
                <div className="flex flex-wrap gap-3">
                    {LEGEND.map((item) => (
                        <span key={item.label} className="inline-flex items-center gap-1.5 text-xs" style={{ color: item.color }}>
                            <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: item.color }} />
                            {item.label}
                        </span>
                    ))}
                </div>
            </div>

            {parlimenRows.length === 0 && kadunRows.length === 0 && (
                <div className={t.card}><p className={`${t.subtext} text-sm`}>Tiada data kerusi untuk penapis semasa.</p></div>
            )}

            {parlimenRows.map((parlimen) => (
                <div key={parlimen.name} className={t.card}>
                    <div className="flex flex-wrap items-center justify-between gap-2 mb-4">
                        <h3 className={`text-base font-semibold ${t.text}`}>Parlimen {parlimen.name}</h3>
                        <span
                            className={t.badge}
                            style={{
                                backgroundColor: `${parlimen.low_data ? '#94a3b8' : parlimen.category_color}26`,
                                color: parlimen.low_data ? '#94a3b8' : parlimen.category_color,
                                border: `1px solid ${parlimen.low_data ? '#94a3b8' : parlimen.category_color}66`,
                            }}
                        >
                            Skor {parlimen.score} — {parlimen.category}
                        </span>
                    </div>
                    <div className={`${t.subtext} text-xs mb-3`}>
                        {parlimen.roll_total.toLocaleString()} pengundi berdaftar · {parlimen.canvassed.toLocaleString()} diculaan ({parlimen.coverage_pct}%) ·
                        Putih {parlimen.putih_pct}% / Kelabu {parlimen.kelabu_pct}% / Hitam {parlimen.hitam_pct}%
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                        {(kadunByParlimen[parlimen.name] || []).map(chip)}
                        {(kadunByParlimen[parlimen.name] || []).length === 0 && (
                            <p className={`${t.subtext} text-xs col-span-full`}>Tiada KADUN diculaan di bawah parlimen ini.</p>
                        )}
                    </div>
                </div>
            ))}

            {(kadunByParlimen['Lain-lain'] || []).length > 0 && (
                <div className={t.card}>
                    <h3 className={`text-base font-semibold ${t.text} mb-3`}>KADUN Tanpa Parlimen Direkod</h3>
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                        {kadunByParlimen['Lain-lain'].map(chip)}
                    </div>
                </div>
            )}
        </div>
    );
}
