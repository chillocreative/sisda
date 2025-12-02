import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    Search,
    Plus,
    Edit,
    Trash2,
    X,
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

export default function Index({ bangsa, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [editingId, setEditingId] = useState(null);
    const [showAddModal, setShowAddModal] = useState(false);
    const [items, setItems] = useState(bangsa.data);

    useEffect(() => {
        setItems(bangsa.data);
    }, [bangsa.data]);

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

                // Calculate new sort orders based on global index (accounting for pagination)
                const startOrder = bangsa.from || 1;
                const itemsWithOrder = newItems.map((item, index) => ({
                    id: item.id,
                    sort_order: startOrder + index
                }));

                // Send update to backend
                axios.post(route('master-data.reorder'), {
                    model: 'Bangsa',
                    items: itemsWithOrder
                }).catch(error => {
                    console.error('Reorder failed', error);
                    // Optional: Show error notification
                });

                return newItems;
            });
        }
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('master-data.bangsa.index'), { search }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleAdd = (e) => {
        e.preventDefault();
        post(route('master-data.bangsa.store'), {
            onSuccess: () => {
                resetAdd();
                setShowAddModal(false);
            },
        });
    };

    const handleEdit = (item) => {
        setEditingId(item.id);
        setEditData('nama', item.nama);
    };

    const handleUpdate = (e, id) => {
        e.preventDefault();
        put(route('master-data.bangsa.update', id), {
            onSuccess: () => {
                resetEdit();
                setEditingId(null);
            },
        });
    };

    const handleDelete = (id) => {
        if (confirm('Adakah anda pasti mahu memadam bangsa ini?')) {
            router.delete(route('master-data.bangsa.destroy', id));
        }
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        resetEdit();
    };

    return (
        <AuthenticatedLayout>
            <Head title="Bangsa" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Bangsa</h1>
                        <p className="text-sm text-slate-600 mt-1">Urus senarai bangsa</p>
                    </div>
                    <button
                        onClick={() => setShowAddModal(true)}
                        className="flex items-center space-x-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Tambah Bangsa</span>
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
                                placeholder="Cari bangsa..."
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
                                    router.get(route('master-data.bangsa.index'));
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
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase w-16">Bil</th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Nama Bangsa</th>
                                        <th className="text-right py-3 px-4 text-xs font-semibold text-slate-600 uppercase w-32">Tindakan</th>
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
                                                                {(bangsa.from || 1) + index}
                                                            </td>
                                                            <td className="py-3 px-4">
                                                                {editingId === item.id ? (
                                                                    <form onSubmit={(e) => handleUpdate(e, item.id)} className="flex items-center space-x-2">
                                                                        <input
                                                                            type="text"
                                                                            value={editData.nama}
                                                                            onChange={(e) => setEditData('nama', e.target.value)}
                                                                            className="flex-1 px-3 py-1.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                                            autoFocus
                                                                        />
                                                                        <button
                                                                            type="submit"
                                                                            disabled={editProcessing}
                                                                            className="px-3 py-1.5 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                                                                        >
                                                                            Simpan
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onClick={handleCancelEdit}
                                                                            className="px-3 py-1.5 border border-slate-300 text-slate-700 text-sm rounded-lg hover:bg-slate-50 transition-colors"
                                                                        >
                                                                            Batal
                                                                        </button>
                                                                    </form>
                                                                ) : (
                                                                    <span className="text-sm font-medium text-slate-900">{item.nama}</span>
                                                                )}
                                                                {editErrors.nama && editingId === item.id && (
                                                                    <p className="text-sm text-rose-600 mt-1">{editErrors.nama}</p>
                                                                )}
                                                            </td>
                                                            <td className="py-3 px-4">
                                                                {editingId !== item.id && (
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
                    {bangsa.last_page > 1 && (
                        <div className="border-t border-slate-200 px-4 py-3 flex items-center justify-between">
                            <div className="text-sm text-slate-600">
                                Menunjukkan {bangsa.from} hingga {bangsa.to} daripada {bangsa.total} rekod
                            </div>
                            <div className="flex items-center space-x-2">
                                {bangsa.links.map((link, index) => (
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
                            <h2 className="text-xl font-bold text-slate-900">Tambah Bangsa</h2>
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
                                    Nama Bangsa <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={addData.nama}
                                    onChange={(e) => setAddData('nama', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    placeholder="Contoh: Melayu"
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
