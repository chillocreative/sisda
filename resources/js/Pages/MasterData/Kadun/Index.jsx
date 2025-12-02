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
    MapPin
} from 'lucide-react';

export default function Index({ kadun, bandarList, selectedBandar, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [editingId, setEditingId] = useState(null);
    const [showAddModal, setShowAddModal] = useState(false);

    const { data: addData, setData: setAddData, post, processing: addProcessing, errors: addErrors, reset: resetAdd } = useForm({
        nama: '',
        kod_dun: '',
        bandar_id: selectedBandar?.id || '',
    });

    const { data: editData, setData: setEditData, put, processing: editProcessing, errors: editErrors, reset: resetEdit } = useForm({
        nama: '',
        kod_dun: '',
        bandar_id: '',
    });

    const handleSearch = (e) => {
        e.preventDefault();
        const url = selectedBandar
            ? route('master-data.kadun.filter', selectedBandar.id)
            : route('master-data.kadun.index');
        router.get(url, { search }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleAdd = (e) => {
        e.preventDefault();
        post(route('master-data.kadun.store'), {
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
            kod_dun: item.kod_dun || '',
            bandar_id: item.bandar_id,
        });
    };

    const handleUpdate = (e, id) => {
        e.preventDefault();
        put(route('master-data.kadun.update', id), {
            onSuccess: () => {
                resetEdit();
                setEditingId(null);
            },
        });
    };

    const handleDelete = (id) => {
        if (confirm('Adakah anda pasti mahu memadam KADUN ini?')) {
            router.delete(route('master-data.kadun.destroy', id));
        }
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        resetEdit();
    };

    return (
        <AuthenticatedLayout>
            <Head title={selectedBandar ? `KADUN - ${selectedBandar.nama}` : 'KADUN'} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        {selectedBandar && (
                            <button
                                onClick={() => router.get(route('master-data.bandar.index', selectedBandar.negeri_id))}
                                className="p-2 hover:bg-slate-100 rounded-lg transition-colors"
                            >
                                <ArrowLeft className="h-5 w-5 text-slate-600" />
                            </button>
                        )}
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">
                                {selectedBandar ? `KADUN - ${selectedBandar.nama}` : 'Semua KADUN'}
                            </h1>
                            <p className="text-sm text-slate-600 mt-1">Urus senarai Kawasan Dewan Undangan Negeri</p>
                        </div>
                    </div>
                    <button
                        onClick={() => setShowAddModal(true)}
                        className="flex items-center space-x-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Tambah KADUN</span>
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
                                placeholder="Cari KADUN atau kod DUN..."
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
                                    const url = selectedBandar
                                        ? route('master-data.kadun.filter', selectedBandar.id)
                                        : route('master-data.kadun.index');
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
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Nama KADUN</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Kod DUN</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Parlimen (Bandar)</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Negeri</th>
                                    <th className="text-right py-3 px-4 text-xs font-semibold text-slate-600 uppercase w-32">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {kadun.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="6" className="py-8 text-center text-slate-500">
                                            Tiada rekod dijumpai
                                        </td>
                                    </tr>
                                ) : (
                                    kadun.data.map((item, index) => (
                                        <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="py-3 px-4 text-sm text-slate-600">
                                                {kadun.from + index}
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
                                                    <span className="text-sm font-medium text-slate-900">{item.nama}</span>
                                                )}
                                                {editErrors.nama && editingId === item.id && (
                                                    <p className="text-sm text-rose-600 mt-1">{editErrors.nama}</p>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                {editingId === item.id ? (
                                                    <input
                                                        type="text"
                                                        value={editData.kod_dun}
                                                        onChange={(e) => setEditData('kod_dun', e.target.value)}
                                                        className="w-full px-3 py-1.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                        placeholder="Contoh: N01"
                                                    />
                                                ) : (
                                                    <span className="text-sm text-slate-600">{item.kod_dun || '-'}</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                {editingId === item.id ? (
                                                    <select
                                                        value={editData.bandar_id}
                                                        onChange={(e) => setEditData('bandar_id', e.target.value)}
                                                        className="w-full px-3 py-1.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                    >
                                                        <option value="">Pilih Parlimen</option>
                                                        {bandarList.map((bandar) => (
                                                            <option key={bandar.id} value={bandar.id}>
                                                                {bandar.nama} ({bandar.negeri?.nama})
                                                            </option>
                                                        ))}
                                                    </select>
                                                ) : (
                                                    <div className="flex items-center space-x-2">
                                                        <Building2 className="h-4 w-4 text-slate-400" />
                                                        <span className="text-sm text-slate-600">{item.bandar?.nama}</span>
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center space-x-2">
                                                    <MapPin className="h-4 w-4 text-slate-400" />
                                                    <span className="text-sm text-slate-600">{item.bandar?.negeri?.nama}</span>
                                                </div>
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
                    {kadun.last_page > 1 && (
                        <div className="border-t border-slate-200 px-4 py-3 flex items-center justify-between">
                            <div className="text-sm text-slate-600">
                                Menunjukkan {kadun.from} hingga {kadun.to} daripada {kadun.total} rekod
                            </div>
                            <div className="flex items-center space-x-2">
                                {kadun.links.map((link, index) => (
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
                            <h2 className="text-xl font-bold text-slate-900">Tambah KADUN</h2>
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
                                    Nama KADUN <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={addData.nama}
                                    onChange={(e) => setAddData('nama', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    placeholder="Contoh: Sungai Tua"
                                    autoFocus
                                />
                                {addErrors.nama && <p className="text-sm text-rose-600 mt-1">{addErrors.nama}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Kod DUN
                                </label>
                                <input
                                    type="text"
                                    value={addData.kod_dun}
                                    onChange={(e) => setAddData('kod_dun', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    placeholder="Contoh: N16"
                                />
                                {addErrors.kod_dun && <p className="text-sm text-rose-600 mt-1">{addErrors.kod_dun}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Parlimen (Bandar) <span className="text-rose-500">*</span>
                                </label>
                                <select
                                    value={addData.bandar_id}
                                    onChange={(e) => setAddData('bandar_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Pilih Parlimen</option>
                                    {bandarList.map((bandar) => (
                                        <option key={bandar.id} value={bandar.id}>
                                            {bandar.nama} ({bandar.negeri?.nama})
                                        </option>
                                    ))}
                                </select>
                                {addErrors.bandar_id && <p className="text-sm text-rose-600 mt-1">{addErrors.bandar_id}</p>}
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
