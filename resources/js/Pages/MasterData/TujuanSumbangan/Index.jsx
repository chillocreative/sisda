import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    Search,
    Plus,
    Edit,
    Trash2,
    X,
    Gift,
    Check,
    GripVertical
} from 'lucide-react';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { SortableTableRow } from '@/Components/SortableTableRow';
import axios from 'axios';

export default function Index({ tujuanSumbangan, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [editingId, setEditingId] = useState(null);
    const [showAddModal, setShowAddModal] = useState(false);
    const [items, setItems] = useState(tujuanSumbangan.data);

    useEffect(() => {
        setItems(tujuanSumbangan.data);
    }, [tujuanSumbangan.data]);

    const { data: addData, setData: setAddData, post, processing: addProcessing, errors: addErrors, reset: resetAdd } = useForm({
        nama: '',
    });

    const { data: editData, setData: setEditData, put, processing: editProcessing, errors: editErrors, reset: resetEdit } = useForm({
        nama: '',
    });

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const handleDragEnd = (event) => {
        const { active, over } = event;

        if (active.id !== over.id) {
            setItems((items) => {
                const oldIndex = items.findIndex((item) => item.id === active.id);
                const newIndex = items.findIndex((item) => item.id === over.id);

                const newItems = arrayMove(items, oldIndex, newIndex);

                const startOrder = tujuanSumbangan.from || 1;
                const itemsWithOrder = newItems.map((item, index) => ({
                    id: item.id,
                    sort_order: startOrder + index
                }));

                axios.post(route('master-data.reorder'), {
                    model: 'TujuanSumbangan',
                    items: itemsWithOrder
                }).catch(error => {
                    console.error('Reorder failed', error);
                });

                return newItems;
            });
        }
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('master-data.tujuan-sumbangan.index'), { search }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleAdd = (e) => {
        e.preventDefault();
        post(route('master-data.tujuan-sumbangan.store'), {
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
        });
    };

    const handleUpdate = (e, id) => {
        e.preventDefault();
        put(route('master-data.tujuan-sumbangan.update', id), {
            onSuccess: () => {
                resetEdit();
                setEditingId(null);
            },
        });
    };

    const handleDelete = (id) => {
        if (confirm('Adakah anda pasti mahu memadam Tujuan Sumbangan ini?')) {
            router.delete(route('master-data.tujuan-sumbangan.destroy', id));
        }
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        resetEdit();
    };

    return (
        <AuthenticatedLayout>
            <Head title="Tujuan Sumbangan" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Tujuan Sumbangan</h1>
                        <p className="text-sm text-slate-600 mt-1">Urus senarai tujuan sumbangan</p>
                    </div>
                    <button
                        onClick={() => setShowAddModal(true)}
                        className="flex items-center space-x-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Tambah Tujuan</span>
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
                                placeholder="Cari Tujuan Sumbangan..."
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
                                    router.get(route('master-data.tujuan-sumbangan.index'));
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
                        <DndContext
                            sensors={sensors}
                            collisionDetection={closestCenter}
                            onDragEnd={handleDragEnd}
                        >
                            <table className="w-full">
                                <thead className="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th className="w-10"></th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase w-16">#</th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Nama</th>
                                        <th className="text-right py-3 px-4 text-xs font-semibold text-slate-600 uppercase w-32">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    <SortableContext
                                        items={items}
                                        strategy={verticalListSortingStrategy}
                                    >
                                        {items.length === 0 ? (
                                            <tr>
                                                <td colSpan="4" className="py-8 text-center text-slate-500">
                                                    Tiada rekod dijumpai
                                                </td>
                                            </tr>
                                        ) : (
                                            items.map((item, index) => (
                                                <SortableTableRow key={item.id} id={item.id} className="hover:bg-slate-50 transition-colors">
                                                    {(attributes, listeners) => (
                                                        <>
                                                            <td className="py-3 px-4 w-10">
                                                                <button
                                                                    {...attributes}
                                                                    {...listeners}
                                                                    className="p-1 text-slate-400 hover:text-slate-600 cursor-grab active:cursor-grabbing"
                                                                >
                                                                    <GripVertical className="h-4 w-4" />
                                                                </button>
                                                            </td>
                                                            <td className="py-3 px-4 text-sm text-slate-600">
                                                                {(tujuanSumbangan.from || 1) + index}
                                                            </td>
                                                            <td className="py-3 px-4">
                                                                {editingId === item.id ? (
                                                                    <div className="space-y-1">
                                                                        <input
                                                                            type="text"
                                                                            value={editData.nama}
                                                                            onChange={(e) => setEditData('nama', e.target.value)}
                                                                            className="w-full px-3 py-1.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                                            autoFocus
                                                                        />
                                                                        {editErrors.nama && (
                                                                            <p className="text-sm text-rose-600">{editErrors.nama}</p>
                                                                        )}
                                                                    </div>
                                                                ) : (
                                                                    <div className="flex items-center space-x-2">
                                                                        <Gift className="h-4 w-4 text-slate-400" />
                                                                        <span className="text-sm font-medium text-slate-900">{item.nama}</span>
                                                                    </div>
                                                                )}
                                                            </td>
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
                                                        </>
                                                    )}
                                                </SortableTableRow>
                                            ))
                                        )}
                                    </SortableContext>
                                </tbody>
                            </table>
                        </DndContext>
                    </div>

                    {/* Pagination */}
                    {tujuanSumbangan.last_page > 1 && (
                        <div className="border-t border-slate-200 px-4 py-3 flex items-center justify-between">
                            <div className="text-sm text-slate-600">
                                Menunjukkan {tujuanSumbangan.from} hingga {tujuanSumbangan.to} daripada {tujuanSumbangan.total} rekod
                            </div>
                            <div className="flex items-center space-x-2">
                                {tujuanSumbangan.links.map((link, index) => (
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
                            <h2 className="text-xl font-bold text-slate-900">Tambah Tujuan Sumbangan</h2>
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
                                    Nama Tujuan <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={addData.nama}
                                    onChange={(e) => setAddData('nama', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    placeholder="Contoh: Kematian"
                                    autoFocus
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
