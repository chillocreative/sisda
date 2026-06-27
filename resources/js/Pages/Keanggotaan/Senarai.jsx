import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Search, RefreshCw, Pencil, Trash2, X, Eye, Download } from 'lucide-react';
import useDragScroll from '@/Hooks/useDragScroll';
import KeanggotaanNav from './Nav';

// Windowed page numbers: 1 … current-1 current current+1 … last
function pageWindow(current, last, delta = 2) {
    const range = [];
    for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) range.push(i);
    const out = [];
    if (range[0] > 1) {
        out.push(1);
        if (range[0] > 2) out.push('...');
    }
    out.push(...range);
    if (range[range.length - 1] < last) {
        if (range[range.length - 1] < last - 1) out.push('...');
        out.push(last);
    }
    return out;
}

const SENTIMEN = {
    putih: { label: 'Putih', cls: 'bg-emerald-500 text-white' },
    hitam: { label: 'Hitam', cls: 'bg-slate-900 text-white' },
    kelabu: { label: 'Kelabu', cls: 'bg-slate-400 text-white' },
};

function SentimenCell({ color }) {
    if (!color) {
        return <span className="text-xs text-slate-400 italic">Belum Dicula</span>;
    }
    const s = SENTIMEN[color] || { label: color, cls: 'bg-slate-300 text-slate-700' };
    return <span className={`inline-block px-3 py-1 rounded text-xs font-semibold ${s.cls}`}>{s.label}</span>;
}

// Match the "Sayap Mengikut Cabang" chart colours.
const WING_COLORS = { AMK: '#2563eb', Srikandi: '#db2777', Wanita: '#9333ea' };

function SayapCell({ wings, graceWings = [] }) {
    if (!wings || wings.length === 0) {
        return <span className="text-xs text-slate-400">-</span>;
    }
    return (
        <div className="flex flex-wrap gap-1">
            {wings.map((w) => {
                const isGrace = graceWings.includes(w);
                return (
                    <span
                        key={w}
                        className={`inline-block px-2 py-0.5 rounded text-xs font-semibold ${isGrace ? 'bg-red-100 text-red-700 border border-red-200' : 'text-white'}`}
                        style={isGrace ? undefined : { backgroundColor: WING_COLORS[w] || '#6366f1' }}
                    >{w}</span>
                );
            })}
        </div>
    );
}

const STATUS_ANGGOTA = {
    aktif: { label: 'Aktif', cls: 'bg-emerald-100 text-emerald-800' },
    tidak_aktif: { label: 'Tidak Aktif', cls: 'bg-slate-200 text-slate-700' },
};

