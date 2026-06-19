import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Search, RefreshCw, Pencil, Trash2, X } from 'lucide-react';
import KeanggotaanNav from './Nav';

const COLOR_BADGE = {
    putih: 'bg-emerald-100 text-emerald-800',
    hitam: 'bg-red-100 text-red-800',
    kelabu: 'bg-slate-100 text-slate-700',
};

function MemberModal({ member, onClose }) {
    const isEdit = !!member;
    const { data, setData, post, put, processing, errors } = useForm({
        no_ic: member?.no_ic || '',
        nama: member?.nama || '',
        no_tel: member?.no_tel || '',
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (isEdit) put(route('keanggotaan.member.update', member.id), opts);
        else post(route('keanggotaan.member.store'), opts);
    };

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-base font-semibold text-slate-900">{isEdit ? 'Kemaskini Ahli' : 'Tambah Ahli'}</h3>
                    <button onClick={onClose}><X className="h-5 w-5 text-slate-400" /></button>
                </div>
                <form onSubmit={submit} className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">No. IC <span className="text-rose-500">*</span></label>
                        <input value={data.no_ic} onChange={(e) => setData('no_ic', e.target.value)} maxLength={12} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" required />
                        {errors.no_ic && <p className="text-sm text-rose-600 mt-1">{errors.no_ic}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Nama <span className="text-rose-500">*</span></label>
                        <input value={data.nama} onChange={(e) => setData('nama', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" required />
                        {errors.nama && <p className="text-sm text-rose-600 mt-1">{errors.nama}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">No. Telefon</label>
                        <input value={data.no_tel} onChange={(e) => setData('no_tel', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                    </div>
                    <p className="text-xs text-slate-500">Padanan kawasan / DUN / umur akan dikira automatik daripada No. IC.</p>
                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50">Batal</button>
                        <button type="submit" disabled={processing} className="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Senarai({ members, filters, flash }) {
    const [search, setSearch] = useState(filters.search || '');
    const [modal, setModal] = useState(null); // { } for add, member object for edit

    const applyFilters = (extra = {}) => {
        router.get(route('keanggotaan.senarai'), { search, status_kawasan: filters.status_kawasan, ...extra }, { preserveState: true, replace: true });
    };

    const remove = (member) => {
        if (confirm('Padam ahli ini?')) router.delete(route('keanggotaan.member.destroy', member.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Keanggotaan — Senarai Ahli" />
            <div className="max-w-6xl mx-auto space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-slate-900">Senarai Ahli</h1>
                    <div className="flex gap-2">
                        <button onClick={() => router.post(route('keanggotaan.resync'), {}, { preserveScroll: true })} className="flex items-center gap-2 px-4 py-2 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50">
                            <RefreshCw className="h-4 w-4" /> Sync Semula
                        </button>
                        <button onClick={() => setModal({})} className="flex items-center gap-2 px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800">
                            <Plus className="h-4 w-4" /> Tambah Ahli
                        </button>
                    </div>
                </div>

                <KeanggotaanNav />

                {flash?.success && <div className="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{flash.success}</div>}

                <div className="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
                    <div className="flex-1 min-w-[200px]">
                        <label className="block text-sm font-medium text-slate-700 mb-1">Carian (Nama / IC)</label>
                        <div className="flex gap-2">
                            <input value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && applyFilters()} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                            <button onClick={() => applyFilters()} className="px-3 py-2 bg-slate-900 text-white rounded-lg"><Search className="h-4 w-4" /></button>
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Status Kawasan</label>
                        <select value={filters.status_kawasan || ''} onChange={(e) => applyFilters({ status_kawasan: e.target.value })} className="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua</option>
                            <option value="dalam_kawasan">Dalam Kawasan</option>
                            <option value="luar_kawasan">Luar Kawasan</option>
                        </select>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-slate-600">
                                <th className="py-3 px-3 font-medium">Nama</th>
                                <th className="py-3 px-3 font-medium">No. IC</th>
                                <th className="py-3 px-3 font-medium">Umur</th>
                                <th className="py-3 px-3 font-medium">DUN</th>
                                <th className="py-3 px-3 font-medium">Status</th>
                                <th className="py-3 px-3 font-medium">Sentimen</th>
                                <th className="py-3 px-3 font-medium text-center">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {members.data.length === 0 && (
                                <tr><td colSpan={7} className="py-8 text-center text-slate-500">Tiada ahli.</td></tr>
                            )}
                            {members.data.map((m) => (
                                <tr key={m.id} className="hover:bg-slate-50">
                                    <td className="py-3 px-3 text-slate-900 font-medium">{m.nama}</td>
                                    <td className="py-3 px-3 text-slate-600">{m.no_ic}</td>
                                    <td className="py-3 px-3 text-slate-600">{m.umur ?? '-'}</td>
                                    <td className="py-3 px-3 text-slate-600">{m.matched_kadun || '-'}</td>
                                    <td className="py-3 px-3">
                                        {m.status_kawasan === 'dalam_kawasan'
                                            ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Dalam Kawasan</span>
                                            : <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Luar Kawasan</span>}
                                    </td>
                                    <td className="py-3 px-3">
                                        {m.voter_color
                                            ? <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${COLOR_BADGE[m.voter_color] || 'bg-slate-100 text-slate-700'}`}>{m.voter_color}{m.is_dicula ? ' (dicula)' : ''}</span>
                                            : <span className="text-slate-400">-</span>}
                                    </td>
                                    <td className="py-3 px-3">
                                        <div className="flex items-center justify-center gap-2">
                                            <button onClick={() => setModal(m)} className="p-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100"><Pencil className="h-3.5 w-3.5" /></button>
                                            <button onClick={() => remove(m)} className="p-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50"><Trash2 className="h-3.5 w-3.5" /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {members.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-slate-600">Halaman {members.current_page} / {members.last_page}</p>
                        <div className="flex gap-2">
                            {members.prev_page_url && <a href={members.prev_page_url} className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-100">Sebelum</a>}
                            {members.next_page_url && <a href={members.next_page_url} className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-100">Seterusnya</a>}
                        </div>
                    </div>
                )}
            </div>

            {modal && <MemberModal member={modal.id ? modal : null} onClose={() => setModal(null)} />}
        </AuthenticatedLayout>
    );
}
