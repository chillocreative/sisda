import { useEffect, useRef, useState } from 'react';
import { Check, ChevronDown, Landmark, ListFilter, MapPin } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';

/**
 * Cascading Parlimen → DUN (Kawasan) dropdowns. Currently one curated dataset —
 * the controls are here so additional seats appear automatically once their
 * data is added.
 */
export function KawasanSelect({ list = [], value, onChange }) {
    const { t } = usePilihanrayaTheme();

    const parlimens = [...new Set(list.map((k) => k.parlimen))];
    const current = list.find((k) => k.id === value) || list[0];
    const selectedParlimen = current?.parlimen ?? parlimens[0];
    const dunOptions = list.filter((k) => k.parlimen === selectedParlimen);

    const onParlimenChange = (p) => {
        const firstDun = list.find((k) => k.parlimen === p);
        if (firstDun) onChange(firstDun.id);
    };

    return (
        <>
            <div className="min-w-[200px]">
                <label className={t.label}>
                    <span className="inline-flex items-center gap-1"><Landmark className="h-3.5 w-3.5" /> Parlimen</span>
                </label>
                <select value={selectedParlimen} onChange={(e) => onParlimenChange(e.target.value)} className={t.input}>
                    {parlimens.map((p) => (
                        <option key={p} value={p}>{p}</option>
                    ))}
                </select>
            </div>
            <div className="min-w-[200px]">
                <label className={t.label}>
                    <span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" /> DUN (Kawasan)</span>
                </label>
                <select value={value} onChange={(e) => onChange(e.target.value)} className={t.input}>
                    {dunOptions.map((k) => (
                        <option key={k.id} value={k.id}>{k.dun}</option>
                    ))}
                </select>
            </div>
        </>
    );
}

/**
 * Daerah Mengundi multi-select. Renders as a dropdown checklist so the tables
 * and charts can be narrowed to specific polling districts.
 */
export function DmFilter({ options = [], selected = [], onChange, label = 'Daerah Mengundi' }) {
    const { t } = usePilihanrayaTheme();
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    const allSelected = selected.length === options.length;
    const toggle = (dm) => {
        onChange(selected.includes(dm) ? selected.filter((d) => d !== dm) : [...selected, dm]);
    };
    const summary = allSelected
        ? `Semua (${options.length})`
        : selected.length === 0 ? 'Tiada dipilih' : `${selected.length} dipilih`;

    return (
        <div className="min-w-[220px]" ref={ref}>
            <label className={t.label}>
                <span className="inline-flex items-center gap-1"><ListFilter className="h-3.5 w-3.5" /> {label}</span>
            </label>
            <div className="relative">
                <button type="button" onClick={() => setOpen((o) => !o)} className={`${t.input} flex items-center justify-between`}>
                    <span className="truncate">{summary}</span>
                    <ChevronDown className={`h-4 w-4 shrink-0 transition ${open ? 'rotate-180' : ''}`} />
                </button>
                {open && (
                    <div className={`absolute z-30 mt-1 w-72 max-h-80 overflow-y-auto rounded-lg border shadow-lg ${t.card} p-2`}>
                        <div className="flex gap-2 mb-2">
                            <button type="button" onClick={() => onChange(options.slice())} className="flex-1 text-xs px-2 py-1 rounded bg-emerald-600 text-white hover:bg-emerald-500">Pilih Semua</button>
                            <button type="button" onClick={() => onChange([])} className={`flex-1 text-xs px-2 py-1 rounded border ${t.border} ${t.subtext} hover:opacity-80`}>Kosongkan</button>
                        </div>
                        {options.map((dm) => {
                            const on = selected.includes(dm);
                            return (
                                <button
                                    key={dm}
                                    type="button"
                                    onClick={() => toggle(dm)}
                                    className={`w-full flex items-center gap-2 px-2 py-1.5 rounded text-left text-sm ${t.text} hover:bg-emerald-500/10`}
                                >
                                    <span className={`flex h-4 w-4 items-center justify-center rounded border ${on ? 'bg-emerald-600 border-emerald-600' : t.border}`}>
                                        {on && <Check className="h-3 w-3 text-white" />}
                                    </span>
                                    <span className="truncate">{dm}</span>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}

/** Layout wrapper: a tidy filter bar card. */
export function FilterBarCard({ children }) {
    const { t } = usePilihanrayaTheme();
    return (
        <div className={`${t.cardTight} mb-6`}>
            <div className="flex flex-wrap items-end gap-4">{children}</div>
        </div>
    );
}
