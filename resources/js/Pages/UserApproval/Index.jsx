import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircle, XCircle, MapPin, Calendar, User as UserIcon } from 'lucide-react';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import DangerButton from '@/Components/DangerButton';
import SecondaryButton from '@/Components/SecondaryButton';

export default function Index({ pendingUsers }) {
    const [processing, setProcessing] = useState(null);
    const [confirmingUser, setConfirmingUser] = useState(null);
    const [actionType, setActionType] = useState(null); // 'approve' or 'reject'

    const confirmAction = (user, type) => {
        setConfirmingUser(user);
        setActionType(type);
    };

    const closeModal = () => {
        setConfirmingUser(null);
        setActionType(null);
    };

    const executeAction = () => {
        if (!confirmingUser || !actionType) return;

        const userId = confirmingUser.id;
        setProcessing(userId);

        const routeName = actionType === 'approve' ? 'user-approval.approve' : 'user-approval.reject';

        router.post(route(routeName, userId), {}, {
            onFinish: () => {
                setProcessing(null);
                closeModal();
            },
        });
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('ms-MY', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Kelulusan Pengguna" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Kelulusan Pengguna</h1>
                    <p className="text-sm text-slate-600 mt-1">
                        Luluskan atau tolak pendaftaran pengguna baharu
                    </p>
                </div>

                {/* Pending Users Table */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">
                                        Nama
                                    </th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">
                                        Telefon
                                    </th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">
                                        Emel
                                    </th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">
                                        Peranan
                                    </th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">
                                        Kawasan
                                    </th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">
                                        Tarikh Daftar
                                    </th>
                                    <th className="text-right py-3 px-4 text-xs font-semibold text-slate-600 uppercase w-48">
                                        Tindakan
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {pendingUsers.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="7" className="py-8 text-center text-slate-500">
                                            Tiada pengguna menunggu kelulusan
                                        </td>
                                    </tr>
                                ) : (
                                    pendingUsers.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="py-3 px-4">
                                                <div className="flex items-center space-x-2">
                                                    <UserIcon className="h-4 w-4 text-slate-400" />
                                                    <span className="text-sm font-medium text-slate-900">
                                                        {user.name}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-sm text-slate-600">
                                                {user.telephone}
                                            </td>
                                            <td className="py-3 px-4 text-sm text-slate-600">
                                                {user.email || '-'}
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${user.role === 'admin'
                                                    ? 'bg-purple-100 text-purple-800'
                                                    : 'bg-blue-100 text-blue-800'
                                                    }`}>
                                                    {user.role === 'admin' ? 'Pentadbir' : 'Pengguna'}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-start space-x-1 text-xs text-slate-600">
                                                    <MapPin className="h-3 w-3 text-slate-400 mt-0.5 flex-shrink-0" />
                                                    <div>
                                                        <div>{user.negeri?.nama}</div>
                                                        <div className="text-slate-500">{user.bandar?.nama}</div>
                                                        <div className="text-slate-500">{user.kadun?.nama}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center space-x-1 text-xs text-slate-600">
                                                    <Calendar className="h-3 w-3 text-slate-400" />
                                                    <span>{formatDate(user.created_at)}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center justify-end space-x-2">
                                                    <button
                                                        onClick={() => confirmAction(user, 'approve')}
                                                        disabled={processing === user.id}
                                                        className="flex items-center space-x-1 px-3 py-1.5 bg-emerald-500 text-white text-sm rounded-lg hover:bg-emerald-600 transition-colors disabled:opacity-50"
                                                    >
                                                        <CheckCircle className="h-4 w-4" />
                                                        <span>Lulus</span>
                                                    </button>
                                                    <button
                                                        onClick={() => confirmAction(user, 'reject')}
                                                        disabled={processing === user.id}
                                                        className="flex items-center space-x-1 px-3 py-1.5 bg-rose-500 text-white text-sm rounded-lg hover:bg-rose-600 transition-colors disabled:opacity-50"
                                                    >
                                                        <XCircle className="h-4 w-4" />
                                                        <span>Tolak</span>
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
                    {pendingUsers.last_page > 1 && (
                        <div className="border-t border-slate-200 px-4 py-3 flex items-center justify-between">
                            <div className="text-sm text-slate-600">
                                Menunjukkan {pendingUsers.from} hingga {pendingUsers.to} daripada {pendingUsers.total} rekod
                            </div>
                            <div className="flex items-center space-x-2">
                                {pendingUsers.links.map((link, index) => (
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

            <Modal show={!!confirmingUser} onClose={closeModal}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-slate-900">
                        {actionType === 'approve'
                            ? 'Luluskan Pengguna'
                            : 'Tolak Pengguna'}
                    </h2>

                    <p className="mt-1 text-sm text-slate-600">
                        {actionType === 'approve'
                            ? `Adakah anda pasti mahu meluluskan pengguna ${confirmingUser?.name}? Pengguna akan mendapat akses ke sistem.`
                            : `Adakah anda pasti mahu menolak pengguna ${confirmingUser?.name}? Pengguna tidak akan mendapat akses ke sistem.`}
                    </p>

                    <div className="mt-6 flex justify-end space-x-3">
                        <SecondaryButton onClick={closeModal}>
                            Batal
                        </SecondaryButton>

                        {actionType === 'approve' ? (
                            <PrimaryButton
                                className="bg-emerald-600 hover:bg-emerald-500 focus:ring-emerald-500"
                                onClick={executeAction}
                                disabled={processing === confirmingUser?.id}
                            >
                                {processing === confirmingUser?.id ? 'Sedang Diproses...' : 'Luluskan Pengguna'}
                            </PrimaryButton>
                        ) : (
                            <DangerButton
                                onClick={executeAction}
                                disabled={processing === confirmingUser?.id}
                            >
                                {processing === confirmingUser?.id ? 'Sedang Diproses...' : 'Tolak Pengguna'}
                            </DangerButton>
                        )}
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
