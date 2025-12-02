import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    Search,
    Plus,
    Edit,
    Trash2,
    X,
    ArrowLeft,
    Building2,
    Vote,
    Users2
} from 'lucide-react';

export default function Index({ mpkk, kadunList, selectedKadun, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [editingId, setEditingId] = useState(null);
    const [showAddModal, setShowAddModal] = useState(false);

    const { data: addData, setData: setAddData, post, processing: addProcessing, errors: addErrors, reset: resetAdd } = useForm({
        nama: '',
        kadun_id: selectedKadun?.id || '',
    });

    const { data: editData, setData: setEditData, put, processing: editProcessing, errors: editErrors, reset: resetEdit } = useForm({
        nama: '',
        kadun_id: '',
    });

    const handleSearch = (e) => {
        e.preventDefault();
        const url = selectedKadun
            ? route('master-data.mpkk.filter', selectedKadun.id)
            : route('master-data.mpkk.index');
        router.get(url, { search }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleAdd = (e) => {
        e.preventDefault();
        post(route('master-data.mpkk.store'), {
            onSuccess: () => {
                resetAdd();
                setShowAddModal(false);
            },
        });
    };

    const handleEdit = (item) => {
        setEditingId(item.id);
        setEditData({
            nama: item.nama,
            kadun_id: item.kadun_id,
        });
    };

    const handleUpdate = (e, id) => {
        e.preventDefault();
        put(route('master-data.mpkk.update', id), {
            onSuccess: () => {
                resetEdit();
                setEditingId(null);
            },
        });
    };

    const handleDelete = (id) => {
        if (confirm('Adakah anda pasti mahu memadam MPKK ini?')) {
            router.delete(route('master-data.mpkk.destroy', id));
        }
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        resetEdit();
    };

    return (
        <AuthenticatedLayout>
            <Head title={selectedKadun ? `MPKK - ${selectedKadun.nama}` : 'MPKK'} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        {selectedKadun && (
                            <button
                                onClick={() => router.get(route('master-data.kadun.index', selectedKadun.bandar_id))}
                                className="p-2 hover:bg-slate-100 rounded-lg transition-colors"
                            >
                                <ArrowLeft className="h-5 w-5 text-slate-600" />
                            </button>
                        )}
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">
                                {selectedKadun ? `MPKK - ${selectedKadun.nama}` : 'Semua MPKK'}
                            </h1>
                            <p className="text-sm text-slate-600 mt-1">Urus senarai Majlis Pengurusan Komuniti Kampung</p>
                        </div>
                    </div>
                    <button
                        onClick={() => setShowAddModal(true)}
                        className="flex items-center space-x-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Tambah MPKK</span>
                    </button>
                </div>

                {/* Search */}
                <div className="bg-white rounded-xl border border-slate-200 p-4">
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari MPKK..."
                                className="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            />
                        </div>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                        >
                            Cari
                        </button>
                        {search && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch('');
                                    const url = selectedKadun
                                        ? route('master-data.mpkk.filter', selectedKadun.id)
                                        : route('master-data.mpkk.index');
                                    router.get(url);
                                }}
                                className="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                            >
                                Reset
                            </button>
                        )}
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase w-16">Bil</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Parlimen</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Nama KADUN</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Nama MPKK</th>
                                    <th className="text-right py-3 px-4 text-xs font-semibold text-slate-600 uppercase w-32">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {mpkk.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="5" className="py-8 text-center text-slate-500">
                                            Tiada rekod dijumpai
                                        </td>
                                    </tr>
                                ) : (
                                    mpkk.data.map((item, index) => (
                                        <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="py-3 px-4 text-sm text-slate-600">
                                                {mpkk.from + index}
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center space-x-2">
                                                    <Building2 className="h-4 w-4 text-slate-400" />
                                                    <span className="text-sm text-slate-600">{item.kadun?.bandar?.nama}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4">
                                                {editingId === item.id ? (
                                                    <select
                                                        value={editData.kadun_id}
                                                        onChange={(e) => setEditData('kadun_id', e.target.value)}
                                                        className="w-full px-3 py-1.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                    >
                                                        <option value="">Pilih KADUN</option>
                                                        {kadunList.map((kadun) => (
                                                            <option key={kadun.id} value={kadun.id}>
                                                                {kadun.nama} ({kadun.bandar?.nama})
                                                            </option>
                                                        ))}
                                                    </select>
                                                ) : (
                                                    <div className="flex items-center space-x-2">
                                                        <Vote className="h-4 w-4 text-slate-400" />
                                                        <span className="text-sm text-slate-600">{item.kadun?.nama}</span>
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                {editingId === item.id ? (
                                                    <input
                                                        type="text"
                                                        value={editData.nama}
                                                        onChange={(e) => setEditData('nama', e.target.value)}
                                                        className="w-full px-3 py-1.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                        autoFocus
                                                    />
                                                ) : (
                                                    <div className="flex items-center space-x-2">
                                                        <Users2 className="h-4 w-4 text-slate-400" />
                                                        <span className="text-sm font-medium text-slate-900">{item.nama}</span>
                                                    </div>
                                                )}
                                                {editErrors.nama && editingId === item.id && (
                                                    <p className="text-sm text-rose-600 mt-1">{editErrors.nama}</p>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                {editingId === item.id ? (
                                                    <div className="flex items-center justify-end space-x-2">
                                                        <button
                                                            onClick={(e) => handleUpdate(e, item.id)}
                                                            disabled={editProcessing}
                                                            className="px-3 py-1.5 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                                                        >
                                                            Simpan
                                                        </button>
                                                        <button
                                                            onClick={handleCancelEdit}
                                                            className="px-3 py-1.5 border border-slate-300 text-slate-700 text-sm rounded-lg hover:bg-slate-50 transition-colors"
                                                        >
                                                            Batal
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <div className="flex items-center justify-end space-x-2">
                                                        <button
                                                            onClick={() => handleEdit(item)}
                                                            className="p-2 text-slate-600 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleDelete(item.id)}
                                                            className="p-2 text-slate-600 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {mpkk.last_page > 1 && (
                        <div className="border-t border-slate-200 px-4 py-3 flex items-center justify-between">
                            <div className="text-sm text-slate-600">
                                Menunjukkan {mpkk.from} hingga {mpkk.to} daripada {mpkk.total} rekod
                            </div>
                            <div className="flex items-center space-x-2">
                                {mpkk.links.map((link, index) => (
                                    <button
                                        key={index}
                                        onClick={() => link.url && router.get(link.url)}
                                        disabled={!link.url}
                                        className={`px-3 py-1 rounded-lg text-sm transition-colors ${link.active
                                                ? 'bg-slate-900 text-white'
                                                : link.url
                                                    ? 'border border-slate-300 text-slate-700 hover:bg-slate-50'
                                                    : 'border border-slate-200 text-slate-400 cursor-not-allowed'
                                            }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Add Modal */}
            {showAddModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-xl max-w-md w-full p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-xl font-bold text-slate-900">Tambah MPKK</h2>
                            <button
                                onClick={() => {
                                    setShowAddModal(false);
                                    resetAdd();
                                }}
                                className="p-2 hover:bg-slate-100 rounded-lg transition-colors"
                            >
                                <X className="h-5 w-5 text-slate-600" />
                            </button>
                        </div>
                        <form onSubmit={handleAdd} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Nama MPKK <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={addData.nama}
                                    onChange={(e) => setAddData('nama', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    placeholder="Contoh: Kampung Baharu"
                                    autoFocus
                                />
                                {addErrors.nama && <p className="text-sm text-rose-600 mt-1">{addErrors.nama}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    KADUN <span className="text-rose-500">*</span>
                                </label>
                                <select
                                    value={addData.kadun_id}
                                    onChange={(e) => setAddData('kadun_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Pilih KADUN</option>
                                    {kadunList.map((kadun) => (
                                        <option key={kadun.id} value={kadun.id}>
                                            {kadun.nama} ({kadun.bandar?.nama})
                                        </option>
                                    ))}
                                </select>
                                {addErrors.kadun_id && <p className="text-sm text-rose-600 mt-1">{addErrors.kadun_id}</p>}
                            </div>
                            <div className="flex items-center justify-end space-x-3 pt-4">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowAddModal(false);
                                        resetAdd();
                                    }}
                                    className="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={addProcessing}
                                    className="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                                >
                                    {addProcessing ? 'Menyimpan...' : 'Simpan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
