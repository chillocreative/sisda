import { useMemo, useState } from 'react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { usePilihanrayaTheme } from './PilihanrayaShell';

const PAGE_SIZE = 20;

/**
 * Constituency ranking table with percentage-intensity heat cells,
 * client-side sorting and simple pagination.
 */
export default function HeatTable({ rows, title = 'Ranking KADUN' }) {
    const { t } = usePilihanrayaTheme();
    const [sortKey, setSortKey] = useState('score');
    const [sortDir, setSortDir] = useState('asc');
    const [page, setPage] = useState(0);

    const sorted = useMemo(() => {
        const dir = sortDir === 'asc' ? 1 : -1;
        const copy = [...rows];
        copy.sort((a, b) => {
            const av = a[sortKey];
            const bv = b[sortKey];
            // Mixed/null values (e.g. parlimen can be null) must not hit
            // the numeric path — NaN comparators scramble Array.sort.
            if (typeof av === 'string' || typeof bv === 'string') {
                return dir * String(av ?? '').localeCompare(String(bv ?? ''));
            }

            return dir * ((av ?? 0) - (bv ?? 0));
        });

        return copy;
    }, [rows, sortKey, sortDir]);

    const pages = Math.max(1, Math.ceil(sorted.length / PAGE_SIZE));
    const visible = sorted.slice(page * PAGE_SIZE, (page + 1) * PAGE_SIZE);

    const toggleSort = (key) => {
        if (sortKey === key) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortKey(key);
            setSortDir('desc');
        }
        setPage(0);
    };

    const heat = (pct, rgb) => ({
        backgroundColor: `rgba(${rgb}, ${Math.min(0.55, (pct / 100) * 0.7)})`,
    });

    const columns = [
        { key: 'name', label: 'KADUN' },
        { key: 'parlimen', label: 'Parlimen' },
        { key: 'roll_total', label: 'Daftar' },
        { key: 'canvassed', label: 'Diculaan' },
        { key: 'coverage_pct', label: 'Liputan %' },
        { key: 'putih_pct', label: 'Putih %' },
        { key: 'kelabu_pct', label: 'Kelabu %' },
        { key: 'hitam_pct', label: 'Hitam %' },
        { key: 'score', label: 'Skor' },
        { key: 'category', label: 'Kategori' },
    ];

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>{title}</h3>
            <div className="overflow-x-auto">
                <table className="min-w-full">
                    <thead>
                        <tr>
                            {columns.map((col) => (
                                <th key={col.key} className={`${t.tableHead} cursor-pointer select-none whitespace-nowrap`} onClick={() => toggleSort(col.key)}>
                                    <span className="inline-flex items-center gap-1">
                                        {col.label}
                                        {sortKey === col.key && (sortDir === 'asc' ? <ArrowUp className="h-3 w-3" /> : <ArrowDown className="h-3 w-3" />)}
                                    </span>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {visible.map((row) => (
                            <tr key={`${row.type}-${row.name}`} className={t.tableRow}>
                                <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{row.name}</td>
                                <td className={`${t.tableCell} whitespace-nowrap`}>{row.parlimen || '-'}</td>
                                <td className={t.tableCell}>{row.roll_total.toLocaleString()}</td>
                                <td className={t.tableCell}>{row.canvassed.toLocaleString()}</td>
                                <td className={t.tableCell} style={heat(row.coverage_pct, '59, 130, 246')}>{row.coverage_pct}%</td>
                                <td className={t.tableCell} style={heat(row.putih_pct, '16, 185, 129')}>{row.putih_pct}%</td>
                                <td className={t.tableCell} style={heat(row.kelabu_pct, '148, 163, 184')}>{row.kelabu_pct}%</td>
                                <td className={t.tableCell} style={heat(row.hitam_pct, '239, 68, 68')}>{row.hitam_pct}%</td>
                                <td className={`${t.tableCell} font-semibold`}>{row.score}</td>
                                <td className={t.tableCell}>
                                    <span
                                        className={t.badge}
                                        style={{ backgroundColor: `${row.category_color}26`, color: row.category_color, border: `1px solid ${row.category_color}66` }}
                                    >
                                        {row.category}{row.low_data ? ' *' : ''}
                                    </span>
                                </td>
                            </tr>
                        ))}
                        {visible.length === 0 && (
                            <tr>
                                <td colSpan={columns.length} className={`${t.tableCell} text-center py-8`}>Tiada data.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <div className="flex items-center justify-between mt-3">
                <p className={`${t.subtext} text-xs`}>* data culaan kurang 30 rekod — skor tidak boleh dipercayai</p>
                {pages > 1 && (
                    <div className="flex items-center gap-2">
                        <button type="button" disabled={page === 0} onClick={() => setPage(page - 1)} className={t.buttonSecondary}>‹</button>
                        <span className={`${t.subtext} text-xs`}>{page + 1} / {pages}</span>
                        <button type="button" disabled={page >= pages - 1} onClick={() => setPage(page + 1)} className={t.buttonSecondary}>›</button>
                    </div>
                )}
            </div>
        </div>
    );
}
