import { useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer,
    Tooltip, XAxis, YAxis,
} from 'recharts';
import {
    BarChart3, CheckCircle2, FileSpreadsheet, Loader2, RotateCcw, Trophy, Upload, Users, Vote, X,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import KpiCard from './components/KpiCard';
import { KawasanSelect, DmFilter, FilterBarCard } from './analisa/FilterControls';
import { PARTY, PARTY_LABEL, computeKeputusanTotals, fmt, pct, safeDiv } from './analisa/shared';

function withDerived(rows) {
    return rows.map((r) => ({
        ...r,
        turnout: r.pemilih ? safeDiv(r.keluar, r.pemilih) : null,
        phPct: safeDiv(r.ph, r.keluar),
        bnPct: safeDiv(r.bn, r.keluar),
    }));
}

/* --------------------------- Scoresheet upload --------------------------- */

function UploadCard({ onParsed, onRawGrid, busy, setBusy, filename }) {
    const { t } = usePilihanrayaTheme();
    const inputRef = useRef(null);
    const [error, setError] = useState(null);
    const [drag, setDrag] = useState(false);

    const handleFile = async (file) => {
        if (!file) return;
        setError(null);
        setBusy(true);
        const form = new FormData();
        form.append('fail', file);
        try {
            const res = await axios.post(route('pilihanraya.analisa.upload'), form, {
                headers: { 'Content-Type': 'multipart/form-data' },
                timeout: 60000,
            });
            if (res.data.parsed && res.data.parsed.rows?.length) {
                onParsed(res.data.parsed, res.data.filename);
            } else {
                onRawGrid(res.data.grid, res.data.filename);
            }
        } catch (e) {
            setError(e.response?.data?.message || 'Gagal membaca fail. Pastikan ia fail Excel scoresheet yang sah.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className={t.card}>
            <div className="flex items-center gap-2 mb-1">
                <FileSpreadsheet className="h-5 w-5 text-emerald-500" />
                <h3 className={t.cardTitle + ' mb-0'}>Muat Naik Scoresheet Pilihanraya Lepas</h3>
            </div>
            <p className={`${t.subtext} text-sm mb-4`}>
                Muat naik fail Excel (.xlsx / .xls / .csv). Sistem akan membaca fail secara automatik
                dan membina jadual keputusan seperti dalam fail asal.
            </p>

            <div
                onDragOver={(e) => { e.preventDefault(); setDrag(true); }}
                onDragLeave={() => setDrag(false)}
                onDrop={(e) => { e.preventDefault(); setDrag(false); handleFile(e.dataTransfer.files?.[0]); }}
                onClick={() => inputRef.current?.click()}
                className={`cursor-pointer rounded-xl border-2 border-dashed px-6 py-8 text-center transition ${
                    drag ? 'border-emerald-500 bg-emerald-500/5' : `${t.border} hover:border-emerald-400`
                }`}
            >
                <input
                    ref={inputRef}
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    className="hidden"
                    onChange={(e) => handleFile(e.target.files?.[0])}
                />
                {busy ? (
                    <div className="flex flex-col items-center gap-2">
                        <Loader2 className="h-8 w-8 animate-spin text-emerald-500" />
                        <p className={`${t.subtext} text-sm`}>Membaca fail…</p>
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-2">
                        <Upload className={`h-8 w-8 ${t.subtext}`} />
                        <p className={`${t.text} text-sm font-medium`}>Klik atau seret fail ke sini</p>
                        <p className={`${t.subtext} text-xs`}>Format disokong: XLSX, XLS, CSV — maksimum 20MB</p>
                    </div>
                )}
            </div>

            {filename && !busy && (
                <div className="mt-3 flex items-center gap-2 text-sm text-emerald-500">
                    <CheckCircle2 className="h-4 w-4" />
                    <span>Sedang memaparkan: <strong>{filename}</strong></span>
                </div>
            )}
            {error && (
                <div className="mt-3 flex items-center gap-2 text-sm text-red-500">
                    <X className="h-4 w-4" />
                    <span>{error}</span>
                </div>
            )}
        </div>
    );
}

/* ------------------------------- Raw grid -------------------------------- */

function RawGridTable({ grid }) {
    const { t } = usePilihanrayaTheme();
    if (!grid?.length) return null;
    const [head, ...body] = grid;

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Kandungan Fail (paparan mentah)</h3>
            <p className={`${t.subtext} text-sm mb-4`}>
                Format fail tidak dikenali sebagai scoresheet piawai — kandungan dipaparkan seadanya.
            </p>
            <div className="overflow-x-auto">
                <table className="min-w-full">
                    <thead>
                        <tr>
                            {head.map((c, i) => <th key={i} className={t.tableHead}>{String(c ?? '')}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {body.map((row, i) => (
                            <tr key={i} className={t.tableRow}>
                                {row.map((c, j) => <td key={j} className={t.tableCell}>{String(c ?? '')}</td>)}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/* -------------------------------- Charts --------------------------------- */

function PartyDonut({ totals }) {
    const { t } = usePilihanrayaTheme();
    const data = [
        { name: PARTY_LABEL.PH, value: totals.ph, color: PARTY.PH },
        { name: PARTY_LABEL.BN, value: totals.bn, color: PARTY.BN },
        { name: PARTY_LABEL.PN, value: totals.pn, color: PARTY.PN },
        { name: PARTY_LABEL.PEJUANG, value: totals.pejuang, color: PARTY.PEJUANG },
        { name: PARTY_LABEL.ditolak, value: totals.ditolak, color: PARTY.ditolak },
    ].filter((d) => d.value > 0);
    const sah = data.reduce((s, d) => s + d.value, 0);

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Pecahan Undi Mengikut Parti</h3>
            <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                    <Pie data={data} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={65} outerRadius={110} paddingAngle={2}>
                        {data.map((d) => <Cell key={d.name} fill={d.color} stroke="transparent" />)}
                    </Pie>
                    <Tooltip contentStyle={t.tooltip} formatter={(v) => `${fmt(v)} undi (${pct(safeDiv(v, sah))})`} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                </PieChart>
            </ResponsiveContainer>
        </div>
    );
}

function PhVsBnBars({ rows }) {
    const { t } = usePilihanrayaTheme();
    const data = useMemo(
        () => rows
            .filter((r) => r.keluar > 0)
            .map((r) => ({ dm: r.dm, PH: +(r.phPct * 100).toFixed(1), BN: +(r.bnPct * 100).toFixed(1) }))
            .sort((a, b) => b.PH - a.PH),
        [rows],
    );

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Peratus Undi PH vs BN Mengikut Daerah Mengundi</h3>
            <ResponsiveContainer width="100%" height={Math.max(360, data.length * 30)}>
                <BarChart data={data} layout="vertical" margin={{ left: 20, right: 16 }}>
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke={t.chartGrid} />
                    <XAxis type="number" stroke={t.chartTick} style={{ fontSize: '11px' }} unit="%" domain={[0, 100]} />
                    <YAxis type="category" dataKey="dm" stroke={t.chartTick} style={{ fontSize: '10px' }} width={160} />
                    <Tooltip contentStyle={t.tooltip} formatter={(v) => `${v}%`} />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <Bar dataKey="PH" name="% PH" fill={PARTY.PH} radius={[0, 3, 3, 0]} />
                    <Bar dataKey="BN" name="% BN" fill={PARTY.BN} radius={[0, 3, 3, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

/* ----------------------------- Result table ------------------------------ */

function KeputusanTable({ rows, totals }) {
    const { t } = usePilihanrayaTheme();
    const showKaum = rows.some((r) => r.melayu !== null && r.melayu !== undefined);

    const cellNum = 'px-3 py-2 text-sm text-right tabular-nums';

    return (
        <div className={t.card}>
            <h3 className={t.cardTitle}>Jadual Keputusan Penuh</h3>
            <div className="overflow-x-auto">
                <table className="min-w-full">
                    <thead>
                        <tr>
                            <th className={t.tableHead}>Daerah Mengundi</th>
                            <th className={t.tableHead + ' text-right'}>Pemilih</th>
                            {showKaum && <th className={t.tableHead + ' text-right'}>% Melayu</th>}
                            {showKaum && <th className={t.tableHead + ' text-right'}>% Cina</th>}
                            {showKaum && <th className={t.tableHead + ' text-right'}>% India</th>}
                            <th className={t.tableHead + ' text-right'}>Keluar</th>
                            <th className={t.tableHead + ' text-right'}>% Turnout</th>
                            <th className={t.tableHead + ' text-right'}>PH</th>
                            <th className={t.tableHead + ' text-right'}>PEJUANG</th>
                            <th className={t.tableHead + ' text-right'}>PN</th>
                            <th className={t.tableHead + ' text-right'}>BN</th>
                            <th className={t.tableHead + ' text-right'}>Ditolak</th>
                            <th className={t.tableHead + ' text-right'}>% PH</th>
                            <th className={t.tableHead + ' text-right'}>% BN</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => {
                            const win = r.phPct >= r.bnPct;
                            return (
                                <tr key={r.dm} className={t.tableRow}>
                                    <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{r.dm}</td>
                                    <td className={`${cellNum} ${t.subtext}`}>{fmt(r.pemilih)}</td>
                                    {showKaum && <td className={`${cellNum} ${t.subtext}`}>{pct(r.melayu, 0)}</td>}
                                    {showKaum && <td className={`${cellNum} ${t.subtext}`}>{pct(r.cina, 0)}</td>}
                                    {showKaum && <td className={`${cellNum} ${t.subtext}`}>{pct(r.india, 0)}</td>}
                                    <td className={`${cellNum} ${t.text}`}>{fmt(r.keluar)}</td>
                                    <td className={`${cellNum} ${t.subtext}`}>{r.turnout !== null ? pct(r.turnout, 0) : '—'}</td>
                                    <td className={`${cellNum} font-semibold`} style={{ color: PARTY.PH }}>{fmt(r.ph)}</td>
                                    <td className={`${cellNum} ${t.subtext}`}>{fmt(r.pejuang)}</td>
                                    <td className={`${cellNum} ${t.subtext}`}>{fmt(r.pn)}</td>
                                    <td className={`${cellNum} font-semibold`} style={{ color: PARTY.BN }}>{fmt(r.bn)}</td>
                                    <td className={`${cellNum} ${t.subtext}`}>{fmt(r.ditolak)}</td>
                                    <td className={cellNum}>
                                        <span className={`inline-block px-1.5 py-0.5 rounded ${win ? 'bg-rose-500/15 text-rose-600 font-semibold' : t.subtext}`}>{pct(r.phPct)}</span>
                                    </td>
                                    <td className={cellNum}>
                                        <span className={`inline-block px-1.5 py-0.5 rounded ${!win ? 'bg-blue-500/15 text-blue-600 font-semibold' : t.subtext}`}>{pct(r.bnPct)}</span>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                    <tfoot>
                        <tr className={`border-t-2 ${t.border} font-bold ${t.text}`}>
                            <td className="px-3 py-3">JUMLAH</td>
                            <td className={cellNum}>{fmt(totals.pemilih)}</td>
                            {showKaum && <td />}
                            {showKaum && <td />}
                            {showKaum && <td />}
                            <td className={cellNum}>{fmt(totals.keluar)}</td>
                            <td className={cellNum}>{totals.pemilih ? pct(safeDiv(totals.keluar, totals.pemilih), 0) : '—'}</td>
                            <td className={cellNum} style={{ color: PARTY.PH }}>{fmt(totals.ph)}</td>
                            <td className={cellNum}>{fmt(totals.pejuang)}</td>
                            <td className={cellNum}>{fmt(totals.pn)}</td>
                            <td className={cellNum} style={{ color: PARTY.BN }}>{fmt(totals.bn)}</td>
                            <td className={cellNum}>{fmt(totals.ditolak)}</td>
                            <td className={cellNum}>{pct(safeDiv(totals.ph, totals.keluar))}</td>
                            <td className={cellNum}>{pct(safeDiv(totals.bn, totals.keluar))}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}

function ResetButton({ onClick }) {
    const { t } = usePilihanrayaTheme();
    return (
        <button type="button" onClick={onClick} className={t.buttonSecondary}>
            <RotateCcw className="h-4 w-4" /> Data Asas
        </button>
    );
}

function EmptyFilter() {
    const { t } = usePilihanrayaTheme();
    return (
        <div className={`${t.card} text-center py-16`}>
            <BarChart3 className={`h-10 w-10 mx-auto mb-3 ${t.subtext}`} />
            <p className={`${t.text} font-medium`}>Tiada Daerah Mengundi dipilih</p>
            <p className={`${t.subtext} text-sm mt-1`}>Pilih sekurang-kurangnya satu Daerah Mengundi dalam penapis di atas.</p>
        </div>
    );
}

/* -------------------------------- Page ----------------------------------- */

export default function Analisa({ context, rows: baseRows, totals: baseTotals }) {
    const [rows, setRows] = useState(baseRows);
    const [rawGrid, setRawGrid] = useState(null);
    const [filename, setFilename] = useState(null);
    const [busy, setBusy] = useState(false);
    const [kawasan, setKawasan] = useState(context.kawasanList?.[0]?.id ?? '');
    const [dmSel, setDmSel] = useState(() => baseRows.map((r) => r.dm));

    const dmOptions = useMemo(() => rows.map((r) => r.dm), [rows]);
    const visibleRows = useMemo(() => rows.filter((r) => dmSel.includes(r.dm)), [rows, dmSel]);
    const derived = useMemo(() => withDerived(visibleRows), [visibleRows]);
    const totals = useMemo(() => computeKeputusanTotals(visibleRows), [visibleRows]);
    const isCustom = filename !== null;

    const onParsed = (parsed, name) => {
        setRawGrid(null);
        setRows(parsed.rows);
        setDmSel(parsed.rows.map((r) => r.dm));
        setFilename(name);
    };

    const onRawGrid = (grid, name) => {
        setRawGrid(grid);
        setFilename(name);
    };

    const reset = () => {
        setRows(baseRows);
        setDmSel(baseRows.map((r) => r.dm));
        setRawGrid(null);
        setFilename(null);
    };

    const turnout = safeDiv(totals.keluar, totals.pemilih);
    const majoriti = totals.bn - totals.ph;

    return (
        <AuthenticatedLayout>
            <Head title="Pilihanraya — Analisa Keputusan" />
            <PilihanrayaShell
                title="Analisa Keputusan Pilihanraya"
                subtitle={`${context.dun} · ${context.parlimen}, ${context.negeri} — PRN Johor ke-15 (2022)`}
                actions={isCustom ? <ResetButton onClick={reset} /> : null}
            >
                <FilterBarCard>
                    <KawasanSelect list={context.kawasanList} value={kawasan} onChange={setKawasan} />
                    <DmFilter options={dmOptions} selected={dmSel} onChange={setDmSel} />
                    <div className="text-sm">
                        <span className="block text-xs opacity-60 mb-1">Paparan</span>
                        <span className="font-semibold">{visibleRows.length} / {dmOptions.length} Daerah Mengundi</span>
                    </div>
                </FilterBarCard>

                <div className="mb-6">
                    <UploadCard onParsed={onParsed} onRawGrid={onRawGrid} busy={busy} setBusy={setBusy} filename={filename} />
                </div>

                {rawGrid ? (
                    <RawGridTable grid={rawGrid} />
                ) : visibleRows.length === 0 ? (
                    <EmptyFilter />
                ) : (
                    <>
                        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                            <KpiCard label="Jumlah Pemilih (DPPR)" value={fmt(totals.pemilih)} icon={Users} sub={`Undi keluar ${fmt(totals.keluar)} · ${pct(turnout, 0)} turnout`} />
                            <KpiCard label="Undi PH" value={fmt(totals.ph)} sub={`${pct(safeDiv(totals.ph, totals.keluar))} undi sah`} icon={Vote} iconBg="bg-rose-500/15" iconColor="text-rose-500" />
                            <KpiCard label="Undi BN" value={fmt(totals.bn)} sub={`${pct(safeDiv(totals.bn, totals.keluar))} undi sah`} icon={Vote} iconBg="bg-blue-500/15" iconColor="text-blue-500" />
                            <KpiCard
                                label={majoriti >= 0 ? 'Majoriti BN' : 'Majoriti PH'}
                                value={fmt(Math.abs(majoriti))}
                                sub={`Undi PN (medan rebutan): ${fmt(totals.undi_pn ?? totals.pn)}`}
                                icon={Trophy}
                                iconBg={majoriti >= 0 ? 'bg-blue-500/15' : 'bg-rose-500/15'}
                                iconColor={majoriti >= 0 ? 'text-blue-500' : 'text-rose-500'}
                            />
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <PartyDonut totals={totals} />
                            <PhVsBnBars rows={derived} />
                        </div>

                        <KeputusanTable rows={derived} totals={totals} />
                    </>
                )}
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
