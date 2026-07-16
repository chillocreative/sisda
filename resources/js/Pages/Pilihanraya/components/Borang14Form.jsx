import { Loader2, Check, AlertCircle } from 'lucide-react';
import { usePilihanrayaTheme } from './PilihanrayaShell';
import EditableCell from './EditableCell';
import DragScroll from '../analisa/DragScroll';

/* ------------------------------- helpers ------------------------------- */

export const fmt = (n) => (n == null || Number.isNaN(n) ? '0' : Number(n).toLocaleString('en-MY'));
export const pct = (num, den) => (den > 0 ? `${((num / den) * 100).toFixed(1)}%` : '—');
export const cellKey = (pusat, saluran, slot) => `${pusat ?? ''}|${saluran}|${slot}`;

// Undi Awal & Undi Pos are combined into a single row only for DUN Buloh Kasap.
export const BULOH_KASAP_KADUN_ID = 41;

// Flatten reference into one block per Pusat Mengundi (a DM may have several).
export function toBlocks(reference) {
    if (!reference) return [];
    return reference.daerah_mengundi.flatMap((dm) =>
        dm.pusat_mengundi.map((p) => ({
            dm: dm.nama,
            pusat: p.nama,
            berdaftar: p.jumlah_berdaftar ?? p.saluran.reduce((s, x) => s + (x.berdaftar || 0), 0),
            saluran: p.saluran,
        })),
    );
}

/* --------------------------- lead highlighting ------------------------- */

// Classify each value in a row against the row max: the highest lead(s) win
// (green), the rest trail (red). All-zero rows stay neutral.
export function leadStatus(values) {
    const max = Math.max(0, ...values);
    if (max <= 0) return values.map(() => 'none');
    return values.map((v) => (v === max ? 'lead' : 'low'));
}

export function LeadSquare({ status }) {
    if (status === 'none') return null;
    return (
        <span
            className={`inline-block h-3 w-3 rounded-sm shrink-0 ${status === 'lead' ? 'bg-emerald-500' : 'bg-rose-500'}`}
            aria-hidden="true"
        />
    );
}

export const totalBgClass = (status, t) => (
    status === 'lead' ? 'bg-emerald-100 text-emerald-800'
        : status === 'low' ? 'bg-rose-100 text-rose-800'
            : t.text
);

/* --------------------------- save-status dot ---------------------------- */

// Per-cell autosave feedback: blank when untouched, quiet spinner while the
// request is in flight, a quiet green tick that fades on success, and an
// unmissable, non-auto-dismissing red icon on failure (see cellStatus in
// Borang14.jsx — the failure must stay visible until the cell is re-saved).
export function SaveStatusDot({ status }) {
    if (!status) return <span className="inline-block w-3.5" aria-hidden="true" />;
    if (status === 'saving') return <Loader2 className="h-3.5 w-3.5 animate-spin text-slate-400" aria-label="Menyimpan…" />;
    if (status === 'saved') return <Check className="h-3.5 w-3.5 text-emerald-500" aria-label="Disimpan" />;
    return (
        <AlertCircle
            className="h-3.5 w-3.5 text-rose-500"
            aria-label="Gagal disimpan"
            title="Gagal disimpan — ubah nilai sel ini untuk cuba semula"
        />
    );
}

/* --------------------------- per-pusat table --------------------------- */

