import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Plus, Upload, RefreshCw, Pencil, Trash2, X, Crosshair, Users, MapPin } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import { CHART_COLORS } from './theme';

const JENIS_COLORS = {
    JPRC: CHART_COLORS.blue,
    JPRD: CHART_COLORS.violet,
    AJK_CABANG: CHART_COLORS.amber,
    WANITA: '#ec4899',
    AMK: CHART_COLORS.putih,
};
const JENIS_LABEL = { JPRC: 'JPRC', JPRD: 'JPRD', AJK_CABANG: 'AJK Cabang', WANITA: 'Wanita', AMK: 'AMK' };

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
                            <label className="block text-sm font-medium text-slate-700 mb-1">No. IC *</label>
                            <input value={data.no_ic} onChange={(e) => setData('no_ic', e.target.value)} maxLength={12} className={field} required />
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
                    <p className="text-xs text-slate-500">Status "dicula" & padanan kawasan dikira automatik daripada No. IC.</p>
                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50">Batal</button>
                        <button type="submit" disabled={processing} className="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function UploadForm({ jenisOptions }) {
    const { t } = usePilihanrayaTheme();
    const { setData, post, processing, reset } = useForm({ fail: null, jenis_default: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('pilihanraya.jawatankuasa.upload'), { forceFormData: true, preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
            <div>
                <label className={t.label}>Fail Excel/CSV</label>
                <input type="file" accept=".xlsx,.xls,.csv" onChange={(e) => setData('fail', e.target.files[0])} className={t.input} required />
            </div>
            <div>
                <label className={t.label}>Jenis Lalai (jika tiada lajur)</label>
                <select onChange={(e) => setData('jenis_default', e.target.value)} className={t.input}>
                    <option value="">— Pilih —</option>
                    {jenisOptions.map((j) => <option key={j} value={j}>{JENIS_LABEL[j]}</option>)}
                </select>
            </div>
            <button type="submit" disabled={processing} className={t.buttonPrimary}><Upload className="h-4 w-4" /> Muat Naik</button>
        </form>
    );
}

function Content({ members, filters, jenisOptions, summary, perJenis, byDun }) {
    const { t } = usePilihanrayaTheme();
    const [modal, setModal] = useState(null);
    const [search, setSearch] = useState(filters.search || '');

    const applyFilters = (extra = {}) => {
        router.get(route('pilihanraya.jawatankuasa.index'), { search, jenis: filters.jenis, ...extra }, { preserveState: true, replace: true });
    };
    const remove = (m) => { if (confirm('Padam ahli jawatankuasa ini?')) router.delete(route('pilihanraya.jawatankuasa.destroy', m.id), { preserveScroll: true }); };

    return (
        <>
            {/* KPI summary */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div className={t.card}>
                    <div className="flex items-center justify-between"><span className={t.kpiLabel}>Jumlah Ahli Jawatankuasa</span><Users className="h-5 w-5 text-slate-400" /></div>
                    <div className={t.kpiValue}>{summary.total.toLocaleString()}</div>
                </div>
                <div className={t.card}>
                    <div className="flex items-center justify-between"><span className={t.kpiLabel}>Telah Dicula (Hitam)</span><Crosshair className="h-5 w-5 text-red-500" /></div>
                    <div className={`${t.kpiValue} text-red-500`}>{summary.dicula.toLocaleString()}</div>
                    <p className={`${t.subtext} text-xs mt-1`}>{summary.dicula_pct}% daripada jumlah</p>
                </div>
                <div className={t.card}>
                    <div className="flex items-center justify-between"><span className={t.kpiLabel}>Dalam Kawasan (DPT)</span><MapPin className="h-5 w-5 text-emerald-500" /></div>
                    <div className={`${t.kpiValue} text-emerald-500`}>{summary.dalam_kawasan.toLocaleString()}</div>
                </div>
            </div>

            {/* Per-jenis dicula */}
            <div className={`${t.card} mb-6`}>
                <h3 className={t.cardTitle}>Culaan Mengikut Jenis Jawatankuasa</h3>
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    {jenisOptions.map((j) => {
                        const row = perJenis.find((p) => p.jenis === j) || { total: 0, dicula: 0, dicula_pct: 0 };
                        return (
                            <div key={j} className={`${t.cardTight} text-center`}>
                                <div className="text-xs font-semibold" style={{ color: JENIS_COLORS[j] }}>{JENIS_LABEL[j]}</div>
                                <div className={`text-2xl font-bold ${t.text} mt-1`}>{row.dicula}/{row.total}</div>
                                <div className={`${t.subtext} text-xs`}>{row.dicula_pct}% dicula</div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* By DUN distribution */}
            <div className={`${t.card} mb-6`}>
                <h3 className={t.cardTitle}>Taburan Jawatankuasa Mengikut DUN</h3>
                {byDun.length === 0 ? <p className={`${t.subtext} text-sm py-12 text-center`}>Tiada data.</p> : (
                    <ResponsiveContainer width="100%" height={Math.max(280, byDun.length * 32)}>
                        <BarChart data={byDun} layout="vertical" margin={{ left: 60 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke={t.chartGrid} horizontal={false} />
                            <XAxis type="number" stroke={t.chartTick} style={{ fontSize: '11px' }} />
                            <YAxis type="category" dataKey="dun" width={150} stroke={t.chartTick} style={{ fontSize: '10px' }} />
                            <Tooltip contentStyle={t.tooltip} />
                            <Legend wrapperStyle={{ fontSize: '12px' }} />
                            {jenisOptions.map((j) => (
                                <Bar key={j} dataKey={j} name={JENIS_LABEL[j]} stackId="a" fill={JENIS_COLORS[j]} />
                            ))}
                        </BarChart>
                    </ResponsiveContainer>
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
                        <label className={t.label}>Carian (Nama / IC)</label>
                        <div className="flex gap-2">
                            <input value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && applyFilters()} className={t.input} />
                            <button onClick={() => applyFilters()} className={t.buttonSecondary}>Cari</button>
                        </div>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr>
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
                            {members.data.length === 0 && <tr><td colSpan={7} className={`${t.tableCell} text-center py-8`}>Tiada ahli jawatankuasa.</td></tr>}
                            {members.data.map((m) => (
                                <tr key={m.id} className={t.tableRow}>
                                    <td className={t.tableCell + ' font-medium'}>{m.nama}</td>
                                    <td className={t.tableCell}>{m.no_ic}</td>
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
                subtitle="JPRC / JPRD / AJK Cabang / Wanita / AMK — pantau anggota yang dicula dan taburan mengikut DUN"
            >
                <Content {...props} />
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
