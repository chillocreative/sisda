import { useEffect, useState } from 'react';

// "Sel BIRU = input" — the blue editable cell used across the Pilihanraya
// tables (Borang 14, Simulasi Pilihanraya). Two modes:
//   mode="int"     → whole numbers (voter counts). Stores/returns the integer.
//   mode="percent" → the user types a percentage (e.g. 67.4); stores/returns
//                    the fraction (0.674). One decimal place, capped 0–100%.
export default function EditableCell({ value, onCommit, mode = 'int', max = null, invalid = false, className = '' }) {
    const toDisplay = (v) => {
        if (v == null || v === '') return '';
        return mode === 'percent' ? String(+(Number(v) * 100).toFixed(1)) : String(Math.round(Number(v)));
    };

    const [local, setLocal] = useState(toDisplay(value));
    useEffect(() => { setLocal(toDisplay(value)); }, [value]); // eslint-disable-line react-hooks/exhaustive-deps

    const parse = (raw) => {
        if (raw === '' || raw == null) return 0;
        const n = parseFloat(raw);
        if (Number.isNaN(n)) return 0;
        if (mode === 'percent') return Math.min(1, Math.max(0, n / 100));
        let v = Math.max(0, Math.round(n));
        if (max != null) v = Math.min(v, max);
        return v;
    };

    const commit = () => {
        const num = parse(local);
        setLocal(toDisplay(num));
        if (num !== (Number(value) || 0)) onCommit(num);
    };

    const border = invalid
        ? 'bg-rose-100 border-rose-400 focus:ring-rose-400'
        : 'bg-sky-100 border-sky-300 focus:ring-sky-400';

    return (
        <div className="relative inline-flex items-center">
            <input
                type="number"
                inputMode="decimal"
                min="0"
                max={mode === 'percent' ? 100 : max ?? undefined}
                step={mode === 'percent' ? '0.1' : '1'}
                value={local}
                onChange={(e) => setLocal(e.target.value)}
                onBlur={commit}
                onKeyDown={(e) => { if (e.key === 'Enter') e.currentTarget.blur(); }}
                className={`w-24 px-2 py-1 text-right text-sm rounded-md text-slate-900 border focus:ring-2 focus:outline-none tabular-nums ${border} ${mode === 'percent' ? 'pr-6' : ''} ${className}`}
                placeholder="0"
            />
            {mode === 'percent' && (
                <span className="pointer-events-none absolute right-2 text-xs text-slate-400">%</span>
            )}
        </div>
    );
}