function StatusAnggotaCell({ status, tanpaPengetahuan }) {
    return (
        <div className="flex flex-col items-start gap-1">
            {status
                ? <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_ANGGOTA[status]?.cls || 'bg-slate-200 text-slate-700'}`}>{STATUS_ANGGOTA[status]?.label || status}</span>
                : <span className="text-xs text-slate-400 italic">Belum Ditetapkan</span>}
            {tanpaPengetahuan && <span className="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700 whitespace-nowrap">Daftar Tanpa Pengetahuan</span>}
        </div>
    );
}

function ViewModal({ member, onClose }) {
    const rows = [
        ['No. Anggota', member.no_anggota],
        ['Nama', member.nama],
        ['No. IC', member.no_ic],
        ['No. Telefon', member.no_tel],
        ['Alamat', member.alamat],
        ['Umur', member.umur],
        ['Jantina', member.jantina],
        ['Bangsa', member.bangsa],
        ['Cabang', member.cabang],
        ['Negeri', member.negeri],
        ['DUN (Padanan)', member.matched_kadun],
        ['Parlimen (Padanan)', member.matched_parlimen],
        ['Tahun Lahir', member.tahun_lahir],
        ['Status Kawasan', member.status_kawasan === 'dalam_kawasan' ? 'Pengundi Dalam Kawasan' : member.status_kawasan === 'tiada_dppr' ? 'Tiada dalam DPPR/DPT' : member.status_kawasan === 'luar_kawasan' ? 'Pengundi Luar' : null],
        ['Sentimen', member.voter_color ? member.voter_color.charAt(0).toUpperCase() + member.voter_color.slice(1) : 'Belum Dicula'],
        ['Sayap', (member.wings && member.wings.length) ? member.wings.join(', ') : null],
        ['Status Anggota', STATUS_ANGGOTA[member.status_anggota]?.label || null],
        ['Daftar Tanpa Pengetahuan', member.daftar_tanpa_pengetahuan ? 'Ya' : 'Tidak'],
    ];

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-base font-semibold text-slate-900">Butiran Anggota</h3>
                    <button onClick={onClose}><X className="h-5 w-5 text-slate-400" /></button>
                </div>
                <dl className="divide-y divide-slate-100">
                    {rows.map(([label, value]) => (
                        <div key={label} className="flex items-start justify-between gap-4 py-2">
                            <dt className="text-sm text-slate-500">{label}</dt>
                            <dd className="text-sm font-medium text-slate-900 text-right">{value ?? <span className="text-slate-400">-</span>}</dd>
                        </div>
                    ))}
                </dl>
                <div className="flex justify-end pt-4">
                    <button type="button" onClick={onClose} className="px-4 py-2 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50">Tutup</button>
                </div>
            </div>
        </div>
    );
}

function MemberModal({ member, onClose, parlimenList = [] }) {
    const { auth } = usePage().props;
    const isAdmin = ['super_admin', 'admin'].includes(auth.user.role);
    const isEdit = !!member;
    const { data, setData, post, put, processing, errors } = useForm({
        no_ic: member?.no_ic || '',
        nama: member?.nama || '',
        no_tel: member?.no_tel || '',
        status_anggota: member?.status_anggota || '',
        daftar_tanpa_pengetahuan: member?.daftar_tanpa_pengetahuan || false,
        // Admin-only fields
        no_anggota: member?.no_anggota || '',
        alamat: member?.alamat || '',
        bangsa: member?.bangsa || '',
        jantina: member?.jantina || '',
        cabang: member?.cabang || '',
        negeri: member?.negeri || '',
        voter_color: member?.voter_color || '',
        status_kawasan: member?.status_kawasan || '',
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (isEdit) put(route('keanggotaan.member.update', member.id), opts);
        else post(route('keanggotaan.member.store'), opts);
    };

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
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
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Status Anggota</label>
                        <select value={data.status_anggota} onChange={(e) => setData('status_anggota', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">— Belum Ditetapkan —</option>
                            <option value="aktif">Aktif</option>
                            <option value="tidak_aktif">Tidak Aktif</option>
                        </select>
                    </div>
                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" checked={data.daftar_tanpa_pengetahuan} onChange={(e) => setData('daftar_tanpa_pengetahuan', e.target.checked)} className="rounded border-slate-400" />
                        Daftar Tanpa Pengetahuan
                    </label>

                    {isAdmin && (
                        <>
                            <hr className="border-slate-200" />
                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide">Maklumat Tambahan (Admin)</p>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">No. Anggota</label>
                                <input value={data.no_anggota} onChange={(e) => setData('no_anggota', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Bangsa</label>
                                <input value={data.bangsa} onChange={(e) => setData('bangsa', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Melayu / Cina / India / dll" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Jantina</label>
                                <select value={data.jantina} onChange={(e) => setData('jantina', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                    <option value="">— Pilih —</option>
                                    <option value="LELAKI">Lelaki</option>
                                    <option value="PEREMPUAN">Perempuan</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Cabang (Parlimen)</label>
                                {parlimenList.length > 0 ? (
                                    <select value={data.cabang} onChange={(e) => setData('cabang', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                        <option value="">— Pilih —</option>
                                        {parlimenList.map((p) => <option key={p} value={p}>{p}</option>)}
                                    </select>
                                ) : (
                                    <input value={data.cabang} onChange={(e) => setData('cabang', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Negeri</label>
                                <input value={data.negeri} onChange={(e) => setData('negeri', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Alamat</label>
                                <textarea value={data.alamat} onChange={(e) => setData('alamat', e.target.value)} rows={2} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Sentimen (Warna Pengundi)</label>
                                <select value={data.voter_color} onChange={(e) => setData('voter_color', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                    <option value="">— Belum Dicula —</option>
                                    <option value="putih">Putih (Penyokong)</option>
                                    <option value="kelabu">Kelabu (Atas Pagar)</option>
                                    <option value="hitam">Hitam (Pembangkang)</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Status Pengundi</label>
                                <select value={data.status_kawasan} onChange={(e) => setData('status_kawasan', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                    <option value="">— Auto (daripada DPPR) —</option>
                                    <option value="dalam_kawasan">Pengundi Dalam Kawasan</option>
                                    <option value="luar_kawasan">Pengundi Luar</option>
                                    <option value="tiada_dppr">Tiada dalam DPPR/DPT</option>
                                </select>
                            </div>
                        </>
                    )}

                    {!isAdmin && <p className="text-xs text-slate-500">Umur, jantina, bangsa, kawasan & sentimen dikira automatik daripada No. IC.</p>}
                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50">Batal</button>
                        <button type="submit" disabled={processing} className="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Senarai({ members, filters, parlimenList = [], dunList = [], bangsaList = [], flash }) {
    const [search, setSearch] = useState(filters.search || '');
    const [modal, setModal] = useState(null);
    const [viewing, setViewing] = useState(null);
    const scrollRef = useDragScroll();

    const baseParams = { search, status_kawasan: filters.status_kawasan, parlimen: filters.parlimen, dun: filters.dun, bangsa: filters.bangsa, jantina: filters.jantina, status_anggota: filters.status_anggota, sentimen: filters.sentimen, sayap: filters.sayap };
    const exportParams = Object.fromEntries(Object.entries(baseParams).filter(([, v]) => v));

    const applyFilters = (extra = {}) => {
        router.get(route('keanggotaan.senarai'), { ...baseParams, ...extra }, { preserveState: true, replace: true });
    };

    const goToPage = (page) => {
        if (page < 1 || page > members.last_page || page === members.current_page) return;
        router.get(route('keanggotaan.senarai'), { ...baseParams, page }, { preserveState: true, replace: true, preserveScroll: true });
    };

    const remove = (member) => {
        if (confirm('Padam ahli ini?')) router.delete(route('keanggotaan.member.destroy', member.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Keanggotaan — Senarai Ahli" />
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-slate-900">Senarai Ahli</h1>
                    <div className="flex gap-2">
                        <a href={route('keanggotaan.senarai.export', exportParams)} className="flex items-center gap-2 px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <Download className="h-4 w-4" /> Muat Turun PDF
                        </a>
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

                <div className="bg-white rounded-xl border border-slate-200 p-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3 items-end">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Carian (Nama / IC)</label>
                        <div className="flex gap-2">
                            <input value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && applyFilters()} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                            <button onClick={() => applyFilters()} className="px-3 py-2 bg-slate-900 text-white rounded-lg"><Search className="h-4 w-4" /></button>
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Parlimen / Cabang</label>
                        <select value={filters.parlimen || ''} onChange={(e) => applyFilters({ parlimen: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua Parlimen</option>
                            {parlimenList.map((p) => <option key={p} value={p}>{p}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">DUN</label>
                        <select value={filters.dun || ''} onChange={(e) => applyFilters({ dun: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua DUN</option>
                            {dunList.map((d) => <option key={d} value={d}>{d}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Status Kawasan</label>
                        <select value={filters.status_kawasan || ''} onChange={(e) => applyFilters({ status_kawasan: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua</option>
                            <option value="dalam_kawasan">Pengundi Dalam Kawasan</option>
                            <option value="luar_kawasan">Pengundi Luar</option>
                            <option value="tiada_dppr">Tiada dalam DPPR/DPT</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Sentimen</label>
                        <select value={filters.sentimen || ''} onChange={(e) => applyFilters({ sentimen: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua</option>
                            <option value="putih">Putih</option>
                            <option value="kelabu">Kelabu</option>
                            <option value="hitam">Hitam</option>
                            <option value="belum_dicula">Belum Dicula</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Sayap</label>
                        <select value={filters.sayap || ''} onChange={(e) => applyFilters({ sayap: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua</option>
                            <option value="AMK">AMK</option>
                            <option value="Srikandi">Srikandi</option>
                            <option value="Wanita">Wanita</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Bangsa</label>
                        <select value={filters.bangsa || ''} onChange={(e) => applyFilters({ bangsa: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua Bangsa</option>
                            {bangsaList.map((b) => <option key={b} value={b}>{b}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Jantina</label>
                        <select value={filters.jantina || ''} onChange={(e) => applyFilters({ jantina: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua</option>
                            <option value="LELAKI">Lelaki</option>
                            <option value="PEREMPUAN">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Status Anggota</label>
                        <select value={filters.status_anggota || ''} onChange={(e) => applyFilters({ status_anggota: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua</option>
                            <option value="aktif">Aktif</option>
                            <option value="tidak_aktif">Tidak Aktif</option>
                        </select>
                    </div>
                </div>

                <div ref={scrollRef} className="bg-white rounded-xl border border-slate-200 overflow-x-auto cursor-grab">
                    <table className="w-full text-sm min-w-[1100px]">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-slate-600 bg-slate-50">
                                <th className="py-3 px-3 font-medium whitespace-nowrap">No. Anggota</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">Nama</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">No. IC</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">Umur</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">Bangsa</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">Jantina</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">Sayap</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">Status Pengundi</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">Status Anggota</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">DUN</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">Cabang</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap">Sentimen</th>
                                <th className="py-3 px-3 font-medium whitespace-nowrap text-center">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {members.data.length === 0 && (
                                <tr><td colSpan={13} className="py-8 text-center text-slate-500">Tiada ahli.</td></tr>
                            )}
                            {members.data.map((m) => (
                                <tr key={m.id} className={m.wing_grace ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-slate-50'}>
                                    <td className="py-3 px-3 text-slate-600 whitespace-nowrap">{m.no_anggota || '-'}</td>
                                    <td className="py-3 px-3 text-slate-900 font-medium whitespace-nowrap">{m.nama}</td>
                                    <td className="py-3 px-3 text-slate-600 whitespace-nowrap">{m.no_ic}</td>
                                    <td className="py-3 px-3 text-slate-600">{m.umur ?? '-'}</td>
                                    <td className="py-3 px-3 text-slate-600 whitespace-nowrap">{m.bangsa || '-'}</td>
                                    <td className="py-3 px-3 text-slate-600 whitespace-nowrap">{m.jantina || '-'}</td>
                                    <td className="py-3 px-3 whitespace-nowrap"><SayapCell wings={m.wings} graceWings={m.grace_wings} /></td>
                                    <td className="py-3 px-3 whitespace-nowrap">
                                        {m.status_kawasan === 'dalam_kawasan'
                                            ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Pengundi Dalam Kawasan</span>
                                            : m.status_kawasan === 'tiada_dppr'
                                                ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-700">Tiada DPPR/DPT</span>
                                                : m.status_kawasan === 'luar_kawasan'
                                                    ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pengundi Luar</span>
                                                    : <span className="text-xs text-slate-400 italic">Belum Sync</span>}
                                    </td>
                                    <td className="py-3 px-3 whitespace-nowrap"><StatusAnggotaCell status={m.status_anggota} tanpaPengetahuan={m.daftar_tanpa_pengetahuan} /></td>
                                    <td className="py-3 px-3 text-slate-600 whitespace-nowrap">{m.matched_kadun || '-'}</td>
                                    <td className="py-3 px-3 text-slate-600 whitespace-nowrap">{m.cabang || '-'}</td>
                                    <td className="py-3 px-3 whitespace-nowrap"><SentimenCell color={m.voter_color} /></td>
                                    <td className="py-3 px-3 whitespace-nowrap">
                                        <div className="flex items-center justify-center gap-2">
                                            <button onClick={() => setViewing(m)} className="p-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100" title="Lihat butiran"><Eye className="h-3.5 w-3.5" /></button>
                                            <button onClick={() => setModal(m)} className="p-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100" title="Kemaskini"><Pencil className="h-3.5 w-3.5" /></button>
                                            <button onClick={() => remove(m)} className="p-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50" title="Padam"><Trash2 className="h-3.5 w-3.5" /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {members.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-slate-600">Halaman {members.current_page} / {members.last_page} ({members.total} ahli)</p>
                        <div className="flex flex-wrap items-center gap-1">
                            <button onClick={() => goToPage(members.current_page - 1)} disabled={members.current_page === 1} className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-100 disabled:opacity-40 disabled:hover:bg-transparent">Sebelum</button>
                            {pageWindow(members.current_page, members.last_page).map((p, i) => (
                                p === '...'
                                    ? <span key={`d${i}`} className="px-2 text-slate-400 select-none">…</span>
                                    : <button
                                        key={p}
                                        onClick={() => goToPage(p)}
                                        className={`min-w-[2.25rem] px-3 py-1.5 text-sm border rounded-lg ${p === members.current_page ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-100'}`}
                                    >{p}</button>
                            ))}
                            <button onClick={() => goToPage(members.current_page + 1)} disabled={members.current_page === members.last_page} className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-100 disabled:opacity-40 disabled:hover:bg-transparent">Seterusnya</button>
                        </div>
                    </div>
                )}
            </div>

            {modal && <MemberModal member={modal.id ? modal : null} onClose={() => setModal(null)} parlimenList={parlimenList} />}
            {viewing && <ViewModal member={viewing} onClose={() => setViewing(null)} />}
        </AuthenticatedLayout>
    );
}
