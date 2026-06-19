import { usePilihanrayaTheme } from './PilihanrayaShell';
import { EMPTY_FILTERS } from '../filters';

/**
 * Cascading Negeri → Parlimen → KADUN filter plus an optional date
 * range. Pure controlled component — parent owns the filter state and
 * the tab data layer refetches when it changes.
 */
export default function FilterBar({ filters, onChange, negeriList, parlimenList, kadunList, showDates = true }) {
    const { t } = usePilihanrayaTheme();

    const parlimenOptions = filters.negeri_id
        ? parlimenList.filter((p) => String(p.negeri_id) === String(filters.negeri_id))
        : parlimenList;

    const selectedParlimen = parlimenList.find((p) => String(p.id) === String(filters.parlimen_id));
    const kadunOptions = filters.parlimen_id
        ? kadunList.filter((k) => String(k.bandar_id) === String(filters.parlimen_id))
        : kadunList;

    const set = (key, value) => {
        const next = { ...filters, [key]: value };
        if (key === 'negeri_id') {
            next.parlimen_id = '';
            next.kadun_id = '';
        }
        if (key === 'parlimen_id') {
            next.kadun_id = '';
        }
        onChange(next);
    };

    const reset = () => onChange({ ...EMPTY_FILTERS });

    return (
        <div className={`${t.cardTight} mb-6`}>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 items-end">
                <div>
                    <label className={t.label}>Negeri</label>
                    <select value={filters.negeri_id} onChange={(e) => set('negeri_id', e.target.value)} className={t.input}>
                        <option value="">Semua Negeri</option>
                        {negeriList.map((n) => (
                            <option key={n.id} value={n.id}>{n.nama}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className={t.label}>Parlimen</label>
                    <select value={filters.parlimen_id} onChange={(e) => set('parlimen_id', e.target.value)} className={t.input}>
                        <option value="">Semua Parlimen</option>
                        {parlimenOptions.map((p) => (
                            <option key={p.id} value={p.id}>{p.nama}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className={t.label}>KADUN</label>
                    <select value={filters.kadun_id} onChange={(e) => set('kadun_id', e.target.value)} className={t.input}>
                        <option value="">Semua KADUN {selectedParlimen ? `(${selectedParlimen.nama})` : ''}</option>
                        {kadunOptions.map((k) => (
                            <option key={k.id} value={k.id}>{k.nama}</option>
                        ))}
                    </select>
                </div>
                {showDates && (
                    <>
                        <div>
                            <label className={t.label}>Dari</label>
                            <input type="date" value={filters.tarikh_dari} onChange={(e) => set('tarikh_dari', e.target.value)} className={t.input} />
                        </div>
                        <div>
                            <label className={t.label}>Hingga</label>
                            <input type="date" value={filters.tarikh_hingga} onChange={(e) => set('tarikh_hingga', e.target.value)} className={t.input} />
                        </div>
                    </>
                )}
                <div>
                    <label className={t.label}>Umur Dari</label>
                    <input type="number" min="0" max="200" value={filters.umur_dari} onChange={(e) => set('umur_dari', e.target.value)} placeholder="cth. 18" className={t.input} />
                </div>
                <div>
                    <label className={t.label}>Umur Hingga</label>
                    <input type="number" min="0" max="200" value={filters.umur_hingga} onChange={(e) => set('umur_hingga', e.target.value)} placeholder="cth. 29" className={t.input} />
                </div>
                <div>
                    <label className={t.label}>Status Pengundi</label>
                    <select value={filters.status_pengundi} onChange={(e) => set('status_pengundi', e.target.value)} className={t.input}>
                        <option value="">Semua Pengundi</option>
                        <option value="baru">Pengundi Baru</option>
                        <option value="lama">Pengundi Lama</option>
                    </select>
                </div>
                <div>
                    <button type="button" onClick={reset} className={`${t.buttonSecondary} w-full justify-center`}>
                        Set Semula
                    </button>
                </div>
            </div>
        </div>
    );
}
