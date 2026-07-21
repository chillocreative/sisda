import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import {
    ArrowRight, CalendarDays, CheckCircle2, FileSpreadsheet, ListFilter,
    Loader2, MapPinPlus, RotateCcw, ShieldAlert, Upload, X,
} from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import SejarahUpload from './SejarahUpload';

function jenisKawasanLabel(jenis) {
    return jenis === 'parlimen' ? 'Parlimen' : 'DUN';
}

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
    // Peraturan rekonsiliasi: campuran semua baris dibandingkan dengan baris
    // JUMLAH yang DICETAK pada sheet. Inilah semakan yang menangkap bacaan
    // tidak lengkap (98 dicampur lawan 4,471 bercetak).
    jumlah_undi: 'Jumlah undi calon tidak sepadan dengan baris JUMLAH bercetak',
    jumlah_a: 'Jumlah (A) tidak sepadan dengan baris JUMLAH bercetak',
    jumlah_jumlah_undian: 'Jumlah undian tidak sepadan dengan baris JUMLAH bercetak',
    jumlah_ditolak: 'Jumlah undi ditolak (C) tidak sepadan dengan baris JUMLAH bercetak',
    jumlah_tidak_dimasukkan: 'Jumlah (D) tidak sepadan dengan baris JUMLAH bercetak',
    saluran_count: 'Bilangan saluran dibaca tidak sepadan dengan bilangan bercetak',
};

