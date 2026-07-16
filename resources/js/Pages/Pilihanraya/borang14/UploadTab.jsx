import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import {
    ArrowRight, CalendarDays, CheckCircle2, FileSpreadsheet, ListFilter,
    Loader2, MapPinPlus, RotateCcw, ShieldAlert, Upload, X,
} from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';

// PRU/PRN/PRK — mesti dipilih, mempengaruhi bagaimana kawasan diselaraskan.
const JENIS_PR_OPTIONS = [
    { value: 'pru', label: 'PRU — Pilihanraya Umum' },
    { value: 'prn', label: 'PRN — Pilihanraya Negeri' },
    { value: 'prk', label: 'PRK — Pilihanraya Kecil' },
];

// 1959 = pilihanraya umum pertama Malaysia; +1 tahun hadapan untuk PR akan
// datang. Mesti kekal <select> — taipan tahun boleh cipta rekod pertindihan/salah.
const TAHUN_OPTIONS = (() => {
    const max = new Date().getFullYear() + 1;
    const list = [];
    for (let y = max; y >= 1959; y--) list.push(y);
    return list;
})();

const RULE_LABEL = {
    balance: 'Jumlah tidak seimbang',
    calon_count: 'Bilangan calon tidak sepadan',
    jumlah_undian: 'Jumlah undian tidak sepadan',
};

function ruleDetail(item) {
    if (item.rule === 'calon_count') return `dijangka ${item.expected}, dapat ${item.actual}`;
    return `jangka ${item.jangka}, dapat ${item.dapat}`;
}

function rowLabel(item) {
    if (item.pusat === 'jumlah' || item.index === 'jumlah') return 'Baris Jumlah';
    const parts = [item.pusat || '(tiada pusat)'];
    if (item.saluran) parts.push(`Saluran ${item.saluran}`);
    return parts.join(' · ');
}

const ACCEPT = '.xlsx,.xls,.csv,.txt,.pdf,.jpg,.jpeg,.png,.webp';
const MAX_BYTES = 20 * 1024 * 1024;

