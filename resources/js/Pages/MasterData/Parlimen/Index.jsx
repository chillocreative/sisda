import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Search,
    Plus,
    Edit,
    Trash2,
    X,
    MapPin,
    Check,
    Landmark
} from 'lucide-react';

export default function Index({ parlimen, negeriList, selectedNegeri, filters }) {
    const user = usePage().props.auth.user;
    const [search, setSearch] = useState(filters.search || '');
    const [negeriFilter, setNegeriFilter] = useState(filters.negeri_id || '');
    const [editingId, setEditingId] = useState(null);
    const [showAddModal, setShowAddModal] = useState(false);

    const { data: addData, setData: setAddData, post, processing: addProcessing, errors: addErrors, reset: resetAdd } = useForm({
        nama: '',
        kod_parlimen: '',
        negeri_id: selectedNegeri?.id || '',
    });

    const { data: editData, setData: setEditData, put, processing: editProcessing, errors: editErrors, reset: resetEdit } = useForm({
        nama: '',
        kod_parlimen: '',
        negeri_id: '',
    });

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('master-data.parlimen.index'), { search, negeri_id: negeriFilter }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleAdd = (e) => {
        e.preventDefault();
        post(route('master-data.parlimen.store'), {
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
            kod_parlimen: item.kod_parlimen || '',
            negeri_id: item.negeri_id,
        });
    };

    const handleUpdate = (e, id) => {
        e.preventDefault();
        put(route('master-data.parlimen.update', id), {
            onSuccess: () => {
                resetEdit();
                setEditingId(null);
            },
        });
    };

    const handleDelete = (id) => {
        if (confirm('Adakah anda pasti mahu memadam parlimen ini?')) {
            router.delete(route('master-data.parlimen.destroy', id));
        }
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        resetEdit();
    };

    return (
        <AuthenticatedLayout>
            <Head title={selectedNegeri ? `Parlimen - ${selectedNegeri.nama}` : 'Parlimen'} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Parlimen</h1>
                        <p className="text-sm text-slate-600 mt-1">Urus senarai parlimen</p>
                    </div>
                    {user.role === 'super_admin' && (
                        <button
                            onClick={() => setShowAddModal(true)}
                            className="flex items-center space-x-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Tambah Parlimen</span>
                        </button>
                    )}
                </div>

                {/* Search & Filter */}
                <div className="bg-white rounded-xl border border-slate-200 p-4">
                    <form onSubmit={handleSearch} className="space-y-3">
                        <div className="flex items-center space-x-2">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari parlimen atau kod..."
                                    className="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                />
                            </div>
                            <select
                                value={negeriFilter}
                                onChange={(e) => setNegeriFilter(e.target.value)}
                                className="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            >
                                <option value="">Semua Negeri</option>
                                {negeriList.map((negeri) => (
                                    <option key={negeri.id} value={negeri.id}>{negeri.nama}</option>
                                ))}
                            </select>
                            <button
                                type="submit"
                                className="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                            >
                                Cari
                            </button>
                            {(search || negeriFilter) && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearch('');
                                        setNegeriFilter('');
                                        router.get(route('master-data.parlimen.index'));
                                    }}
                                    className="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                                >
                                    Reset
                                </button>
                            )}
                        </div>
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase w-16">Bil</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Negeri</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Parlimen</th>
                                    {user.role === 'super_admin' && (
                                        <th className="text-right py-3 px-4 text-xs font-semibold text-slate-600 uppercase w-32">Tindakan</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {parlimen.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={user.role === 'super_admin' ? "4" : "3"} className="py-8 text-center text-slate-500">
                                            Tiada rekod dijumpai
                                        </td>
                                    </tr>
                                ) : (
                                    parlimen.data.map((item, index) => (
                                        <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="py-3 px-4 text-sm text-slate-600">
                                                {parlimen.from + index}
                                            </td>
                                            <td className="py-3 px-4">
                                                {editingId === item.id ? (
                                                    <select
                                                        value={editData.negeri_id}
                                                        onChange={(e) => setEditData('negeri_id', e.target.value)}
                                                        className="w-full px-3 py-1.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                    >
                                                        <option value="">Pilih Negeri</option>
                                                        {negeriList.map((negeri) => (
                                                            <option key={negeri.id} value={negeri.id}>{negeri.nama}</option>
                                                        ))}
                                                    </select>
                                                ) : (
                                                    <div className="flex items-center space-x-2">
                                                        <MapPin className="h-4 w-4 text-slate-400" />
                                                        <span className="text-sm text-slate-600">{item.negeri?.nama}</span>
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                {editingId === item.id ? (
                                                    <div className="flex space-x-2">
                                                        <input
                                                            type="text"
                                                            value={editData.kod_parlimen}
                                                            onChange={(e) => setEditData('kod_parlimen', e.target.value)}
                                                            className="w-24 px-3 py-1.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                            placeholder="Kod"
                                                        />
                                                        <input
                                                            type="text"
                                                            value={editData.nama}
                                                            onChange={(e) => setEditData('nama', e.target.value)}
                                                            className="flex-1 px-3 py-1.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                            placeholder="Nama Parlimen"
                                                        />
                                                    </div>
                                                ) : (
                                                    <div className="flex items-center space-x-2">
                                                        <Landmark className="h-4 w-4 text-slate-400" />
                                                        <span className="text-sm font-medium text-slate-900">
                                                            {item.kod_parlimen ? `${item.kod_parlimen} - ${item.nama}` : item.nama}
                                                        </span>
                                                    </div>
                                                )}
                                                {editErrors.nama && editingId === item.id && (
                                                    <p className="text-sm text-rose-600 mt-1">{editErrors.nama}</p>
                                                )}
                                            </td>
                                            {user.role === 'super_admin' && (
                                                <td className="py-3 px-4">
                                                    {editingId === item.id ? (
                                                        <div className="flex items-center justify-end space-x-2">
                                                            <button
                                                                onClick={(e) => handleUpdate(e, item.id)}
                                                                disabled={editProcessing}
                                                                className="p-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition-colors disabled:opacity-50"
                                                                title="Simpan"
                                                            >
                                                                <Check className="h-4 w-4" />
                                                            </button>
                                                            <button
                                                                onClick={handleCancelEdit}
                                                                className="p-2 bg-slate-200 text-slate-600 rounded-lg hover:bg-slate-300 transition-colors"
                                                                title="Batal"
                                                            >
                                                                <X className="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <div className="flex items-center justify-end space-x-2">
                                                            <button
                                                                onClick={() => handleEdit(item)}
                                                                className="p-2 bg-amber-400 text-white rounded-lg hover:bg-amber-500 transition-colors"
                                                                title="Kemaskini"
                                                            >
                                                                <Edit className="h-4 w-4" />
                                                            </button>
                                                            <button
                                                                onClick={() => handleDelete(item.id)}
                                                                className="p-2 bg-rose-500 text-white rounded-lg hover:bg-rose-600 transition-colors"
                                                                title="Padam"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {parlimen.last_page > 1 && (
                        <div className="border-t border-slate-200 px-4 py-3 flex items-center justify-between">
                            <div className="text-sm text-slate-600">
                                Menunjukkan {parlimen.from} hingga {parlimen.to} daripada {parlimen.total} rekod
                            </div>
                            <div className="flex items-center space-x-2">
                                {parlimen.links.map((link, index) => (
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
                            <h2 className="text-xl font-bold text-slate-900">Tambah Parlimen</h2>
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
                                    Negeri <span className="text-rose-500">*</span>
                                </label>
                                <select
                                    value={addData.negeri_id}
                                    onChange={(e) => setAddData('negeri_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Pilih Negeri</option>
                                    {negeriList.map((negeri) => (
                                        <option key={negeri.id} value={negeri.id}>{negeri.nama}</option>
                                    ))}
                                </select>
                                {addErrors.negeri_id && <p className="text-sm text-rose-600 mt-1">{addErrors.negeri_id}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Kod Parlimen
                                </label>
                                <input
                                    type="text"
                                    value={addData.kod_parlimen}
                                    onChange={(e) => setAddData('kod_parlimen', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    placeholder="Contoh: P001"
                                />
                                {addErrors.kod_parlimen && <p className="text-sm text-rose-600 mt-1">{addErrors.kod_parlimen}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Nama Parlimen <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={addData.nama}
                                    onChange={(e) => setAddData('nama', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    placeholder="Contoh: Kepala Batas"
                                />
                                {addErrors.nama && <p className="text-sm text-rose-600 mt-1">{addErrors.nama}</p>}
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
