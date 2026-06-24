import { useState, useRef } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Plus, RefreshCw, Pencil, Trash2, X, Users, MapPin, Loader2, Sparkles, Building2, Landmark } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import { CHART_COLORS } from './theme';

const JENIS_COLORS = {
    JPRC: CHART_COLORS.blue,
    JPRD: CHART_COLORS.violet,
};
const JENIS_LABEL = { JPRC: 'JPRC', JPRD: 'JPRD' };

function MemberModal({ member, jenisOptions, onClose }) {
    const isEdit = !!member;
    const { data, setData, post, put, processing, errors } = useForm({
        no_ic: member?.no_ic || '',
        nama: member?.nama || '',
        jenis: member?.jenis || jenisOptions[0],
        jawatan: member?.jawatan || '',
        cabang: member?.cabang || '',
        dun: member?.dun || '',
        no_tel: member?.no_tel || '',
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (isEdit) put(route('pilihanraya.jawatankuasa.update', member.id), opts);
        else post(route('pilihanraya.jawatankuasa.store'), opts);
    };

    const field = 'w-full px-3 py-2 border border-slate-300 rounded-lg text-sm';
    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-base font-semibold text-slate-900">{isEdit ? 'Kemaskini Ahli Jawatankuasa' : 'Tambah Ahli Jawatankuasa'}</h3>
                    <button onClick={onClose}><X className="h-5 w-5 text-slate-400" /></button>
                </div>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">No. IC</label>
                            <input value={data.no_ic} onChange={(e) => setData('no_ic', e.target.value)} maxLength={12} className={field} placeholder="(pilihan)" />
                            {errors.no_ic && <p className="text-xs text-rose-600 mt-1">{errors.no_ic}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Jenis *</label>
                            <select value={data.jenis} onChange={(e) => setData('jenis', e.target.value)} className={field}>
                                {jenisOptions.map((j) => <option key={j} value={j}>{JENIS_LABEL[j]}</option>)}
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Nama *</label>
                        <input value={data.nama} onChange={(e) => setData('nama', e.target.value)} className={field} required />
                        {errors.nama && <p className="text-xs text-rose-600 mt-1">{errors.nama}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Jawatan</label>
                            <input value={data.jawatan} onChange={(e) => setData('jawatan', e.target.value)} className={field} />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Cabang</label>
                            <input value={data.cabang} onChange={(e) => setData('cabang', e.target.value)} className={field} />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">DUN</label>
                            <input value={data.dun} onChange={(e) => setData('dun', e.target.value)} className={field} />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">No. Telefon</label>
                            <input value={data.no_tel} onChange={(e) => setData('no_tel', e.target.value)} className={field} />
                        </div>
                    </div>
                    <p className="text-xs text-slate-500">No. IC pilihan — jika diisi, status "dicula" & padanan kawasan dikira automatik.</p>
                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50">Batal</button>
                        <button type="submit" disabled={processing} className="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    );
}

const FIELD_LABELS = { no_ic: 'IC', nama: 'Nama', jenis: 'Jenis', jawatan: 'Jawatan', cabang: 'Cabang', dun: 'DUN', no_tel: 'No. Telefon' };

function ImportPreviewModal({ result, committing, onConfirm, onClose }) {
    const rows = result.rows || [];
    const sample = rows.slice(0, 10);
    const cols = result.mapping?.columns || {};
    const detectedJenis = result.mapping?.jenis_constant;
    const detectedDun = result.mapping?.dun_constant;

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-xl shadow-xl max-w-3xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-base font-semibold text-slate-900">Pratonton Muat Naik</h3>
                    <button onClick={onClose}><X className="h-5 w-5 text-slate-400" /></button>
                </div>

                <div className="flex flex-wrap items-center gap-2 mb-4 text-xs">
                    <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full font-medium ${result.ai_used ? 'bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-600'}`}>
                        <Sparkles className="h-3.5 w-3.5" /> {result.ai_used ? 'Dibaca oleh AI' : 'Kaedah heuristik (AI tidak aktif)'}
                    </span>
                    {detectedJenis && <span className="inline-flex px-2 py-1 rounded-full bg-blue-100 text-blue-700 font-medium">Jenis: {JENIS_LABEL[detectedJenis] || detectedJenis}</span>}
                    {detectedDun && <span className="inline-flex px-2 py-1 rounded-full bg-indigo-100 text-indigo-700 font-medium">DUN: {detectedDun}</span>}
                    <span className="inline-flex px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 font-medium">{rows.length} baris sah</span>
                    {result.skipped > 0 && <span className="inline-flex px-2 py-1 rounded-full bg-amber-100 text-amber-700 font-medium">{result.skipped} dilangkau (tiada nama/jenis)</span>}
                </div>

                <div className="mb-4 text-xs text-slate-600">
                    <span className="font-medium">Lajur dikesan: </span>
                    {Object.entries(FIELD_LABELS).map(([f, label]) => (
                        <span key={f} className="inline-block mr-3">{label}: {cols[f] !== null && cols[f] !== undefined ? `lajur ${cols[f] + 1}` : '—'}</span>
                    ))}
                </div>

                {rows.length === 0 ? (
                    <p className="text-sm text-slate-500 py-8 text-center">Tiada baris sah dikesan. Semak fail atau pilih Jenis Lalai dahulu.</p>
                ) : (
                    <div className="overflow-x-auto border border-slate-200 rounded-lg">
                        <table className="w-full text-xs">
                            <thead className="bg-slate-50">
                                <tr>{['Nama', 'IC', 'Jenis', 'Jawatan', 'DUN', 'No. Tel'].map((h) => <th key={h} className="text-left px-3 py-2 font-medium text-slate-500">{h}</th>)}</tr>
                            </thead>
                            <tbody>
                                {sample.map((r, i) => (
                                    <tr key={i} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5 text-slate-800">{r.nama}</td>
                                        <td className="px-3 py-1.5 text-slate-600">{r.no_ic || '-'}</td>
                                        <td className="px-3 py-1.5 text-slate-600">{JENIS_LABEL[r.jenis] || r.jenis}</td>
                                        <td className="px-3 py-1.5 text-slate-600">{r.jawatan || '-'}</td>
                                        <td className="px-3 py-1.5 text-slate-600">{r.dun || '-'}</td>
                                        <td className="px-3 py-1.5 text-slate-600">{r.no_tel || '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {rows.length > sample.length && <p className="text-xs text-slate-400 px-3 py-2">… dan {rows.length - sample.length} baris lagi</p>}
                    </div>
                )}

                <div className="flex justify-end gap-3 pt-4">
                    <button type="button" onClick={onClose} className="px-4 py-2 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50">Batal</button>
                    <button type="button" disabled={committing || rows.length === 0} onClick={onConfirm} className="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50 inline-flex items-center gap-2">
                        {committing && <Loader2 className="h-4 w-4 animate-spin" />} Simpan {rows.length} Ahli
                    </button>
                </div>
            </div>
        </div>
    );
}

function UploadForm({ jenisOptions }) {
    const { t } = usePilihanrayaTheme();
    const [file, setFile] = useState(null);
    const [jenisDefault, setJenisDefault] = useState('');
    const [analyzing, setAnalyzing] = useState(false);
    const [committing, setCommitting] = useState(false);
    const [preview, setPreview] = useState(null);
    const fileInputRef = useRef(null);

    const analyze = async (e) => {
        e.preventDefault();
        if (!file) return;
        setAnalyzing(true);
        try {
            const fd = new FormData();
            fd.append('fail', file);
            if (jenisDefault) fd.append('jenis_default', jenisDefault);
            const { data } = await window.axios.post(route('pilihanraya.jawatankuasa.upload.analyze'), fd);
            setPreview(data);
        } catch (err) {
            alert(err.response?.data?.message || 'Gagal membaca fail.');
        } finally {
            setAnalyzing(false);
        }
    };

    const commit = async () => {
        setCommitting(true);
        try {
            await window.axios.post(route('pilihanraya.jawatankuasa.upload.commit'), { rows: preview.rows });
            setPreview(null);
            setFile(null);
            if (fileInputRef.current) fileInputRef.current.value = '';
            router.reload({ preserveScroll: true });
        } catch (err) {
            alert(err.response?.data?.message || 'Gagal menyimpan.');
        } finally {
            setCommitting(false);
        }
    };

    return (
        <>
            <form onSubmit={analyze} className="flex flex-wrap items-end gap-3">
                <div>
                    <label className={t.label}>Fail Excel/CSV</label>
                    <input ref={fileInputRef} type="file" accept=".xlsx,.xls,.csv" onChange={(e) => setFile(e.target.files[0] || null)} className={t.input} required />
                </div>
                <div>
                    <label className={t.label}>Jenis Lalai (jika tiada lajur)</label>
                    <select value={jenisDefault} onChange={(e) => setJenisDefault(e.target.value)} className={t.input}>
                        <option value="">— Pilih —</option>
                        {jenisOptions.map((j) => <option key={j} value={j}>{JENIS_LABEL[j]}</option>)}
                    </select>
                </div>
                <button type="submit" disabled={analyzing || !file} className={t.buttonPrimary}>
                    {analyzing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />} {analyzing ? 'Membaca…' : 'Baca dengan AI'}
                </button>
            </form>
            {preview && <ImportPreviewModal result={preview} committing={committing} onConfirm={commit} onClose={() => setPreview(null)} />}
        </>
    );
}

function Content({ members, filters, jenisOptions, summary, byDun, dunOptions = [] }) {
    const { t } = usePilihanrayaTheme();
    const [modal, setModal] = useState(null);
    const [search, setSearch] = useState(filters.search || '');
    const [selected, setSelected] = useState(new Set());
    const [bulkDeleting, setBulkDeleting] = useState(false);

    const applyFilters = (extra = {}) => {
        router.get(route('pilihanraya.jawatankuasa.index'), { search, jenis: filters.jenis, dun: filters.dun, ...extra }, { preserveState: true, replace: true });
    };
    const remove = (m) => { if (confirm('Padam ahli jawatankuasa ini?')) router.delete(route('pilihanraya.jawatankuasa.destroy', m.id), { preserveScroll: true }); };

    const pageIds = members.data.map((m) => m.id);
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const toggleOne = (id) => setSelected((prev) => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });
    const toggleAll = () => setSelected((prev) => {
        const n = new Set(prev);
        allSelected ? pageIds.forEach((id) => n.delete(id)) : pageIds.forEach((id) => n.add(id));
        return n;
    });
    const bulkDelete = async () => {
        if (!confirm(`Padam ${selected.size} ahli jawatankuasa yang dipilih?`)) return;
        setBulkDeleting(true);
        try {
            await window.axios.post(route('pilihanraya.jawatankuasa.bulk-destroy'), { ids: [...selected] });
            setSelected(new Set());
            router.reload({ preserveScroll: true });
        } catch (err) {
            alert(err.response?.data?.message || 'Gagal memadam.');
        } finally {
            setBulkDeleting(false);
        }
    };

    return (
        <>
            {/* KPI summary — JPRC + JPRD across the DUNs */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div className={t.card}>
                    <div className="flex items-center justify-between"><span className={t.kpiLabel}>Jumlah Ahli Jawatankuasa</span><Users className="h-5 w-5 text-slate-400" /></div>
                    <div className={t.kpiValue}>{summary.total.toLocaleString()}</div>
                </div>
                <div className={t.card}>
                    <div className="flex items-center justify-between"><span className={t.kpiLabel}>JPRC (Peringkat Cabang)</span><Landmark className="h-5 w-5" style={{ color: JENIS_COLORS.JPRC }} /></div>
                    <div className={t.kpiValue} style={{ color: JENIS_COLORS.JPRC }}>{summary.jprc.toLocaleString()}</div>
                </div>
                <div className={t.card}>
                    <div className="flex items-center justify-between"><span className={t.kpiLabel}>JPRD (Mengikut DUN)</span><Building2 className="h-5 w-5" style={{ color: JENIS_COLORS.JPRD }} /></div>
                    <div className={t.kpiValue} style={{ color: JENIS_COLORS.JPRD }}>{summary.jprd.toLocaleString()}</div>
                </div>
                <div className={t.card}>
                    <div className="flex items-center justify-between"><span className={t.kpiLabel}>DUN Diliputi</span><MapPin className="h-5 w-5 text-emerald-500" /></div>
                    <div className={`${t.kpiValue} text-emerald-500`}>{summary.dun_count.toLocaleString()}</div>
                    {summary.dicula > 0 && <p className={`${t.subtext} text-xs mt-1`}>{summary.dicula} dicula / {summary.with_ic} ahli ber-IC</p>}
                </div>
            </div>

            {/* JPRC + JPRD per DUN */}
            <div className={`${t.card} mb-6`}>
                <h3 className={t.cardTitle}>Jawatankuasa JPRC &amp; JPRD Mengikut DUN</h3>
                {byDun.length === 0 ? <p className={`${t.subtext} text-sm py-12 text-center`}>Tiada data. Muat naik fail struktur JPRC/JPRD untuk bermula.</p> : (
                    <>
                        <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 mb-6">
                            {byDun.map((d) => {
                                const isCabang = d.dun === 'Peringkat Cabang';
                                const active = filters.dun === d.dun;
                                const clickable = !isCabang && d.dun !== 'Tidak Diketahui';
                                return (
                                    <button
                                        key={d.dun}
                                        type="button"
                                        onClick={() => clickable && applyFilters({ dun: active ? '' : d.dun })}
                                        className={`${t.cardTight} text-left transition ${clickable ? 'cursor-pointer hover:ring-2 hover:ring-blue-400/40' : 'cursor-default'} ${active ? 'ring-2 ring-blue-500' : ''}`}
                                    >
                                        <div className="flex items-center gap-1.5 mb-1">
                                            {isCabang ? <Landmark className="h-4 w-4 text-slate-400" /> : <MapPin className="h-4 w-4 text-emerald-500" />}
                                            <span className={`text-xs font-semibold ${t.text} truncate`}>{d.dun}</span>
                                        </div>
                                        <div className={`text-2xl font-bold ${t.text}`}>{d.total}</div>
                                        <div className="flex gap-3 mt-1 text-xs">
                                            <span style={{ color: JENIS_COLORS.JPRC }}>JPRC {d.JPRC || 0}</span>
                                            <span style={{ color: JENIS_COLORS.JPRD }}>JPRD {d.JPRD || 0}</span>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                        <ResponsiveContainer width="100%" height={Math.max(220, byDun.length * 38)}>
                            <BarChart data={byDun} layout="vertical" margin={{ left: 40 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke={t.chartGrid} horizontal={false} />
                                <XAxis type="number" stroke={t.chartTick} style={{ fontSize: '11px' }} allowDecimals={false} />
                                <YAxis type="category" dataKey="dun" width={130} stroke={t.chartTick} style={{ fontSize: '10px' }} />
                                <Tooltip contentStyle={t.tooltip} />
                                <Legend wrapperStyle={{ fontSize: '12px' }} />
                                {jenisOptions.map((j) => (
                                    <Bar key={j} dataKey={j} name={JENIS_LABEL[j]} stackId="a" fill={JENIS_COLORS[j]} />
                                ))}
                            </BarChart>
                        </ResponsiveContainer>
                    </>
                )}
            </div>

            {/* Management */}
            <div className={`${t.card}`}>
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h3 className={t.cardTitle + ' !mb-0'}>Senarai Ahli Jawatankuasa</h3>
                    <div className="flex flex-wrap gap-2">
                        <button onClick={() => router.post(route('pilihanraya.jawatankuasa.resync'), {}, { preserveScroll: true })} className={t.buttonSecondary}><RefreshCw className="h-4 w-4" /> Sync Semula</button>
                        <button onClick={() => setModal({})} className={t.buttonPrimary}><Plus className="h-4 w-4" /> Tambah</button>
                    </div>
                </div>

                <div className="mb-4 p-4 rounded-lg border border-dashed border-slate-300 dark:border-slate-700">
                    <UploadForm jenisOptions={jenisOptions} />
                </div>

                <div className="flex flex-wrap gap-3 items-end mb-4">
                    <div>
                        <label className={t.label}>Jenis</label>
                        <select value={filters.jenis || ''} onChange={(e) => applyFilters({ jenis: e.target.value })} className={t.input}>
                            <option value="">Semua</option>
                            {jenisOptions.map((j) => <option key={j} value={j}>{JENIS_LABEL[j]}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={t.label}>DUN</label>
                        <select value={filters.dun || ''} onChange={(e) => applyFilters({ dun: e.target.value })} className={t.input}>
                            <option value="">Semua DUN</option>
                            {dunOptions.map((d) => <option key={d} value={d}>{d}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={t.label}>Carian (Nama / IC)</label>
                        <div className="flex gap-2">
                            <input value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && applyFilters()} className={t.input} />
                            <button onClick={() => applyFilters()} className={t.buttonSecondary}>Cari</button>
                        </div>
                    </div>
                </div>

                {selected.size > 0 && (
                    <div className="flex items-center justify-between gap-3 mb-3 px-4 py-2 rounded-lg bg-red-500/10 border border-red-500/30">
                        <span className="text-sm text-red-500 font-medium">{selected.size} dipilih</span>
                        <div className="flex gap-2">
                            <button onClick={() => setSelected(new Set())} className="px-3 py-1.5 text-sm border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50">Nyahpilih</button>
                            <button onClick={bulkDelete} disabled={bulkDeleting} className="px-3 py-1.5 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 inline-flex items-center gap-2">
                                {bulkDeleting && <Loader2 className="h-4 w-4 animate-spin" />}<Trash2 className="h-4 w-4" /> Padam Terpilih
                            </button>
                        </div>
                    </div>
                )}

                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr>
                                <th className={t.tableHead + ' w-10'}>
                                    <input type="checkbox" checked={allSelected} onChange={toggleAll} className="rounded border-slate-400" />
                                </th>
                                <th className={t.tableHead}>Nama</th>
                                <th className={t.tableHead}>IC</th>
                                <th className={t.tableHead}>Jenis</th>
                                <th className={t.tableHead}>Jawatan</th>
                                <th className={t.tableHead}>DUN</th>
                                <th className={t.tableHead}>Sentimen</th>
                                <th className={t.tableHead}>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {members.data.length === 0 && <tr><td colSpan={8} className={`${t.tableCell} text-center py-8`}>Tiada ahli jawatankuasa.</td></tr>}
                            {members.data.map((m) => (
                                <tr key={m.id} className={t.tableRow}>
                                    <td className={t.tableCell}>
                                        <input type="checkbox" checked={selected.has(m.id)} onChange={() => toggleOne(m.id)} className="rounded border-slate-400" />
                                    </td>
                                    <td className={t.tableCell + ' font-medium'}>{m.nama}</td>
                                    <td className={t.tableCell}>{m.no_ic || '-'}</td>
                                    <td className={t.tableCell}>{JENIS_LABEL[m.jenis]}</td>
                                    <td className={t.tableCell}>{m.jawatan || '-'}</td>
                                    <td className={t.tableCell}>{m.dun || m.matched_kadun || '-'}</td>
                                    <td className={t.tableCell}>
                                        {m.is_dicula
                                            ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/15 text-red-500">Dicula</span>
                                            : <span className={`${t.subtext} text-xs`}>{m.voter_color || '-'}</span>}
                                    </td>
                                    <td className={t.tableCell}>
                                        <div className="flex gap-2">
                                            <button onClick={() => setModal(m)} className="p-1.5 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-800"><Pencil className="h-3.5 w-3.5" /></button>
                                            <button onClick={() => remove(m)} className="p-1.5 rounded-lg border border-red-500/40 text-red-400 hover:bg-red-500/10"><Trash2 className="h-3.5 w-3.5" /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {members.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4">
                        <p className={`${t.subtext} text-sm`}>Halaman {members.current_page} / {members.last_page}</p>
                        <div className="flex gap-2">
                            {members.prev_page_url && <a href={members.prev_page_url} className={t.buttonSecondary}>Sebelum</a>}
                            {members.next_page_url && <a href={members.next_page_url} className={t.buttonSecondary}>Seterusnya</a>}
                        </div>
                    </div>
                )}
            </div>

            {modal && <MemberModal member={modal.id ? modal : null} jenisOptions={jenisOptions} onClose={() => setModal(null)} />}
        </>
    );
}

export default function Jawatankuasa(props) {
    return (
        <AuthenticatedLayout>
            <Head title="Pilihanraya — Jawatankuasa" />
            <PilihanrayaShell
                title="Jawatankuasa Pilihanraya"
                subtitle="JPRC (peringkat cabang) & JPRD (mengikut DUN) — muat naik fail struktur, AI tentukan jenis & DUN"
            >
                <Content {...props} />
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
