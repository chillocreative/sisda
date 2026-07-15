import { useRef, useState } from 'react';
import axios from 'axios';
import {
    CheckCircle2, FileSpreadsheet, FolderOpen, Loader2, Plus, Sparkles, Trash2, Upload, X,
} from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import ComparisonResult from './ComparisonResult';

const fmt = (n) => (n === null || n === undefined || Number.isNaN(Number(n)) ? '—'
    : Number(n).toLocaleString('en-MY'));

/* ----------------------- Add-scenario form (one slot) ---------------------- */

function AddScenarioForm({ comparisonId, position, onAdded }) {
    const { t } = usePilihanrayaTheme();
    const inputRef = useRef(null);
    const [label, setLabel] = useState('');
    const [date, setDate] = useState('');
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [drag, setDrag] = useState(false);

    const submit = async () => {
        setError(null);
        if (!label.trim() || !date || !file) {
            setError('Isi label, tarikh pilihanraya dan muat naik scoresheet.');
            return;
        }
        setBusy(true);
        const form = new FormData();
        form.append('label', label.trim());
        form.append('election_date', date);
        form.append('fail', file);
        try {
            const res = await axios.post(route('pilihanraya.analisa.comparisons.scenarios.store', comparisonId), form, {
                headers: { 'Content-Type': 'multipart/form-data' },
                timeout: 60000,
            });
            onAdded(res.data.comparison);
            setLabel(''); setDate(''); setFile(null);
        } catch (e) {
            setError(e.response?.data?.message || 'Gagal menambah senario.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="rounded-xl border-2 border-dashed border-slate-300 p-4">
            <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
                <Plus className="h-4 w-4 text-emerald-500" /> Senario {position}
            </div>
            <div className="space-y-3">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label className={t.label}>Label</label>
                        <input value={label} onChange={(e) => setLabel(e.target.value)} placeholder="cth. PRN Johor 2022" className={t.input} />
                    </div>
                    <div>
                        <label className={t.label}>Tarikh Pilihanraya</label>
                        <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className={t.input} />
                    </div>
                </div>

                <div
                    onDragOver={(e) => { e.preventDefault(); setDrag(true); }}
                    onDragLeave={() => setDrag(false)}
                    onDrop={(e) => { e.preventDefault(); setDrag(false); setFile(e.dataTransfer.files?.[0] || null); }}
                    onClick={() => inputRef.current?.click()}
                    className={`cursor-pointer rounded-lg border-2 border-dashed px-4 py-5 text-center transition ${
                        drag ? 'border-emerald-500 bg-emerald-500/5' : 'border-slate-200 hover:border-emerald-400'
                    }`}
                >
                    <input ref={inputRef} type="file" accept=".xlsx,.xls,.csv,.pdf" className="hidden"
                        onChange={(e) => setFile(e.target.files?.[0] || null)} />
                    {file ? (
                        <div className="flex items-center justify-center gap-2 text-sm text-emerald-600">
                            <FileSpreadsheet className="h-4 w-4" /> {file.name}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center gap-1 text-slate-500">
                            <Upload className="h-6 w-6" />
                            <span className="text-sm">Klik atau seret scoresheet (XLSX / XLS / CSV / PDF)</span>
                            <span className="text-xs text-slate-400">Fail PDF akan dibaca secara automatik oleh AI</span>
                        </div>
                    )}
                </div>

                {error && <div className="flex items-center gap-1.5 text-sm text-red-500"><X className="h-4 w-4" /> {error}</div>}

                <button type="button" onClick={submit} disabled={busy} className={t.buttonPrimary}>
                    {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                    Tambah Senario
                </button>
            </div>
        </div>
    );
}

/* ------------------------------ Scenario chip ------------------------------ */

function ScenarioChip({ scenario, comparisonId, onRemoved }) {
    const { t } = usePilihanrayaTheme();
    const [busy, setBusy] = useState(false);
    const totals = scenario.parsed_totals || {};
    const undi = totals.undi || {};
    const topParties = Object.entries(undi)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 4)
        .map(([name, votes]) => `${name} ${fmt(votes)}`);

    const remove = async () => {
        setBusy(true);
        try {
            const res = await axios.delete(route('pilihanraya.analisa.comparisons.scenarios.destroy', [comparisonId, scenario.id]));
            onRemoved(res.data.comparison);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <div className="font-semibold text-slate-900">{scenario.label}</div>
                    <div className="text-xs text-slate-500">{scenario.election_date} · {scenario.source_filename}</div>
                </div>
                <button type="button" onClick={remove} disabled={busy} className="text-slate-400 hover:text-red-500">
                    {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                </button>
            </div>
            <div className="mt-3 flex items-center gap-2 text-xs text-emerald-600">
                <CheckCircle2 className="h-3.5 w-3.5" />
                {scenario.row_count} kawasan · Pemilih {fmt(totals.pemilih)} · Keluar {fmt(totals.keluar)}
            </div>
            <div className="mt-1 text-xs text-slate-500">
                {topParties.length ? topParties.join(' · ') : 'Tiada data undi'}
            </div>
        </div>
    );
}

/* ------------------------------- Main card -------------------------------- */

export default function ComparisonBuilder({ savedComparisons = [], currentScope }) {
    const { t } = usePilihanrayaTheme();
    // Only show saved comparisons for the currently-selected kawasan.
    const scoped = savedComparisons.filter((c) =>
        c.level === currentScope.level
        && String(c.bandar_id) === String(currentScope.bandar_id)
        && String(c.kadun_id ?? '') === String(currentScope.kadun_id ?? ''));
    const [saved, setSaved] = useState(scoped);
    const [comparison, setComparison] = useState(null);
    const [title, setTitle] = useState('');
    const [openId, setOpenId] = useState('');
    const [busy, setBusy] = useState(false);       // create / open
    const [analyzing, setAnalyzing] = useState(false);
    const [error, setError] = useState(null);

    const create = async () => {
        if (!title.trim()) { setError('Masukkan tajuk perbandingan.'); return; }
        setError(null); setBusy(true);
        try {
            const res = await axios.post(route('pilihanraya.analisa.comparisons.store'), {
                title: title.trim(),
                level: currentScope.level,
                bandar_id: currentScope.bandar_id,
                kadun_id: currentScope.kadun_id,
            });
            setComparison(res.data.comparison);
            setTitle('');
        } catch (e) {
            setError(e.response?.data?.message || 'Gagal mencipta perbandingan.');
        } finally {
            setBusy(false);
        }
    };

    const open = async (id) => {
        if (!id) return;
        setError(null); setBusy(true);
        try {
            const res = await axios.get(route('pilihanraya.analisa.comparisons.show', id));
            setComparison(res.data.comparison);
        } catch (e) {
            setError('Gagal membuka perbandingan.');
        } finally {
            setBusy(false); setOpenId('');
        }
    };

    const analyze = async () => {
        setError(null); setAnalyzing(true);
        try {
            const res = await axios.post(route('pilihanraya.analisa.comparisons.analyze', comparison.id), {}, { timeout: 300000 });
            setComparison(res.data.comparison);
        } catch (e) {
            setError(e.response?.data?.message || 'Analisis gagal. Cuba semula.');
        } finally {
            setAnalyzing(false);
        }
    };

    const remove = async () => {
        if (!comparison) return;
        setBusy(true);
        try {
            const res = await axios.delete(route('pilihanraya.analisa.comparisons.destroy', comparison.id));
            setSaved(res.data.comparisons);
            setComparison(null);
        } finally {
            setBusy(false);
        }
    };

    const scenarios = comparison?.scenarios || [];
    const canAnalyze = scenarios.length >= 1;
    const analyzed = comparison?.ai_result && comparison.status === 'analyzed';

    return (
        <div className={t.card}>
            <div className="mb-4 flex items-center gap-2">
                <Sparkles className="h-5 w-5 text-emerald-500" />
                <h3 className={t.cardTitle + ' mb-0'}>Perbandingan Senario AI</h3>
            </div>

            {!comparison ? (
                <div className="space-y-4">
                    <p className={`${t.subtext} text-sm`}>
                        Kawasan: <strong>{currentScope.label}</strong>. Bina perbandingan 1–3 pilihanraya (setiap senario =
                        satu scoresheet + tarikh). AI akan membanding keputusan, merujuk keadaan politik pada masa itu dan
                        sekarang (carian web), serta menganalisa pengundi baru/lama, peratus pengundi muda dan pecahan saluran.
                    </p>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className={t.label}>Perbandingan Baru — Tajuk</label>
                            <div className="flex gap-2">
                                <input value={title} onChange={(e) => setTitle(e.target.value)}
                                    placeholder={`Perbandingan ${currentScope.label || ''}`} className={t.input} />
                                <button type="button" onClick={create} disabled={busy} className={t.buttonPrimary}>
                                    {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />} Cipta
                                </button>
                            </div>
                        </div>
                        {saved.length > 0 && (
                            <div>
                                <label className={t.label}>Buka Perbandingan Tersimpan</label>
                                <div className="flex gap-2">
                                    <select value={openId} onChange={(e) => setOpenId(e.target.value)} className={t.input}>
                                        <option value="">— Pilih —</option>
                                        {saved.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.title} ({c.scenario_count} senario{c.ai_status ? `, ${c.ai_status}` : ''})
                                            </option>
                                        ))}
                                    </select>
                                    <button type="button" onClick={() => open(openId)} disabled={busy || !openId} className={t.buttonSecondary}>
                                        <FolderOpen className="h-4 w-4" /> Buka
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                    {error && <div className="flex items-center gap-1.5 text-sm text-red-500"><X className="h-4 w-4" /> {error}</div>}
                </div>
            ) : (
                <div className="space-y-5">
                    {/* Active comparison header */}
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
                        <div>
                            <div className="font-semibold text-slate-900">{comparison.title}</div>
                            <div className="text-xs text-slate-500">{comparison.dun} · {comparison.parlimen}</div>
                        </div>
                        <div className="flex items-center gap-2">
                            <button type="button" onClick={remove} disabled={busy} className="inline-flex items-center gap-1 text-sm text-red-500 hover:text-red-600">
                                <Trash2 className="h-4 w-4" /> Padam
                            </button>
                            <button type="button" onClick={() => setComparison(null)} className={t.buttonSecondary}>
                                <X className="h-4 w-4" /> Tutup
                            </button>
                        </div>
                    </div>

                    {/* Scenario slots */}
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        {scenarios.map((s) => (
                            <ScenarioChip key={s.id} scenario={s} comparisonId={comparison.id} onRemoved={setComparison} />
                        ))}
                        {scenarios.length < 3 && (
                            <AddScenarioForm comparisonId={comparison.id} position={scenarios.length + 1} onAdded={setComparison} />
                        )}
                    </div>

                    {/* Analyze */}
                    <div className="flex flex-wrap items-center gap-3">
                        <button type="button" onClick={analyze} disabled={!canAnalyze || analyzing} className={t.buttonPrimary}>
                            {analyzing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
                            {analyzed ? 'Jana Semula Analisis' : 'Jana Analisis AI'}
                        </button>
                        {analyzing && (
                            <span className="text-sm text-slate-500">AI sedang membuat carian web &amp; analisis… (1–3 minit)</span>
                        )}
                        {!canAnalyze && <span className="text-sm text-slate-400">Tambah sekurang-kurangnya satu senario.</span>}
                    </div>
                    {error && <div className="flex items-center gap-1.5 text-sm text-red-500"><X className="h-4 w-4" /> {error}</div>}

                    {/* Result */}
                    {analyzed && (
                        <div className="border-t border-slate-200 pt-5">
                            <ComparisonResult comparison={comparison} />
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