export function VoteTable({ block, partyNames, votes, onSave, anchorId, cellStatus = {} }) {
    const { t } = usePilihanrayaTheme();
    const nParties = partyNames.length;

    const rows = block.saluran.map((s) => {
        const slots = Array.from({ length: nParties }, (_, i) =>
            votes[cellKey(block.pusat, String(s.no), i + 1)] ?? 0);
        const keluar = slots.reduce((a, b) => a + b, 0);
        return { no: s.no, berdaftar: s.berdaftar, slots, keluar, status: leadStatus(slots) };
    });

    const totals = {
        slots: Array.from({ length: nParties }, (_, i) => rows.reduce((a, r) => a + r.slots[i], 0)),
        keluar: rows.reduce((a, r) => a + r.keluar, 0),
        berdaftar: rows.reduce((a, r) => a + (r.berdaftar || 0), 0),
    };
    const totalStatus = leadStatus(totals.slots);

    return (
        <div id={anchorId} className={`${t.card} p-4 scroll-mt-24`}>
            <div className="mb-3">
                <div className={`text-xs font-semibold uppercase tracking-wider ${t.subtext}`}>DM: {block.dm}</div>
                <div className={`text-sm font-bold ${t.text}`}>Pusat Mengundi: {block.pusat}</div>
            </div>
            <DragScroll>
                <table className="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th className={`${t.tableHead} whitespace-nowrap`}>Saluran</th>
                            {partyNames.map((p, i) => (
                                <th key={i} className={`${t.tableHead} whitespace-nowrap text-right`}>{p}</th>
                            ))}
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Jumlah Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Berdaftar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Turnout</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Tak Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Tak Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.no} className={t.tableRow}>
                                <td className={`${t.tableCell} font-medium whitespace-nowrap`}>Saluran {r.no}</td>
                                {r.slots.map((v, i) => {
                                    const key = cellKey(block.pusat, String(r.no), i + 1);
                                    return (
                                        <td key={i} className="px-2 py-1">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <LeadSquare status={r.status[i]} />
                                                <SaveStatusDot status={cellStatus[key]} />
                                                <EditableCell
                                                    value={v}
                                                    invalid={cellStatus[key] === 'error'}
                                                    max={r.berdaftar > 0 ? Math.max(0, r.berdaftar - (r.keluar - v)) : null}
                                                    onCommit={(undi) => onSave(block.pusat, String(r.no), i + 1, undi)}
                                                />
                                            </div>
                                        </td>
                                    );
                                })}
                                <td className={`${t.tableCell} text-right font-semibold`}>{fmt(r.keluar)}</td>
                                <td className={`${t.tableCell} text-right`}>{fmt(r.berdaftar)}</td>
                                <td className={`${t.tableCell} text-right`}>{pct(r.keluar, r.berdaftar)}</td>
                                <td className={`${t.tableCell} text-right`}>{fmt((r.berdaftar || 0) - r.keluar)}</td>
                                <td className={`${t.tableCell} text-right`}>{pct((r.berdaftar || 0) - r.keluar, r.berdaftar)}</td>
                            </tr>
                        ))}
                        <tr className={`border-t-2 ${t.border} font-bold`}>
                            <td className={`${t.tableCell} font-bold whitespace-nowrap`}>Jumlah Undi</td>
                            {totals.slots.map((v, i) => (
                                <td key={i} className={`px-3 py-2 text-sm text-right font-bold ${totalBgClass(totalStatus[i], t)}`}>{fmt(v)}</td>
                            ))}
                            <td className={`${t.tableCell} text-right font-bold`}>{fmt(totals.keluar)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{fmt(totals.berdaftar)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{pct(totals.keluar, totals.berdaftar)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{fmt(totals.berdaftar - totals.keluar)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{pct(totals.berdaftar - totals.keluar, totals.berdaftar)}</td>
                        </tr>
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

/* ----------------------- undi awal / undi pos -------------------------- */

export function UndiAwalPosTable({ partyNames, votes, onSave, rows, cellStatus = {} }) {
    const { t } = usePilihanrayaTheme();
    const nParties = partyNames.length;

    return (
        <div className={`${t.card} p-4`}>
            <div className={`text-sm font-bold ${t.text} mb-3`}>Undi Awal & Undi Pos</div>
            <DragScroll>
                <table className="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th className={`${t.tableHead} whitespace-nowrap`}>Saluran</th>
                            {partyNames.map((p, i) => (
                                <th key={i} className={`${t.tableHead} whitespace-nowrap text-right`}>{p}</th>
                            ))}
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Jumlah Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Berdaftar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Turnout</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Tak Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Tak Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map(({ label, berdaftar }) => {
                            const slots = Array.from({ length: nParties }, (_, i) =>
                                votes[cellKey('', label, i + 1)] ?? 0);
                            const keluar = slots.reduce((a, b) => a + b, 0);
                            const status = leadStatus(slots);
                            return (
                                <tr key={label} className={t.tableRow}>
                                    <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{label}</td>
                                    {slots.map((v, i) => {
                                        const key = cellKey('', label, i + 1);
                                        return (
                                            <td key={i} className="px-2 py-1">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <LeadSquare status={status[i]} />
                                                    <SaveStatusDot status={cellStatus[key]} />
                                                    <EditableCell
                                                        value={v}
                                                        invalid={cellStatus[key] === 'error'}
                                                        max={null}
                                                        onCommit={(undi) => onSave('', label, i + 1, undi)}
                                                    />
                                                </div>
                                            </td>
                                        );
                                    })}
                                    <td className={`${t.tableCell} text-right font-semibold`}>{fmt(keluar)}</td>
                                    <td className={`${t.tableCell} text-right`}>{fmt(berdaftar)}</td>
                                    <td className={`${t.tableCell} text-right`}>{pct(keluar, berdaftar)}</td>
                                    <td className={`${t.tableCell} text-right`}>{fmt(Math.max(0, berdaftar - keluar))}</td>
                                    <td className={`${t.tableCell} text-right`}>{pct(Math.max(0, berdaftar - keluar), berdaftar)}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

/* --------------------------- grand summary ----------------------------- */

// Bottom-of-page rollup across every pusat mengundi + undi awal & pos:
// per-party grand totals, total turnout (sum of parties), and overall %.
export function GrandSummary({ partyNames, totals }) {
    const { t } = usePilihanrayaTheme();
    const status = leadStatus(totals.partyTotals);

    const tileTone = (s) => (
        s === 'lead' ? 'border-emerald-300 bg-emerald-50'
            : s === 'low' ? 'border-rose-300 bg-rose-50'
                : `${t.border} bg-slate-50`
    );
    const valueTone = (s) => (
        s === 'lead' ? 'text-emerald-700'
            : s === 'low' ? 'text-rose-700'
                : t.text
    );

    return (
        <div className={`${t.card} mt-4 mb-4`}>
            <div className="mb-4">
                <div className={`text-sm font-bold ${t.text}`}>Ringkasan Keseluruhan</div>
                <div className={`text-xs ${t.subtext}`}>Semua pusat mengundi termasuk undi awal &amp; undi pos</div>
            </div>
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                {partyNames.map((name, i) => (
                    <div key={i} className={`rounded-xl border p-4 ${tileTone(status[i])}`}>
                        <div className={`text-xs font-semibold uppercase tracking-wider ${t.subtext} flex items-center gap-1.5`}>
                            <LeadSquare status={status[i]} /> {name}
                        </div>
                        <div className={`text-2xl font-bold mt-1 ${valueTone(status[i])}`}>{fmt(totals.partyTotals[i])}</div>
                        <div className={`text-xs ${t.subtext} mt-0.5`}>Jumlah undi</div>
                    </div>
                ))}
                <div className={`rounded-xl border ${t.border} bg-slate-50 p-4`}>
                    <div className={`text-xs font-semibold uppercase tracking-wider ${t.subtext}`}>Jumlah Keluar Mengundi</div>
                    <div className={`text-2xl font-bold mt-1 ${t.text}`}>{fmt(totals.keluar)}</div>
                    <div className={`text-xs ${t.subtext} mt-0.5`}>{partyNames.join(' + ') || 'Semua parti'}</div>
                </div>
                <div className="rounded-xl border border-sky-300 bg-sky-50 p-4">
                    <div className="text-xs font-semibold uppercase tracking-wider text-sky-700">Peratusan Keluar Mengundi</div>
                    <div className="text-2xl font-bold mt-1 text-sky-800">{pct(totals.keluar, totals.berdaftar)}</div>
                    <div className="text-xs text-sky-700/80 mt-0.5">{fmt(totals.keluar)} / {fmt(totals.berdaftar)} berdaftar</div>
                </div>
            </div>
        </div>
    );
}
