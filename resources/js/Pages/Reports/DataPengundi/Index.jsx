import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import useDragScroll from '@/Hooks/useDragScroll';
import {
    Search,
    Calendar,
    Download,
    Plus,
    Edit,
    Trash2,
    X,
    Filter,
    ChevronDown,
    Eye
} from 'lucide-react';

export default function Index({ dataPengundi, filters, currentUserId }) {
    const { auth } = usePage().props;
    const user = auth.user;
    const [selectedItems, setSelectedItems] = useState([]);
    const [showFilters, setShowFilters] = useState(false);
    const [viewingItem, setViewingItem] = useState(null);
    const scrollRef = useDragScroll();

    // Helper to check if user can modify a record
    const canModifyRecord = (item) => {
        if (user.role === 'super_admin') return true;
        if (user.role === 'admin') return item.bandar === user.bandar?.nama;
        if (user.role === 'user') return item.submitted_by?.id === user.id && item.kadun === user.kadun?.nama;
        return false;
    };

    const ownItemsOnPage = dataPengundi.data.filter(canModifyRecord);

    const { data, setData, get, processing } = useForm({
        search: filters.search || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });

    const handleFilter = (e) => {
        e.preventDefault();
        get(route('reports.data-pengundi.index'), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleExport = () => {
        const params = new URLSearchParams({
            search: data.search,
            date_from: data.date_from,
            date_to: data.date_to,
        }).toString();

        window.location.href = route('reports.data-pengundi.export') + '?' + params;
    };

    const handleSelectAll = (e) => {
        if (e.target.checked) {
            setSelectedItems(ownItemsOnPage.map(item => item.id));
        } else {
            setSelectedItems([]);
        }
    };

    const handleSelectItem = (id) => {
        if (selectedItems.includes(id)) {
            setSelectedItems(selectedItems.filter(itemId => itemId !== id));
        } else {
            setSelectedItems([...selectedItems, id]);
        }
    };

    const handleDelete = (id) => {
        if (confirm('Adakah anda pasti mahu memadam rekod ini?')) {
            router.delete(route('reports.data-pengundi.destroy', id));
        }
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('ms-MY', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Data Pengundi" />

            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Data Pengundi</h1>
                        <p className="text-sm text-slate-600 mt-1">
                            Jumlah: {dataPengundi.total} rekod
                        </p>
                    </div>
                    <div className="flex items-center space-x-3">
                        <button
                            onClick={handleExport}
                            className="flex items-center space-x-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                        >
                            <Download className="h-4 w-4" />
                            <span>Export Excel</span>
                        </button>
                        <button
                            onClick={() => router.visit(route('reports.data-pengundi.create'))}
                            className="flex items-center space-x-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Tambah Baru</span>
                        </button>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="font-semibold text-slate-900 flex items-center space-x-2">
                            <Filter className="h-5 w-5" />
                            <span>Penapis</span>
                        </h3>
                        <button
                            onClick={() => setShowFilters(!showFilters)}
                            className="text-sm text-slate-600 hover:text-slate-900 flex items-center space-x-1"
                        >
                            <span>{showFilters ? 'Sembunyikan' : 'Tunjukkan'}</span>
                            <ChevronDown className={`h-4 w-4 transition-transform ${showFilters ? 'rotate-180' : ''}`} />
                        </button>
                    </div>

                    {showFilters && (
                        <form onSubmit={handleFilter} className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Carian
                                </label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                                    <input
                                        type="text"
                                        value={data.search}
                                        onChange={(e) => setData('search', e.target.value)}
                                        placeholder="Nama, No. IC, No. Tel"
                                        className="w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Tarikh Dari
                                </label>
                                <div className="relative">
                                    <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                                    <input
                                        type="date"
                                        value={data.date_from}
                                        onChange={(e) => setData('date_from', e.target.value)}
                                        className="w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Tarikh Hingga
                                </label>
                                <div className="relative">
                                    <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                                    <input
                                        type="date"
                                        value={data.date_to}
                                        onChange={(e) => setData('date_to', e.target.value)}
                                        className="w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    />
                                </div>
                            </div>

                            <div className="flex items-end space-x-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                                >
                                    Tapis
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setData({ search: '', date_from: '', date_to: '' });
                                        router.visit(route('reports.data-pengundi.index'));
                                    }}
                                    className="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                                >
                                    Reset
                                </button>
                            </div>
                        </form>
                    )}
                </div>

                {/* Data Table */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div ref={scrollRef} className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th className="text-left py-3 px-4 w-12">
                                        <input
                                            type="checkbox"
                                            checked={ownItemsOnPage.length > 0 && ownItemsOnPage.every(item => selectedItems.includes(item.id))}
                                            onChange={handleSelectAll}
                                            className="rounded border-slate-300 disabled:opacity-50"
                                            disabled={ownItemsOnPage.length === 0}
                                        />
                                    </th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Nama</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">No. IC</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Umur</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">No. Tel</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Bangsa</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Hubungan</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Negeri</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Parlimen</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">KADUN</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Lokaliti</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Keahlian Parti</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Tarikh</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Dikemukakan</th>
                                    <th className="text-right py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {dataPengundi.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="15" className="py-8 text-center text-slate-500">
                                            Tiada rekod dijumpai
                                        </td>
                                    </tr>
                                ) : (
                                    dataPengundi.data.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="py-3 px-4">
                                                {canModifyRecord(item) && (
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedItems.includes(item.id)}
                                                        onChange={() => handleSelectItem(item.id)}
                                                        className="rounded border-slate-300"
                                                    />
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-sm font-medium text-slate-900">
                                                <button
                                                    onClick={() => setViewingItem(item)}
                                                    className="hover:text-sky-600 hover:underline text-left"
                                                >
                                                    {item.nama}
                                                </button>
                                            </td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.no_ic}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.umur}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.no_tel}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.bangsa}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.hubungan || '-'}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.negeri}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.parlimen}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.kadun}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.lokaliti || '—'}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.keahlian_parti || '-'}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{formatDate(item.created_at)}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.submitted_by?.name || '-'}</td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center justify-end space-x-2">
                                                    {canModifyRecord(item) ? (
                                                        <>
                                                            <button
                                                                onClick={() => router.visit(route('reports.data-pengundi.edit', item.id))}
                                                                className="p-2 text-slate-600 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors"
                                                                title="Edit"
                                                            >
                                                                <Edit className="h-4 w-4" />
                                                            </button>
                                                            <button
                                                                onClick={() => handleDelete(item.id)}
                                                                className="p-2 text-slate-600 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                                                title="Padam"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </button>
                                                        </>
                                                    ) : (
                                                        <button
                                                            onClick={() => setViewingItem(item)}
                                                            className="p-2 text-slate-600 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors"
                                                            title="Lihat"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {dataPengundi.links && dataPengundi.links.length > 3 && (
                        <div className="border-t border-slate-200 px-6 py-4">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-slate-600">
                                    Menunjukkan {dataPengundi.from} hingga {dataPengundi.to} daripada {dataPengundi.total} rekod
                                </p>
                                <div className="flex items-center space-x-2">
                                    {dataPengundi.links.map((link, index) => (
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

                {/* Selected Items Actions */}
                {selectedItems.length > 0 && (
                    <div className="fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900 text-white rounded-xl shadow-xl px-6 py-4 flex items-center space-x-4">
                        <span className="text-sm font-medium">
                            {selectedItems.length} item dipilih
                        </span>
                        <button
                            onClick={() => {
                                if (confirm(`Padam ${selectedItems.length} item?`)) {
                                    router.post(route('reports.data-pengundi.bulk-delete'), {
                                        ids: selectedItems
                                    });
                                }
                            }}
                            className="flex items-center space-x-2 px-4 py-2 bg-rose-600 rounded-lg hover:bg-rose-700 transition-colors"
                        >
                            <Trash2 className="h-4 w-4" />
                            <span>Padam Semua</span>
                        </button>
                        <button
                            onClick={() => setSelectedItems([])}
                            className="p-2 hover:bg-slate-800 rounded-lg transition-colors"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                )}
            </div>

            {/* View Modal */}
            <Modal show={!!viewingItem} onClose={() => setViewingItem(null)}>
                <div className="p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-lg font-medium text-slate-900">
                            Maklumat Pengundi
                        </h2>
                        <button
                            onClick={() => setViewingItem(null)}
                            className="text-slate-400 hover:text-slate-500"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    {viewingItem && (
                        <div className="space-y-6 max-h-[70vh] overflow-y-auto pr-2">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Nama</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.nama}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">No. Kad Pengenalan</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.no_ic}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Umur</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.umur}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">No. Telefon</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.no_tel}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Bangsa</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.bangsa}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Hubungan</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.hubungan || '-'}</div>
                                </div>
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-slate-500">Alamat</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.alamat}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Poskod</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.poskod}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Bandar</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.bandar}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Negeri</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.negeri}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Parlimen</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.parlimen}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">KADUN</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.kadun}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Pusat Mengundi</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.pusat_mengundi || '-'}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Saluran</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.saluran || '-'}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Keahlian Parti</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.keahlian_parti || '-'}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Kecenderungan Politik</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.kecenderungan_politik || '-'}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Nota</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.nota || '-'}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Dikemukakan Oleh</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.submitted_by?.name || '-'}</div>
                                </div>
                            </div>

                            <div className="flex justify-end pt-6 border-t border-slate-100">
                                <SecondaryButton onClick={() => setViewingItem(null)}>
                                    Tutup
                                </SecondaryButton>
                            </div>
                        </div>
                    )}
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