function ruleDetail(item) {
    if (item.rule === 'calon_count') return `dijangka ${item.expected}, dapat ${item.actual}`;
    // Bagi peraturan rekonsiliasi, `jangka` ialah angka BERCETAK pada sheet dan
    // `dapat` ialah campuran baris yang dibaca — dinamakan secara eksplisit
    // supaya pengguna tahu angka mana yang menjadi rujukan.
    if (item.rule?.startsWith('jumlah_') && item.rule !== 'jumlah_undian') {
        const calon = item.calon ? ` calon ${item.calon}` : '';
        return `bercetak ${item.jangka}${calon}, dibaca ${item.dapat}`;
    }
    if (item.rule === 'saluran_count') return `bercetak ${item.jangka}, dibaca ${item.dapat}`;
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
    // 'idle' | 'extracting' | 'confirm' | 'committing'
    const [phase, setPhase] = useState('idle');
    const [elapsed, setElapsed] = useState(0);
    const [error, setError] = useState(null);
    const [pending, setPending] = useState(null); // { token, willCreate, kawasanType, negeri, kawasanNama, needsReview, unbalanced }
    const [result, setResult] = useState(null); // { formId, created, unbalanced, needsReview }
    // Pembilang, BUKAN form_id: memuat naik semula kerusi yang sama menghasilkan
    // form_id yang sama, jadi panel sejarah tidak akan tahu ia perlu dimuat semula.
    const [historyKey, setHistoryKey] = useState(0);

    const busy = phase === 'extracting' || phase === 'committing';
    const locked = busy || phase === 'confirm';

    // Honest elapsed-time counter while the AI reads the sheet — no fake
    // progress bar, just proof the request is still alive.
    useEffect(() => {
        if (phase !== 'extracting') return undefined;
        setElapsed(0);
        const id = setInterval(() => setElapsed((s) => s + 1), 1000);
        return () => clearInterval(id);
    }, [phase]);

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
        setPending(null);
        setError(null);
        setFile(null);
        setJenisPr('');
        setTahun('');
        setPhase('idle');
    };

    // Langkah 2: guna token daripada dry run — TIADA fail dihantar semula, jadi
    // pembacaan AI (mahal & lambat) tidak berulang. Hanya sekarang kawasan
    // benar-benar dicipta dan borang/undi ditulis.
    const commit = async (token) => {
        setPhase('committing');
        setError(null);
        try {
            const { data } = await axios.post(route('pilihanraya.borang-14.upload'), { token });
            setResult({
                formId: data.form_id,
                created: data.created || [],
                unbalanced: data.unbalanced || [],
                needsReview: !!data.needs_review,
            });
            setPending(null);
            setFile(null);
            setPhase('idle');
            setHistoryKey((n) => n + 1);
        } catch (e) {
            // 422 = token sah tidak dijumpai/luput/milik pengguna lain di sisi pelayan
            // — token memang mati, mesti muat naik semula. Sebarang kegagalan LAIN
            // (rangkaian terputus, 500 sekejap) TIDAK memusnahkan token yang masih sah
            // selama 15 minit di cache pelayan — kekalkan `pending` supaya pengguna
            // boleh cuba "Cipta & teruskan" semula tanpa bayar untuk bacaan AI baharu.
            const tokenDead = e.response?.status === 422 || !pending;
            if (tokenDead) {
                setError(e.response?.data?.message || 'Muat naik gagal. Cuba semula.');
                setPending(null);
                setPhase('idle');
            } else {
                setError('Penciptaan gagal buat sementara (rangkaian/pelayan). Token masih sah — klik "Cipta & teruskan" sekali lagi untuk cuba semula, tanpa perlu muat naik semula.');
                setPhase('confirm');
            }
        }
    };

    // Langkah 1: baca scoresheet & padan kawasan TANPA menulis apa-apa. Jika
    // kawasan itu sudah wujud, tiada apa untuk disahkan — terus ke langkah 2.
    const submit = async () => {
        setError(null);
        if (!ready) {
            setError('Pilih Jenis PR, Tahun dan muat naik scoresheet dahulu.');
            return;
        }
        setPhase('extracting');
        setResult(null);
        setPending(null);
        const form = new FormData();
        form.append('dry_run', '1');
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
            const willCreate = data.will_create || [];
            if (willCreate.length === 0) {
                // Kawasan sudah wujud — tiada apa untuk disahkan, terus cipta.
                await commit(data.token);
                return;
            }
            setPending({
                token: data.token,
                willCreate,
                kawasanType: data.kawasan_type,
                negeri: data.negeri,
                kawasanNama: data.kawasan_nama,
                needsReview: !!data.needs_review,
                unbalanced: data.unbalanced || [],
            });
            setPhase('confirm');
        } catch (e) {
            setError(e.response?.data?.message || 'Muat naik gagal. Cuba semula.');
            setPhase('idle');
        }
    };

    // Batal: buang token di sisi klien sahaja — TIADA permintaan dihantar,
    // jadi tiada apa-apa ditulis ke pangkalan data.
    const cancelPending = () => {
        setPending(null);
        setPhase('idle');
    };

    const goToKeyin = () => {
        if (!result) return;
        onUploaded({ formId: result.formId, jenisPr, tahun });
    };

    return (
        <div className="space-y-4">
        <div className={`${t.card} space-y-4`}>
            <div>
                <div className={`text-sm font-bold ${t.text}`}>Upload Scoresheet SPR (Borang 760)</div>
                <div className={`text-xs ${t.subtext} mt-1`}>
                    Borang SPR 760 bertaip dibaca terus (tepat, tanpa AI); sheet imbasan atau gambar dibaca oleh AI.
                    Negeri, Parlimen dan DUN dikesan daripada kandungan fail, dan fail asal disimpan dalam Sejarah Muat Naik di bawah.
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label className={t.label}><span className="inline-flex items-center gap-1"><ListFilter className="h-3.5 w-3.5" /> Jenis PR</span></label>
                    <select value={jenisPr} className={t.input} disabled={locked}
                        onChange={(e) => setJenisPr(e.target.value)}>
                        <option value="">Pilih Jenis PR</option>
                        {JENIS_PR_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                </div>
                <div>
                    <label className={t.label}><span className="inline-flex items-center gap-1"><CalendarDays className="h-3.5 w-3.5" /> Tahun</span></label>
                    <select value={tahun} className={t.input} disabled={locked}
                        onChange={(e) => setTahun(e.target.value)}>
                        <option value="">Pilih Tahun</option>
                        {TAHUN_OPTIONS.map((y) => <option key={y} value={y}>{y}</option>)}
                    </select>
                </div>
            </div>

            <div
                onDragOver={(e) => { e.preventDefault(); if (!locked) setDrag(true); }}
                onDragLeave={() => setDrag(false)}
                onDrop={(e) => { e.preventDefault(); setDrag(false); if (!locked) pickFile(e.dataTransfer.files?.[0] || null); }}
                onClick={() => !locked && inputRef.current?.click()}
                className={`rounded-lg border-2 border-dashed px-4 py-6 text-center transition ${locked ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'} ${
                    drag ? 'border-emerald-500 bg-emerald-500/5' : 'border-slate-200 hover:border-emerald-400'
                }`}
            >
                <input ref={inputRef} type="file" accept={ACCEPT} className="hidden" disabled={locked}
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

            {/* Kawasan yang SUDAH wujud tidak melalui skrin pengesahan langsung (lihat submit():
                willCreate kosong -> terus commit). Kerusi yang mempunyai struktur ditaip tangan
                sememangnya kawasan yang sudah wujud, jadi nota ini mesti kekal di sini — bukan
                di dalam blok pengesahan — supaya ia dibaca sebelum fail dimuat naik. */}
            <div className={`text-xs ${t.subtext}`}>
                Nota: jika borang ini mempunyai struktur yang ditaip tangan, scoresheet akan
                menggantikannya. Keadaan lama disimpan dan boleh dipulihkan melalui Revert.
            </div>

            {phase !== 'confirm' && !(phase === 'committing' && pending) && (
                <div className="flex items-center gap-3">
                    <button type="button" onClick={submit} disabled={locked || !ready} className={t.buttonPrimary}>
                        {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />} Muat Naik &amp; Baca
                    </button>
                    {phase === 'extracting' && (
                        <span className={`text-sm ${t.subtext}`}>
                            AI sedang membaca scoresheet… {elapsed}s (biasanya 1–3 minit untuk PDF berbilang muka surat — jangan tutup tab ini)
                        </span>
                    )}
                    {phase === 'committing' && (
                        <span className={`text-sm ${t.subtext}`}>
                            Mencipta kawasan dan menyimpan draf…
                        </span>
                    )}
                </div>
            )}

            {(phase === 'confirm' || phase === 'committing') && pending && (
                <div className="space-y-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3">
                    <div className="flex items-center gap-1.5 text-sm font-semibold text-amber-800">
                        <MapPinPlus className="h-4 w-4" /> Kawasan ini akan dicipta:
                    </div>
                    <ul className="list-disc pl-5 text-sm text-amber-900">
                        {pending.willCreate.map((c, i) => (
                            <li key={i}>{jenisKawasanLabel(c.jenis)}: <strong>{c.nama}</strong></li>
                        ))}
                    </ul>
                    <div className="text-xs text-amber-800">
                        Dikesan daripada scoresheet: <strong>{pending.negeri}</strong> — <strong>{jenisKawasanLabel(pending.kawasanType)} {pending.kawasanNama}</strong>.
                        Sahkan jenis kawasan (Parlimen/DUN) dan nama ini betul sebelum diteruskan — kawasan baharu akan ditulis ke pangkalan data selepas ini.
                    </div>

                    {pending.needsReview && (
                        <div className="flex items-start gap-2 text-sm bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3">
                            <ShieldAlert className="h-4 w-4 shrink-0 mt-0.5" />
                            <span>
                                Bacaan ini ditanda <strong>perlu semakan</strong> — kemungkinan nama parti dikesan melalui logo
                                (bukan teks) atau saluran tidak lengkap. Semak dengan teliti sebelum tekan &quot;Cipta &amp; teruskan&quot;,
                                atau tekan &quot;Batal&quot; jika bacaan ini kelihatan salah.
                            </span>
                        </div>
                    )}

                    {pending.unbalanced.length > 0 && (
                        <div className="flex items-start gap-2 text-sm bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3">
                            <ShieldAlert className="h-4 w-4 shrink-0 mt-0.5" />
                            <div className="space-y-1">
                                <div className="font-semibold">Semakan aritmetik gagal ({pending.unbalanced.length})</div>
                                <ul className="space-y-0.5">
                                    {pending.unbalanced.map((item, i) => (
                                        <li key={i}>
                                            {rowLabel(item)} — {RULE_LABEL[item.rule] || item.rule} ({ruleDetail(item)})
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    )}

                    <div className="flex items-center gap-3 pt-1">
                        <button type="button" onClick={() => commit(pending.token)} disabled={busy} className={t.buttonPrimary}>
                            {phase === 'committing' ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />} Cipta &amp; teruskan
                        </button>
                        <button type="button" onClick={cancelPending} disabled={busy} className="inline-flex items-center gap-2 px-4 py-2 border border-rose-300 text-rose-700 hover:bg-rose-50 rounded-lg text-sm font-medium disabled:opacity-50">
                            <X className="h-4 w-4" /> Batal
                        </button>
                    </div>
                </div>
            )}

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

        <SejarahUpload refreshKey={historyKey} />
        </div>
    );
}
