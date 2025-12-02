import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Search, Map, Plus, Edit, Trash2, X } from 'lucide-react';
import Modal from '@/Components/Modal';

export default function Index({ daerahMengundi, filters, auth }) {
    const [search, setSearch] = useState(filters.search || '');
    const [showModal, setShowModal] = useState(false);
    const [editingItem, setEditingItem] = useState(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        kod_dm: '',
        nama: '',
        bandar_id: '',
    });

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('master-data.daerah-mengundi.index'), { search }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        router.get(route('master-data.daerah-mengundi.index'));
    };

    const openAddModal = () => {
        reset();
        setEditingItem(null);
        setShowModal(true);
    };

    const openEditModal = (item) => {
        setEditingItem(item);
        setData({
            kod_dm: item.kod_dm,
            nama: item.nama,
            bandar_id: item.bandar_id,
        });
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingItem(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        if (editingItem) {
            put(route('master-data.daerah-mengundi.update', editingItem.id), {
                onSuccess: () => closeModal(),
            });
        } else {
            post(route('master-data.daerah-mengundi.store'), {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleDelete = (id) => {
        if (confirm('Adakah anda pasti untuk memadam daerah mengundi ini?')) {
            router.delete(route('master-data.daerah-mengundi.destroy', id));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Daerah Mengundi" />

            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Daerah Mengundi</h1>
                        <p className="text-sm text-slate-600 mt-1">
                            Jumlah: {daerahMengundi.total} daerah mengundi
                        </p>
                    </div>
                    <button
                        onClick={openAddModal}
                        className="flex items-center space-x-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Tambah Daerah Mengundi</span>
                    </button>
                </div>

                {/* Search */}
                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <form onSubmit={handleSearch} className="flex items-center space-x-4">
                        <div className="flex-1 relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari kod atau nama daerah mengundi..."
                                className="w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            />
                        </div>
                        <button
                            type="submit"
                            className="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                        >
                            Cari
                        </button>
                        {filters.search && (
                            <button
                                type="button"
                                onClick={handleReset}
                                className="px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                            >
                                Reset
                            </button>
                        )}
                    </form>
                </div>

                {/* Data Table */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Bil</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Kod DM</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Nama Daerah Mengundi</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Parlimen</th>
                                    <th className="text-right py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {daerahMengundi.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="5" className="py-12 text-center">
                                            <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
                                                <Map className="h-8 w-8 text-slate-400" />
                                            </div>
                                            <p className="text-slate-600">Tiada daerah mengundi dijumpai</p>
                                            {filters.search && (
                                                <button
                                                    onClick={handleReset}
                                                    className="mt-4 text-sky-600 hover:text-sky-700 text-sm font-medium"
                                                >
                                                    Papar semua daerah mengundi
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ) : (
                                    daerahMengundi.data.map((item, index) => (
                                        <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="py-3 px-4 text-sm text-slate-600">
                                                {daerahMengundi.from + index}
                                            </td>
                                            <td className="py-3 px-4 text-sm font-medium text-slate-900">
                                                {item.kod_dm}
                                            </td>
                                            <td className="py-3 px-4 text-sm text-slate-900">
                                                <div className="flex items-center space-x-2">
                                                    <Map className="h-4 w-4 text-slate-400" />
                                                    <span>{item.nama}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-sm text-slate-600">
                                                {item.bandar?.nama || '-'}
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center justify-end space-x-2">
                                                    <button
                                                        onClick={() => openEditModal(item)}
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
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {daerahMengundi.links && daerahMengundi.links.length > 3 && (
                        <div className="border-t border-slate-200 px-6 py-4">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-slate-600">
                                    Menunjukkan {daerahMengundi.from} hingga {daerahMengundi.to} daripada {daerahMengundi.total} rekod
                                </p>
                                <div className="flex items-center space-x-2">
                                    {daerahMengundi.links.map((link, index) => (
                                        <button
                                            key={index}
                                            onClick={() => link.url && router.visit(link.url)}
                                            disabled={!link.url}
                                            className={`px-3 py-1 rounded-lg text-sm transition-colors ${link.active
                                                    ? 'bg-slate-900 text-white'
                                                    : link.url
                                                        ? 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                                        : 'bg-slate-50 text-slate-400 cursor-not-allowed'
                                                }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Add/Edit Modal */}
            <Modal show={showModal} onClose={closeModal} maxWidth="md">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-xl font-bold text-slate-900">
                            {editingItem ? 'Edit Daerah Mengundi' : 'Tambah Daerah Mengundi'}
                        </h2>
                        <button
                            onClick={closeModal}
                            className="p-2 hover:bg-slate-100 rounded-lg transition-colors"
                        >
                            <X className="h-5 w-5 text-slate-600" />
                        </button>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                Kod DM <span className="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.kod_dm}
                                onChange={(e) => setData('kod_dm', e.target.value)}
                                placeholder="Contoh: 041/01/02"
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                required
                            />
                            {errors.kod_dm && <p className="text-sm text-rose-600 mt-1">{errors.kod_dm}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                Nama Daerah Mengundi <span className="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.nama}
                                onChange={(e) => setData('nama', e.target.value.toUpperCase())}
                                placeholder="Contoh: PULAU MERTAJAM"
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 uppercase"
                                required
                            />
                            {errors.nama && <p className="text-sm text-rose-600 mt-1">{errors.nama}</p>}
                        </div>

                        <div className="flex items-center justify-end space-x-3 pt-4">
                            <button
                                type="button"
                                onClick={closeModal}
                                className="px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                            >
                                {processing ? 'Menyimpan...' : editingItem ? 'Kemaskini' : 'Simpan'}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
