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
    Eye,
    FileDown
} from 'lucide-react';

export default function Index({ hasilCulaan, filters, currentUserId }) {
    const { auth } = usePage().props;
    const user = auth.user;
    const [selectedItems, setSelectedItems] = useState([]);
    const [showFilters, setShowFilters] = useState(false);
    const [viewingItem, setViewingItem] = useState(null);
    const [viewingImage, setViewingImage] = useState(null);
    const scrollRef = useDragScroll();

    // Helper to check if user can modify a record
    const canModifyRecord = (item) => {
        if (user.role === 'super_admin') return true;
        if (user.role === 'admin') return item.bandar === user.bandar?.nama;
        if (user.role === 'user') return item.submitted_by?.id === user.id && item.kadun === user.kadun?.nama;
        return false;
    };

    const ownItemsOnPage = hasilCulaan.data.filter(canModifyRecord);

    const { data, setData, get, processing } = useForm({
        search: filters.search || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });

    const handleFilter = (e) => {
        e.preventDefault();
        get(route('reports.hasil-culaan.index'), {
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

        window.location.href = route('reports.hasil-culaan.export') + '?' + params;
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
            router.delete(route('reports.hasil-culaan.destroy', id));
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

    const formatCurrency = (amount) => {
        return 'RM ' + parseFloat(amount).toLocaleString('ms-MY', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Hasil Culaan" />

            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Hasil Culaan</h1>
                        <p className="text-sm text-slate-600 mt-1">
                            Jumlah: {hasilCulaan.total} rekod
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
                            onClick={() => router.visit(route('reports.hasil-culaan.create'))}
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
                                        router.visit(route('reports.hasil-culaan.index'));
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
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Negeri</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Bandar</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Lokaliti</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Pendapatan</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Tarikh</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Dikemukakan</th>
                                    <th className="text-center py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Kad Pengenalan</th>
                                    <th className="text-right py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {hasilCulaan.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="14" className="py-8 text-center text-slate-500">
                                            Tiada rekod dijumpai
                                        </td>
                                    </tr>
                                ) : (
                                    hasilCulaan.data.map((item) => (
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
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.negeri}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.bandar}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.lokaliti || '—'}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{formatCurrency(item.pendapatan_isi_rumah)}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{formatDate(item.created_at)}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.submitted_by?.name || '-'}</td>
                                            <td className="py-3 px-4">
                                                {item.kad_pengenalan ? (
                                                    <div className="flex items-center justify-center space-x-2">
                                                        <a
                                                            href={`/storage/${item.kad_pengenalan}`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="p-2 text-slate-600 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors"
                                                            title="Lihat"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </a>
                                                        <a
                                                            href={`/storage/${item.kad_pengenalan}`}
                                                            download
                                                            className="p-2 text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                                            title="Muat Turun"
                                                        >
                                                            <FileDown className="h-4 w-4" />
                                                        </a>
                                                    </div>
                                                ) : (
                                                    <div className="text-center text-slate-400 text-xs">
                                                        Tiada
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center justify-end space-x-2">
                                                    {canModifyRecord(item) ? (
                                                        <>
                                                            <button
                                                                onClick={() => router.visit(route('reports.hasil-culaan.edit', item.id))}
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
                    {hasilCulaan.links && hasilCulaan.links.length > 3 && (
                        <div className="border-t border-slate-200 px-6 py-4">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-slate-600">
                                    Menunjukkan {hasilCulaan.from} hingga {hasilCulaan.to} daripada {hasilCulaan.total} rekod
                                </p>
                                <div className="flex items-center space-x-2">
                                    {hasilCulaan.links.map((link, index) => (
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
                {
                    selectedItems.length > 0 && (
                        <div className="fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900 text-white rounded-xl shadow-xl px-6 py-4 flex items-center space-x-4">
                            <span className="text-sm font-medium">
                                {selectedItems.length} item dipilih
                            </span>
                            <button
                                onClick={() => {
                                    if (confirm(`Padam ${selectedItems.length} item?`)) {
                                        router.post(route('reports.hasil-culaan.bulk-delete'), {
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
                    )
                }
            </div >

            {/* View Modal */}
            < Modal show={!!viewingItem
            } onClose={() => setViewingItem(null)}>
                <div className="p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-lg font-medium text-slate-900">
                            Maklumat Hasil Culaan
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
                                    <label className="block text-sm font-medium text-slate-500">KADUN</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.kadun}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Bil Isi Rumah</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.bil_isi_rumah}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Pendapatan Isi Rumah</label>
                                    <div className="mt-1 text-slate-900">{formatCurrency(viewingItem.pendapatan_isi_rumah)}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Pekerjaan</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.pekerjaan || '-'}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Jenis Pekerjaan</label>
                                    <div className="mt-1 text-slate-900">
                                        {viewingItem.jenis_pekerjaan === 'Lain-lain' && viewingItem.jenis_pekerjaan_lain
                                            ? viewingItem.jenis_pekerjaan_lain
                                            : viewingItem.jenis_pekerjaan || '-'}
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Pemilik Rumah</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.pemilik_rumah || '-'}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Jenis Sumbangan</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.jenis_sumbangan || '-'}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Tujuan Sumbangan</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.tujuan_sumbangan || '-'}</div>
                                </div>
                                {viewingItem.bantuan_lain && viewingItem.bantuan_lain.includes('ZAKAT PULAU PINANG') && (
                                    <div>
                                        <label className="block text-sm font-medium text-slate-500">Jenis Bantuan ZPP</label>
                                        <div className="mt-1 text-slate-900">{viewingItem.zpp_jenis_bantuan || '-'}</div>
                                    </div>
                                )}
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Dikemukakan Oleh</label>
                                    <div className="mt-1 text-slate-900">{viewingItem.submitted_by?.name || '-'}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-500">Kad Pengenalan (Lampiran)</label>
                                    {viewingItem.kad_pengenalan ? (
                                        <div className="mt-1 flex items-center space-x-2">
                                            <button
                                                onClick={() => setViewingImage(`/storage/${viewingItem.kad_pengenalan}`)}
                                                className="text-sky-600 hover:text-sky-700 hover:underline flex items-center space-x-1"
                                            >
                                                <Eye className="h-4 w-4" />
                                                <span>Lihat</span>
                                            </button>
                                            <a
                                                href={`/storage/${viewingItem.kad_pengenalan}`}
                                                download
                                                className="text-emerald-600 hover:text-emerald-700 hover:underline flex items-center space-x-1"
                                            >
                                                <FileDown className="h-4 w-4" />
                                                <span>Muat Turun</span>
                                            </a>
                                        </div>
                                    ) : (
                                        <div className="mt-1 text-slate-400">Tiada</div>
                                    )}
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
            </Modal >

            {/* Image Modal */}
            < Modal show={!!viewingImage} onClose={() => setViewingImage(null)} maxWidth="4xl" >
                <div className="relative p-4">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-lg font-medium text-slate-900">Lampiran Kad Pengenalan</h3>
                        <div className="flex items-center space-x-2">
                            <a
                                href={viewingImage}
                                download
                                className="p-2 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                title="Muat Turun"
                            >
                                <Download className="h-5 w-5" />
                            </a>
                            <button
                                onClick={() => setViewingImage(null)}
                                className="p-2 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                title="Tutup"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                    <div className="flex items-center justify-center bg-slate-100 rounded-lg p-2">
                        <img
                            src={viewingImage}
                            alt="Kad Pengenalan"
                            className="max-w-full max-h-[75vh] object-contain rounded"
                            onError={(e) => {
                                e.target.onerror = null;
                                e.target.src = 'https://placehold.co/600x400?text=Imej+Tidak+Dijumpai';
                                e.target.className = "max-w-full max-h-[75vh] object-contain rounded opacity-50";
                            }}
                        />
                    </div>
                </div>
            </Modal >
        </AuthenticatedLayout >
    );
}
