import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import { Upload, Loader2, CheckCircle, XCircle, RefreshCw, Trash2, Database, AlertTriangle } from 'lucide-react';

export default function Index({ batches, flash }) {
    const [confirmDelete, setConfirmDelete] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        fail: null,
    });

    const fileInputRef = useRef(null);

    const hasProcessing = batches.data.some((b) => b.status === 'processing');

    // Poll every 5 seconds while any batch is processing
    useEffect(() => {
        if (!hasProcessing) return;
        const interval = setInterval(() => {
            router.reload({ only: ['batches'] });
        }, 5000);
        return () => clearInterval(interval);
    }, [hasProcessing]);

    const handleFileChange = (e) => {
        setData('fail', e.target.files[0]);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('upload-database.store'), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const handleRestore = (batch) => {
        router.post(route('upload-database.restore', batch.id));
    };

    const handleDelete = (batch) => {
        setConfirmDelete(batch);
    };

    const confirmDeleteAction = () => {
        if (!confirmDelete) return;
        router.delete(route('upload-database.destroy', confirmDelete.id), {
            onFinish: () => setConfirmDelete(null),
        });
    };

    const statusBadge = (status) => {
        switch (status) {
            case 'completed':
                return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Selesai</span>;
            case 'processing':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        <Loader2 className="h-3 w-3 animate-spin" />
                        Memproses
                    </span>
                );
            case 'failed':
                return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Gagal</span>;
            default:
                return null;
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Upload Database Pengundi" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Upload Database Pengundi</h1>
                    <p className="text-sm text-slate-600 mt-1">Muat naik fail ZIP yang mengandungi data pengundi (format: DUN › Daerah Mengundi › LOCALITIES › *.xlsx)</p>
                </div>

                {/* Flash messages */}
                {flash?.success && (
                    <div className="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3">
                        <CheckCircle className="h-5 w-5 flex-shrink-0" />
                        <span className="text-sm">{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3">
                        <XCircle className="h-5 w-5 flex-shrink-0" />
                        <span className="text-sm">{flash.error}</span>
                    </div>
                )}

                {/* Processing notice banner */}
                {hasProcessing && (
                    <div className="flex items-center gap-3 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg px-4 py-3">
                        <AlertTriangle className="h-5 w-5 flex-shrink-0" />
                        <span className="text-sm">
                            Pemprosesan data sedang berjalan di latar belakang. Halaman ini akan dikemaskini secara automatik setiap 5 saat.
                        </span>
                        <Loader2 className="h-4 w-4 animate-spin flex-shrink-0 ml-auto" />
                    </div>
                )}

                {/* Upload Form */}
                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <Upload className="h-5 w-5" />
                        Muat Naik Fail ZIP
                    </h2>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                Fail ZIP <span className="text-rose-500">*</span>
                            </label>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept=".zip"
                                onChange={handleFileChange}
                                className="block w-full text-sm text-slate-700 border border-slate-300 rounded-lg cursor-pointer bg-slate-50 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-l-lg file:border-0 file:text-sm file:font-medium file:bg-slate-900 file:text-white hover:file:bg-slate-800"
                                required
                            />
                            {errors.fail && <p className="text-sm text-rose-600 mt-1">{errors.fail}</p>}
                            <p className="text-xs text-slate-500 mt-1">Hanya fail .zip sahaja. Saiz maksimum: 100MB.</p>
                        </div>

                        <div className="flex items-center gap-3">
                            <button
                                type="submit"
                                disabled={processing || !data.fail}
                                className="flex items-center gap-2 px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? (
                                    <>
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                        Memuat naik...
                                    </>
                                ) : (
                                    <>
                                        <Upload className="h-4 w-4" />
                                        Muat Naik
                                    </>
                                )}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Upload History */}
                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <Database className="h-5 w-5" />
                        Sejarah Muat Naik
                    </h2>

                    {batches.data.length === 0 ? (
                        <p className="text-sm text-slate-500 text-center py-8">Tiada rekod muat naik.</p>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200">
                                            <th className="text-left py-3 px-3 font-medium text-slate-600">Bil</th>
                                            <th className="text-left py-3 px-3 font-medium text-slate-600">Nama Fail</th>
                                            <th className="text-left py-3 px-3 font-medium text-slate-600">Tarikh Upload</th>
                                            <th className="text-right py-3 px-3 font-medium text-slate-600">Jumlah Rekod</th>
                                            <th className="text-center py-3 px-3 font-medium text-slate-600">Status</th>
                                            <th className="text-center py-3 px-3 font-medium text-slate-600">Aktif</th>
                                            <th className="text-center py-3 px-3 font-medium text-slate-600">Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {batches.data.map((batch, index) => (
                                            <tr key={batch.id} className="hover:bg-slate-50">
                                                <td className="py-3 px-3 text-slate-600">
                                                    {(batches.current_page - 1) * batches.per_page + index + 1}
                                                </td>
                                                <td className="py-3 px-3 text-slate-900 font-medium max-w-xs truncate">
                                                    {batch.nama_fail}
                                                </td>
                                                <td className="py-3 px-3 text-slate-600">
                                                    {new Date(batch.created_at).toLocaleString('ms-MY', {
                                                        day: '2-digit', month: '2-digit', year: 'numeric',
                                                        hour: '2-digit', minute: '2-digit'
                                                    })}
                                                </td>
                                                <td className="py-3 px-3 text-slate-900 text-right">
                                                    {batch.jumlah_rekod.toLocaleString()}
                                                </td>
                                                <td className="py-3 px-3 text-center">
                                                    {statusBadge(batch.status)}
                                                </td>
                                                <td className="py-3 px-3 text-center">
                                                    {batch.is_active ? (
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                                                    ) : (
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Tidak Aktif</span>
                                                    )}
                                                </td>
                                                <td className="py-3 px-3">
                                                    <div className="flex items-center justify-center gap-2">
                                                        <button
                                                            onClick={() => handleRestore(batch)}
                                                            disabled={batch.is_active || batch.status === 'processing'}
                                                            title="Jadikan aktif"
                                                            className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                        >
                                                            <RefreshCw className="h-3 w-3" />
                                                            Restore
                                                        </button>
                                                        <button
                                                            onClick={() => handleDelete(batch)}
                                                            disabled={batch.status === 'processing'}
                                                            title="Padam"
                                                            className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-red-300 text-red-700 hover:bg-red-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                        >
                                                            <Trash2 className="h-3 w-3" />
                                                            Padam
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            {batches.last_page > 1 && (
                                <div className="flex items-center justify-between mt-4 pt-4 border-t border-slate-200">
                                    <p className="text-sm text-slate-600">
                                        Halaman {batches.current_page} daripada {batches.last_page}
                                    </p>
                                    <div className="flex gap-2">
                                        {batches.prev_page_url && (
                                            <a
                                                href={batches.prev_page_url}
                                                className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-100"
                                            >
                                                Sebelum
                                            </a>
                                        )}
                                        {batches.next_page_url && (
                                            <a
                                                href={batches.next_page_url}
                                                className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-100"
                                            >
                                                Seterusnya
                                            </a>
                                        )}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>

            {/* Confirm Delete Modal */}
            {confirmDelete && (
                <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="flex-shrink-0 h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                                <Trash2 className="h-5 w-5 text-red-600" />
                            </div>
                            <div>
                                <h3 className="text-base font-semibold text-slate-900">Padam Rekod</h3>
                                <p className="text-sm text-slate-600">Tindakan ini tidak boleh diundur.</p>
                            </div>
                        </div>
                        <p className="text-sm text-slate-700">
                            Adakah anda pasti mahu memadam <strong>{confirmDelete.nama_fail}</strong>?
                            Semua <strong>{confirmDelete.jumlah_rekod.toLocaleString()}</strong> rekod pengundi akan dipadam bersama.
                        </p>
                        <div className="flex justify-end gap-3 pt-2">
                            <button
                                onClick={() => setConfirmDelete(null)}
                                className="px-4 py-2 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                onClick={confirmDeleteAction}
                                className="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700"
                            >
                                Ya, Padam
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
