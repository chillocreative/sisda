import { RotateCcw } from 'lucide-react';
import { usePilihanrayaTheme } from './PilihanrayaShell';
import { DEFAULT_SLIDERS } from '../simulation/whatIfModel';

const GROUPS = [
    {
        title: 'Anjakan Kaum (mata % ke arah PH)',
        sliders: [
            { key: 'malaySwing', label: 'Anjakan Melayu', min: -20, max: 20, step: 1, unit: ' pt' },
            { key: 'chineseSwing', label: 'Anjakan Cina', min: -20, max: 20, step: 1, unit: ' pt' },
            { key: 'indianSwing', label: 'Anjakan India', min: -20, max: 20, step: 1, unit: ' pt' },
        ],
    },
    {
        title: 'Keluar Mengundi',
        sliders: [
            { key: 'youthTurnout', label: 'Belia (18-29)', min: 40, max: 95, step: 1, unit: '%' },
            { key: 'seniorTurnout', label: 'Warga Emas (50+)', min: 40, max: 95, step: 1, unit: '%' },
        ],
    },
    {
        title: 'Jentera Kempen',
        sliders: [
            { key: 'fenceConversion', label: 'Penukaran Atas Pagar', min: 0, max: 100, step: 5, unit: '%' },
            { key: 'campaignEffectiveness', label: 'Keberkesanan Kempen (→ PH)', min: 0, max: 100, step: 5, unit: '%' },
        ],
    },
];

export default function SliderPanel({ sliders, onChange }) {
    const { t } = usePilihanrayaTheme();

    return (
        <div className={t.card}>
            <div className="flex items-center justify-between mb-4">
                <h3 className={`text-lg font-semibold ${t.text}`}>Kawalan Senario</h3>
                <button type="button" onClick={() => onChange({ ...DEFAULT_SLIDERS })} className={t.buttonSecondary}>
                    <RotateCcw className="h-4 w-4" /> Set Semula
                </button>
            </div>
            <div className="space-y-6">
                {GROUPS.map((group) => (
                    <div key={group.title}>
                        <p className={`${t.subtext} text-xs font-semibold uppercase tracking-wider mb-3`}>{group.title}</p>
                        <div className="space-y-4">
                            {group.sliders.map((def) => {
                                const value = sliders[def.key] ?? DEFAULT_SLIDERS[def.key];
                                const changed = value !== DEFAULT_SLIDERS[def.key];

                                return (
                                    <div key={def.key}>
                                        <div className="flex items-center justify-between mb-1">
                                            <label className={`text-sm ${t.text}`}>{def.label}</label>
                                            <span className={`${t.badge} ${changed ? 'bg-emerald-500/15 text-emerald-500 border border-emerald-500/40' : `${t.subtext}`}`}>
                                                {value > 0 && def.min < 0 ? '+' : ''}{value}{def.unit}
                                            </span>
                                        </div>
                                        <input
                                            type="range"
                                            min={def.min}
                                            max={def.max}
                                            step={def.step}
                                            value={value}
                                            onChange={(e) => onChange({ ...sliders, [def.key]: Number(e.target.value) })}
                                            className="w-full accent-emerald-500"
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