export default function UploadTab({ onUploaded }) {
    const { t } = usePilihanrayaTheme();
    const inputRef = useRef(null);
    const [jenisPr, setJenisPr] = useState('');
    const [tahun, setTahun] = useState('');
    const [file, setFile] = useState(null);
    const [drag, setDrag] = useState(false);
    const [busy, setBusy] = useState(false);
    const [elapsed, setElapsed] = useState(0);
    const [error, setError] = useState(null);
    const [result, setResult] = useState(null); // { formId, created, unbalanced, needsReview }

    // Honest elapsed-time counter while the AI reads the sheet — no fake
    // progress bar, just proof the request is still alive.
    useEffect(() => {
        if (!busy) return undefined;
        setElapsed(0);
        const id = setInterval(() => setElapsed((s) => s + 1), 1000);
        return () => clearInterval(id);
    }, [busy]);

    const ready = jenisPr && tahun && file;

    const pickFile = (f) => {
        if (!f) { setFile(null); return; }
        if (f.size > MAX_BYTES) {
            setError('Fail melebihi had 20MB.');
            setFile(null);
            return;
        }
        setError(null);
        setFile(f);
    };

    const reset = () => {
        setResult(null);
        setError(null);
        setFile(null);
        setJenisPr('');
        setTahun('');
    };

    const submit = async () => {
        setError(null);
        if (!ready) {
            setError('Pilih Jenis PR, Tahun dan muat naik scoresheet dahulu.');
            return;
        }
        setBusy(true);
        setResult(null);
        const form = new FormData();
        form.append('fail', file);
        form.append('jenis_pr', jenisPr);
        form.append('tahun', tahun);
        try {
            const { data } = await axios.post(route('pilihanraya.borang-14.upload'), form, {
                headers: { 'Content-Type': 'multipart/form-data' },
                // Server allows up to ~200s for a multi-page PDF read by Claude;
                // leave generous margin above that for network/queueing.
                timeout: 240000,
            });
            setResult({
                formId: data.form_id,
                created: data.created || [],
                unbalanced: data.unbalanced || [],
                needsReview: !!data.needs_review,
            });
            setFile(null);
        } catch (e) {
            setError(e.response?.data?.message || 'Muat naik gagal. Cuba semula.');
        } finally {
            setBusy(false);
        }
    };

    const goToKeyin = () => {
        if (!result) return;
        onUploaded({ formId: result.formId, jenisPr, tahun });
    };

    return (
        <div className={`${t.card} space-y-4`}>
            <div>
                <div className={`text-sm font-bold ${t.text}`}>Upload Scoresheet SPR (Borang 760)</div>
                <div className={`text-xs ${t.subtext} mt-1`}>
                    AI membaca scoresheet dan mengisi draf Keyin secara automatik — fail tidak disimpan ke pelayan.
                    Negeri, Parlimen dan DUN dikesan terus daripada kandungan fail.
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label className={t.label}><span className="inline-flex items-center gap-1"><ListFilter className="h-3.5 w-3.5" /> Jenis PR</span></label>
                    <select value={jenisPr} className={t.input} disabled={busy}
                        onChange={(e) => setJenisPr(e.target.value)}>
                        <option value="">Pilih Jenis PR</option>
                        {JENIS_PR_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                </div>
                <div>
                    <label className={t.label}><span className="inline-flex items-center gap-1"><CalendarDays className="h-3.5 w-3.5" /> Tahun</span></label>
                    <select value={tahun} className={t.input} disabled={busy}
                        onChange={(e) => setTahun(e.target.value)}>
                        <option value="">Pilih Tahun</option>
                        {TAHUN_OPTIONS.map((y) => <option key={y} value={y}>{y}</option>)}
                    </select>
                </div>
            </div>

            <div
                onDragOver={(e) => { e.preventDefault(); if (!busy) setDrag(true); }}
                onDragLeave={() => setDrag(false)}
                onDrop={(e) => { e.preventDefault(); setDrag(false); if (!busy) pickFile(e.dataTransfer.files?.[0] || null); }}
                onClick={() => !busy && inputRef.current?.click()}
                className={`rounded-lg border-2 border-dashed px-4 py-6 text-center transition ${busy ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'} ${
                    drag ? 'border-emerald-500 bg-emerald-500/5' : 'border-slate-200 hover:border-emerald-400'
                }`}
            >
                <input ref={inputRef} type="file" accept={ACCEPT} className="hidden" disabled={busy}
                    onChange={(e) => pickFile(e.target.files?.[0] || null)} />
                {file ? (
                    <div className="flex items-center justify-center gap-2 text-sm text-emerald-600">
                        <FileSpreadsheet className="h-4 w-4" /> {file.name}
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-1 text-slate-500">
                        <Upload className="h-6 w-6" />
                        <span className="text-sm">Klik atau seret scoresheet (XLSX / XLS / CSV / TXT / PDF / imej, maks 20MB)</span>
                        <span className="text-xs text-slate-400">Fail PDF &amp; imej dibaca terus oleh Claude AI</span>
                    </div>
                )}
            </div>

            {error && (
                <div className="flex items-center gap-1.5 text-sm bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3">
                    <X className="h-4 w-4 shrink-0" /> {error}
                </div>
            )}

            <div className="flex items-center gap-3">
                <button type="button" onClick={submit} disabled={busy || !ready} className={t.buttonPrimary}>
                    {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />} Muat Naik &amp; Baca
                </button>
                {busy && (
                    <span className={`text-sm ${t.subtext}`}>
                        AI sedang membaca scoresheet… {elapsed}s (biasanya 1–3 minit untuk PDF berbilang muka surat — jangan tutup tab ini)
                    </span>
                )}
            </div>

            {result && (
                <div className="space-y-3 border-t border-dashed border-slate-200 pt-4">
                    <div className="flex items-center gap-2 text-sm font-semibold text-emerald-700">
                        <CheckCircle2 className="h-4 w-4" /> Scoresheet berjaya dibaca dan draf Keyin telah diisi.
                    </div>

                    {result.created.length > 0 && (
                        <div className={`${t.banner} space-y-1`}>
                            <div className="flex items-center gap-1.5 font-semibold">
                                <MapPinPlus className="h-4 w-4" /> Kawasan baharu dicipta dalam sistem
                            </div>
                            <ul className="list-disc pl-5">
                                {result.created.map((c, i) => (
                                    <li key={i}>
                                        {c.jenis === 'parlimen' ? 'Parlimen' : 'DUN'}: <strong>{c.nama}</strong>
                                    </li>
                                ))}
                            </ul>
                            <div className="text-xs">Kawasan ini belum wujud dalam senarai — sila sahkan namanya betul dan betulkan di tab Keyin jika perlu.</div>
                        </div>
                    )}

                    {result.needsReview && (
                        <div className="flex items-start gap-2 text-sm bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3">
                            <ShieldAlert className="h-4 w-4 shrink-0 mt-0.5" />
                            <span>
                                Draf ini ditanda <strong>perlu semakan</strong> — kemungkinan nama parti dikesan melalui logo
                                (bukan teks) atau saluran tidak lengkap. Sahkan angka di tab Keyin sebelum diterbitkan.
                            </span>
                        </div>
                    )}

                    {result.unbalanced.length > 0 && (
                        <div className="flex items-start gap-2 text-sm bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3">
                            <ShieldAlert className="h-4 w-4 shrink-0 mt-0.5" />
                            <div className="space-y-1">
                                <div className="font-semibold">Semakan aritmetik gagal ({result.unbalanced.length})</div>
                                <ul className="space-y-0.5">
                                    {result.unbalanced.map((item, i) => (
                                        <li key={i}>
                                            {rowLabel(item)} — {RULE_LABEL[item.rule] || item.rule} ({ruleDetail(item)})
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    )}

                    <div className="flex items-center gap-3 pt-1">
                        <button type="button" onClick={goToKeyin} className={t.buttonPrimary}>
                            Ke Keyin <ArrowRight className="h-4 w-4" />
                        </button>
                        <button type="button" onClick={reset} className={t.buttonSecondary}>
                            <RotateCcw className="h-4 w-4" /> Upload Lain
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
