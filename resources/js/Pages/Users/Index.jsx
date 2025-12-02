import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Users as UsersIcon, Shield, User as UserIcon, Edit, Trash2, Plus, Search, Filter, X, ChevronDown } from 'lucide-react';
import { useState, useEffect } from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

export default function Index({ users, stats, negeriList, bandarList, kadunList, filters: initialFilters }) {
    const [selectedUsers, setSelectedUsers] = useState([]);
    const [editingUser, setEditingUser] = useState(null);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showFilters, setShowFilters] = useState(false);

    // Filter State
    const [filterData, setFilterData] = useState({
        search: initialFilters.search || '',
        role: initialFilters.role || '',
        status: initialFilters.status || '',
        negeri_id: initialFilters.negeri_id || '',
        bandar_id: initialFilters.bandar_id || '',
        kadun_id: initialFilters.kadun_id || '',
    });

    // Form State for Create/Edit
    const [formData, setFormData] = useState({
        name: '',
        telephone: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'user',
        status: 'pending',
        negeri_id: '',
        bandar_id: '',
        kadun_id: '',
    });

    // Cascading Dropdown State
    const [filteredBandar, setFilteredBandar] = useState([]);
    const [filteredKadun, setFilteredKadun] = useState([]);

    // Reset form when modal closes
    useEffect(() => {
        if (!showCreateModal && !editingUser) {
            setFormData({
                name: '',
                telephone: '',
                email: '',
                password: '',
                password_confirmation: '',
                role: 'user',
                status: 'pending',
                negeri_id: '',
                bandar_id: '',
                kadun_id: '',
            });
        }
    }, [showCreateModal, editingUser]);

    // Populate form when editing
    useEffect(() => {
        if (editingUser) {
            setFormData({
                name: editingUser.name,
                telephone: editingUser.telephone,
                email: editingUser.email || '',
                password: '', // Leave blank to keep existing
                password_confirmation: '',
                role: editingUser.role,
                status: editingUser.status,
                negeri_id: editingUser.negeri_id,
                bandar_id: editingUser.bandar_id,
                kadun_id: editingUser.kadun_id,
            });
        }
    }, [editingUser]);

    // Handle Cascading Dropdowns for Form
    useEffect(() => {
        if (formData.negeri_id) {
            setFilteredBandar(bandarList.filter(b => b.negeri_id == formData.negeri_id));
        } else {
            setFilteredBandar([]);
        }
    }, [formData.negeri_id, bandarList]);

    useEffect(() => {
        if (formData.bandar_id) {
            setFilteredKadun(kadunList.filter(k => k.bandar_id == formData.bandar_id));
        } else {
            setFilteredKadun([]);
        }
    }, [formData.bandar_id, kadunList]);

    // Handle Filter Changes
    const handleFilterChange = (key, value) => {
        setFilterData(prev => ({ ...prev, [key]: value }));
    };

    const applyFilters = () => {
        router.get(route('users.index'), filterData, { preserveState: true });
    };

    const resetFilters = () => {
        setFilterData({
            search: '',
            role: '',
            status: '',
            negeri_id: '',
            bandar_id: '',
            kadun_id: '',
        });
        router.get(route('users.index'));
    };

    // Selection Logic
    const handleSelectAll = (e) => {
        if (e.target.checked) {
            setSelectedUsers(users.map(u => u.id));
        } else {
            setSelectedUsers([]);
        }
    };

    const handleSelectUser = (userId) => {
        if (selectedUsers.includes(userId)) {
            setSelectedUsers(selectedUsers.filter(id => id !== userId));
        } else {
            setSelectedUsers([...selectedUsers, userId]);
        }
    };

    // CRUD Actions
    const handleDelete = (userId) => {
        if (confirm('Adakah anda pasti mahu memadam pengguna ini?')) {
            router.delete(route('users.destroy', userId));
        }
    };

    const handleBulkDelete = () => {
        if (selectedUsers.length === 0) {
            alert('Sila pilih pengguna untuk dipadam');
            return;
        }

        if (confirm(`Adakah anda pasti mahu memadam ${selectedUsers.length} pengguna?`)) {
            router.post(route('users.bulk-delete'), { ids: selectedUsers });
            setSelectedUsers([]);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingUser) {
            router.put(route('users.update', editingUser.id), formData, {
                onSuccess: () => setEditingUser(null),
            });
        } else {
            router.post(route('users.store'), formData, {
                onSuccess: () => setShowCreateModal(false),
            });
        }
    };

    // Helpers
    const getRoleBadgeColor = (role) => {
        switch (role) {
            case 'super_admin': return 'bg-rose-100 text-rose-800';
            case 'admin': return 'bg-sky-100 text-sky-800';
            default: return 'bg-slate-100 text-slate-800';
        }
    };

    const getStatusBadgeColor = (status) => {
        switch (status) {
            case 'approved': return 'bg-emerald-100 text-emerald-800';
            case 'rejected': return 'bg-rose-100 text-rose-800';
            default: return 'bg-amber-100 text-amber-800';
        }
    };

    const getRoleLabel = (role) => {
        switch (role) {
            case 'super_admin': return 'Super Admin';
            case 'admin': return 'Admin';
            default: return 'Pengguna';
        }
    };

    const getStatusLabel = (status) => {
        switch (status) {
            case 'approved': return 'Lulus';
            case 'rejected': return 'Ditolak';
            default: return 'Menunggu';
        }
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'Tidak pernah';
        return new Date(dateString).toLocaleDateString('ms-MY', {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Pengguna" />

            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Pengguna</h1>
                        <p className="text-sm text-slate-600 mt-1">Urus pengguna sistem</p>
                    </div>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="flex items-center space-x-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Tambah Pengguna</span>
                    </button>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <div className="flex items-center justify-between">
                            <div className="p-3 rounded-lg bg-rose-50">
                                <Shield className="h-6 w-6 text-rose-600" />
                            </div>
                        </div>
                        <div className="mt-4">
                            <p className="text-sm font-medium text-slate-600">Super Admin</p>
                            <p className="text-2xl font-bold text-slate-900 mt-1">{stats.super_admin}</p>
                        </div>
                    </div>

                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <div className="flex items-center justify-between">
                            <div className="p-3 rounded-lg bg-sky-50">
                                <UsersIcon className="h-6 w-6 text-sky-600" />
                            </div>
                        </div>
                        <div className="mt-4">
                            <p className="text-sm font-medium text-slate-600">Admin</p>
                            <p className="text-2xl font-bold text-slate-900 mt-1">{stats.admin}</p>
                        </div>
                    </div>

                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <div className="flex items-center justify-between">
                            <div className="p-3 rounded-lg bg-slate-50">
                                <UserIcon className="h-6 w-6 text-slate-600" />
                            </div>
                        </div>
                        <div className="mt-4">
                            <p className="text-sm font-medium text-slate-600">Pengguna</p>
                            <p className="text-2xl font-bold text-slate-900 mt-1">{stats.user}</p>
                        </div>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div className="p-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between cursor-pointer" onClick={() => setShowFilters(!showFilters)}>
                        <div className="flex items-center space-x-2">
                            <Filter className="h-4 w-4 text-slate-600" />
                            <span className="text-sm font-medium text-slate-700">Tapis & Carian</span>
                        </div>
                        <ChevronDown className={`h-4 w-4 text-slate-400 transition-transform duration-200 ${showFilters ? 'transform rotate-180' : ''}`} />
                    </div>

                    {showFilters && (
                        <div className="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <InputLabel value="Carian" />
                                <TextInput
                                    value={filterData.search}
                                    onChange={(e) => handleFilterChange('search', e.target.value)}
                                    placeholder="Nama, Telefon, Emel..."
                                    className="w-full mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel value="Peranan" />
                                <select
                                    value={filterData.role}
                                    onChange={(e) => handleFilterChange('role', e.target.value)}
                                    className="w-full mt-1 border-slate-300 rounded-lg focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Semua</option>
                                    <option value="super_admin">Super Admin</option>
                                    <option value="admin">Admin</option>
                                    <option value="user">Pengguna</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Status" />
                                <select
                                    value={filterData.status}
                                    onChange={(e) => handleFilterChange('status', e.target.value)}
                                    className="w-full mt-1 border-slate-300 rounded-lg focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Semua</option>
                                    <option value="pending">Menunggu</option>
                                    <option value="approved">Lulus</option>
                                    <option value="rejected">Ditolak</option>
                                </select>
                            </div>
                            <div className="md:col-span-3 flex justify-end space-x-2">
                                <button onClick={resetFilters} className="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm">Set Semula</button>
                                <button onClick={applyFilters} className="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 text-sm">Tapis</button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Users Table */}
                <div className="bg-white rounded-xl border border-slate-200">
                    <div className="p-6 border-b border-slate-200">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-slate-900">Senarai Pengguna</h2>
                            {selectedUsers.length > 0 && (
                                <button
                                    onClick={handleBulkDelete}
                                    className="flex items-center space-x-2 px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700 transition-colors"
                                >
                                    <Trash2 className="h-4 w-4" />
                                    <span>Padam Terpilih ({selectedUsers.length})</span>
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th className="text-left py-3 px-6 w-12">
                                        <input
                                            type="checkbox"
                                            checked={selectedUsers.length === users.length && users.length > 0}
                                            onChange={handleSelectAll}
                                            className="rounded border-slate-300 text-slate-900 focus:ring-slate-400"
                                        />
                                    </th>
                                    <th className="text-left py-3 px-6 text-xs font-semibold text-slate-600 uppercase tracking-wider">Nama / Info</th>
                                    <th className="text-left py-3 px-6 text-xs font-semibold text-slate-600 uppercase tracking-wider">Kawasan</th>
                                    <th className="text-left py-3 px-6 text-xs font-semibold text-slate-600 uppercase tracking-wider">Peranan / Status</th>
                                    <th className="text-left py-3 px-6 text-xs font-semibold text-slate-600 uppercase tracking-wider">Last Login</th>
                                    <th className="text-right py-3 px-6 text-xs font-semibold text-slate-600 uppercase tracking-wider">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {users.map((user) => (
                                    <tr key={user.id} className="hover:bg-slate-50 transition-colors">
                                        <td className="py-4 px-6">
                                            <input
                                                type="checkbox"
                                                checked={selectedUsers.includes(user.id)}
                                                onChange={() => handleSelectUser(user.id)}
                                                className="rounded border-slate-300 text-slate-900 focus:ring-slate-400"
                                            />
                                        </td>
                                        <td className="py-4 px-6">
                                            <div className="text-sm font-medium text-slate-900">{user.name}</div>
                                            <div className="text-xs text-slate-500">{user.telephone}</div>
                                            <div className="text-xs text-slate-500">{user.email}</div>
                                        </td>
                                        <td className="py-4 px-6">
                                            <div className="text-xs text-slate-700 font-medium">{user.negeri?.nama || '-'}</div>
                                            <div className="text-xs text-slate-500">{user.bandar?.nama || '-'}</div>
                                            <div className="text-xs text-slate-500">{user.kadun?.nama || '-'}</div>
                                        </td>
                                        <td className="py-4 px-6">
                                            <div className="flex flex-col space-y-1">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium w-fit ${getRoleBadgeColor(user.role)}`}>
                                                    {getRoleLabel(user.role)}
                                                </span>
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium w-fit ${getStatusBadgeColor(user.status)}`}>
                                                    {getStatusLabel(user.status)}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="py-4 px-6 text-sm text-slate-600">
                                            {formatDate(user.last_login)}
                                        </td>
                                        <td className="py-4 px-6">
                                            <div className="flex items-center justify-end space-x-2">
                                                <button onClick={() => setEditingUser(user)} className="p-2 text-slate-600 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors">
                                                    <Edit className="h-4 w-4" />
                                                </button>
                                                <button onClick={() => handleDelete(user.id)} className="p-2 text-slate-600 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {users.length === 0 && (
                                    <tr>
                                        <td colSpan="6" className="py-8 text-center text-slate-500">Tiada pengguna dijumpai.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Create/Edit Modal */}
            {(showCreateModal || editingUser) && (
                <div className="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 overflow-y-auto">
                    <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 my-8">
                        <div className="flex justify-between items-center mb-6">
                            <h3 className="text-lg font-semibold text-slate-900">
                                {editingUser ? 'Edit Pengguna' : 'Tambah Pengguna Baru'}
                            </h3>
                            <button onClick={() => { setShowCreateModal(false); setEditingUser(null); }} className="text-slate-400 hover:text-slate-600">
                                <X className="h-6 w-6" />
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Nama" required />
                                    <TextInput
                                        value={formData.name}
                                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                        className="w-full mt-1"
                                        required
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Nombor Telefon" required />
                                    <TextInput
                                        value={formData.telephone}
                                        onChange={(e) => setFormData({ ...formData, telephone: e.target.value })}
                                        className="w-full mt-1"
                                        required
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Emel" />
                                    <TextInput
                                        type="email"
                                        value={formData.email}
                                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                        className="w-full mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Kata Laluan" required={!editingUser} />
                                    <TextInput
                                        type="password"
                                        value={formData.password}
                                        onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                        className="w-full mt-1"
                                        placeholder={editingUser ? 'Biarkan kosong jika tidak tukar' : ''}
                                        required={!editingUser}
                                    />
                                </div>
                                {(!editingUser || formData.password) && (
                                    <div>
                                        <InputLabel value="Sahkan Kata Laluan" required={!editingUser || formData.password} />
                                        <TextInput
                                            type="password"
                                            value={formData.password_confirmation}
                                            onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
                                            className="w-full mt-1"
                                            required={!editingUser || formData.password}
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="border-t border-slate-200 pt-4">
                                <h4 className="text-sm font-medium text-slate-900 mb-3">Peranan & Status</h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel value="Peranan" required />
                                        <select
                                            value={formData.role}
                                            onChange={(e) => setFormData({ ...formData, role: e.target.value })}
                                            className="w-full mt-1 border-slate-300 rounded-lg focus:ring-slate-400 focus:border-slate-400"
                                            required
                                        >
                                            <option value="user">Pengguna</option>
                                            <option value="admin">Admin</option>
                                            <option value="super_admin">Super Admin</option>
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="Status" required />
                                        <select
                                            value={formData.status}
                                            onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                                            className="w-full mt-1 border-slate-300 rounded-lg focus:ring-slate-400 focus:border-slate-400"
                                            required
                                        >
                                            <option value="pending">Menunggu</option>
                                            <option value="approved">Lulus</option>
                                            <option value="rejected">Ditolak</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div className="border-t border-slate-200 pt-4">
                                <h4 className="text-sm font-medium text-slate-900 mb-3">Kawasan</h4>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <InputLabel value="Negeri" required />
                                        <select
                                            value={formData.negeri_id}
                                            onChange={(e) => setFormData({ ...formData, negeri_id: e.target.value, bandar_id: '', kadun_id: '' })}
                                            className="w-full mt-1 border-slate-300 rounded-lg focus:ring-slate-400 focus:border-slate-400"
                                            required
                                        >
                                            <option value="">Pilih Negeri</option>
                                            {negeriList.map(n => (
                                                <option key={n.id} value={n.id}>{n.nama}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="Bandar / Parlimen" required />
                                        <select
                                            value={formData.bandar_id}
                                            onChange={(e) => setFormData({ ...formData, bandar_id: e.target.value, kadun_id: '' })}
                                            className="w-full mt-1 border-slate-300 rounded-lg focus:ring-slate-400 focus:border-slate-400"
                                            required
                                            disabled={!formData.negeri_id}
                                        >
                                            <option value="">Pilih Bandar</option>
                                            {filteredBandar.map(b => (
                                                <option key={b.id} value={b.id}>{b.nama}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="KADUN" required />
                                        <select
                                            value={formData.kadun_id}
                                            onChange={(e) => setFormData({ ...formData, kadun_id: e.target.value })}
                                            className="w-full mt-1 border-slate-300 rounded-lg focus:ring-slate-400 focus:border-slate-400"
                                            required
                                            disabled={!formData.bandar_id}
                                        >
                                            <option value="">Pilih KADUN</option>
                                            {filteredKadun.map(k => (
                                                <option key={k.id} value={k.id}>{k.nama}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center justify-end space-x-3 mt-6 pt-4 border-t border-slate-200">
                                <button
                                    type="button"
                                    onClick={() => { setShowCreateModal(false); setEditingUser(null); }}
                                    className="px-4 py-2 text-slate-700 hover:bg-slate-100 rounded-lg transition-colors"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                                >
                                    {editingUser ? 'Simpan Perubahan' : 'Cipta Pengguna'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
