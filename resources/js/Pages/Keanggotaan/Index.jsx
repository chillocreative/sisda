import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import { Upload, Loader2, CheckCircle, XCircle, X, Power, PowerOff, Trash2, Database, AlertTriangle } from 'lucide-react';
import KeanggotaanNav from './Nav';

export default function Index({ batches, flash }) {
    const [confirmDelete, setConfirmDelete] = useState(null);
    const [selected, setSelected] = useState([]);
    const [activating, setActivating] = useState(false);

    const completedIds = batches.data.filter((b) => b.status === 'completed').map((b) => b.id);
    const allSelected = completedIds.length > 0 && completedIds.every((id) => selected.includes(id));

    const { data, setData, post, processing, errors, reset } = useForm({ fail: null });
    const fileInputRef = useRef(null);
    const hasProcessing = batches.data.some((b) => b.status === 'processing');

    useEffect(() => {
        if (!hasProcessing) return;
        const interval = setInterval(() => router.reload({ only: ['batches'] }), 5000);
        return () => clearInterval(interval);
    }, [hasProcessing]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('keanggotaan.store'), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const toggleSelect = (id) => setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    const toggleSelectAll = () => setSelected(allSelected ? [] : completedIds);

    const handleSetActive = (batchIds, action) => {
        setActivating(true);
        router.post(route('keanggotaan.set-active'), { batch_ids: batchIds, action }, {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
            onFinish: () => setActivating(false),
        });
    };

    const confirmDeleteAction = () => {
        if (!confirmDelete) return;
        router.delete(route('keanggotaan.destroy', confirmDelete.id), { onFinish: () => setConfirmDelete(null) });
    };

    const statusBadge = (status) => {
        switch (status) {
            case 'completed':
                return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Selesai</span>;
            case 'processing':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        <Loader2 className="h-3 w-3 animate-spin" /> Memproses
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
            <Head title="Keanggotaan — Muat Naik" />

            <div className="max-w-5xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Keanggotaan</h1>
                    <p className="text-sm text-slate-600 mt-1">Muat naik senarai ahli (ZIP / Excel / CSV / PDF). Setiap ahli dipadankan dengan SISDA mengikut No. IC — ahli yang tiada dalam pangkalan data pengundi aktif dilabel sebagai <strong>pengundi luar kawasan</strong>.</p>
                </div>

                <KeanggotaanNav />

                {flash?.success && (
                    <div className="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3">
                        <CheckCircle className="h-5 w-5 flex-shrink-0" /><span className="text-sm">{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3">
                        <XCircle className="h-5 w-5 flex-shrink-0" /><span className="text-sm">{flash.error}</span>
                    </div>
                )}

                {hasProcessing && (
                    <div className="flex items-center gap-3 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg px-4 py-3">
                        <AlertTriangle className="h-5 w-5 flex-shrink-0" />
                        <span className="text-sm">Pemprosesan & padanan sedang berjalan. Halaman dikemaskini setiap 5 saat.</span>
                        <Loader2 className="h-4 w-4 animate-spin flex-shrink-0 ml-auto" />
                    </div>
                )}

                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <Upload className="h-5 w-5" /> Muat Naik Fail Keanggotaan
                    </h2>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Fail <span className="text-rose-500">*</span></label>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept=".zip,.xlsx,.xls,.csv,.pdf"
                                onChange={(e) => setData('fail', e.target.files[0])}
                                className="block w-full text-sm text-slate-700 border border-slate-300 rounded-lg cursor-pointer bg-slate-50 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-l-lg file:border-0 file:text-sm file:font-medium file:bg-slate-900 file:text-white hover:file:bg-slate-800"
                                required
                            />
                            {errors.fail && <p className="text-sm text-rose-600 mt-1">{errors.fail}</p>}
                            <p className="text-xs text-slate-500 mt-1">Format: .zip, .xlsx, .xls, .csv atau .pdf. Lajur dikenali: nama, no_ic, no_tel. Saiz maksimum: 100MB.</p>
                        </div>
                        <button type="submit" disabled={processing || !data.fail} className="flex items-center gap-2 px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Memuat naik...</> : <><Upload className="h-4 w-4" /> Muat Naik</>}
                        </button>
                    </form>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h2 className="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <Database className="h-5 w-5" /> Sejarah Muat Naik
                        </h2>
                        {selected.length > 0 && (
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-slate-600">{selected.length} dipilih</span>
                                <button onClick={() => handleSetActive(selected, 'activate')} disabled={activating} className="flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50">
                                    {activating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Power className="h-4 w-4" />} Aktifkan ({selected.length})
                                </button>
                                <button onClick={() => handleSetActive(selected, 'deactivate')} disabled={activating} className="flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-50">
                                    <PowerOff className="h-4 w-4" /> Nyahaktifkan ({selected.length})
                                </button>
                            </div>
                        )}
                    </div>

                    {batches.data.length === 0 ? (
                        <p className="text-sm text-slate-500 text-center py-8">Tiada rekod muat naik.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200">
                                        <th className="py-3 px-3 w-10">
                                            <input type="checkbox" checked={allSelected} onChange={toggleSelectAll} disabled={completedIds.length === 0} className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                        </th>
                                        <th className="text-left py-3 px-3 font-medium text-slate-600">Nama Fail</th>
                                        <th className="text-left py-3 px-3 font-medium text-slate-600">Tarikh</th>
                                        <th className="text-right py-3 px-3 font-medium text-slate-600">Jumlah Ahli</th>
                                        <th className="text-center py-3 px-3 font-medium text-slate-600">Status</th>
                                        <th className="text-center py-3 px-3 font-medium text-slate-600">Aktif</th>
                                        <th className="text-center py-3 px-3 font-medium text-slate-600">Tindakan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {batches.data.map((batch) => (
                                        <tr key={batch.id} className="hover:bg-slate-50">
                                            <td className="py-3 px-3">
                                                {batch.status === 'completed' && (
                                                    <input type="checkbox" checked={selected.includes(batch.id)} onChange={() => toggleSelect(batch.id)} className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                                )}
                                            </td>
                                            <td className="py-3 px-3 text-slate-900 font-medium max-w-xs truncate">{batch.nama_fail}</td>
                                            <td className="py-3 px-3 text-slate-600">{new Date(batch.created_at).toLocaleString('ms-MY', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td>
                                            <td className="py-3 px-3 text-slate-900 text-right">{batch.jumlah_rekod.toLocaleString()}</td>
                                            <td className="py-3 px-3 text-center">{statusBadge(batch.status)}</td>
                                            <td className="py-3 px-3 text-center">
                                                {batch.is_active
                                                    ? <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                                                    : <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Tidak Aktif</span>}
                                            </td>
                                            <td className="py-3 px-3">
                                                <div className="flex items-center justify-center gap-2">
                                                    {batch.status === 'processing' ? (
                                                        <button onClick={() => router.post(route('keanggotaan.cancel', batch.id))} className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50">
                                                            <X className="h-3 w-3" /> Batal
                                                        </button>
                                                    ) : batch.status === 'completed' ? (
                                                        batch.is_active ? (
                                                            <button onClick={() => handleSetActive([batch.id], 'deactivate')} disabled={activating} className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40">
                                                                <PowerOff className="h-3 w-3" /> Nyahaktif
                                                            </button>
                                                        ) : (
                                                            <button onClick={() => handleSetActive([batch.id], 'activate')} disabled={activating} className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50 disabled:opacity-40">
                                                                <Power className="h-3 w-3" /> Aktifkan
                                                            </button>
                                                        )
                                                    ) : null}
                                                    <button onClick={() => setConfirmDelete(batch)} className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-red-300 text-red-700 hover:bg-red-50">
                                                        <Trash2 className="h-3 w-3" /> Padam
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {confirmDelete && (
                <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                        <h3 className="text-base font-semibold text-slate-900">Padam Batch Keanggotaan</h3>
                        <p className="text-sm text-slate-700">Padam <strong>{confirmDelete.nama_fail}</strong> dan semua {confirmDelete.jumlah_rekod.toLocaleString()} ahli di dalamnya?</p>
                        <div className="flex justify-end gap-3 pt-2">
                            <button onClick={() => setConfirmDelete(null)} className="px-4 py-2 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50">Batal</button>
                            <button onClick={confirmDeleteAction} className="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Ya, Padam</button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
