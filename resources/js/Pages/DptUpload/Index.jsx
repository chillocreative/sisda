import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { Upload, FileText, CheckCircle, XCircle, Trash2, Loader2, Users, UserX, ArrowRightLeft, Search } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';

export default function Index({ uploads, filters = {} }) {
    const { flash = {} } = usePage().props;
    const [confirmDelete, setConfirmDelete] = useState(null);
    const [search, setSearch] = useState(filters.search || '');
    const [perPage, setPerPage] = useState(filters.per_page || 20);
    const isFirstRender = useRef(true);
    const debounceRef = useRef(null);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            router.get(
                route('dpt-upload.index'),
                { search, per_page: perPage },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        }, 400);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [search, perPage]);

    const { data, setData, post, processing, progress } = useForm({
        file: null,
    });

    const handleUpload = (e) => {
        e.preventDefault();
        if (!data.file) return;
        post(route('dpt-upload.upload'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const handleDelete = (id) => {
        router.delete(route('dpt-upload.destroy', id), {
            preserveScroll: true,
            onSuccess: () => setConfirmDelete(null),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Upload DPT" />

            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex items-center gap-3 mb-8">
                    <div className="p-2 bg-sky-100 rounded-lg">
                        <FileText className="h-6 w-6 text-sky-600" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Data Pengundi Tambahan (DPT)</h1>
                        <p className="text-sm text-slate-500">Muat naik fail PDF Daftar Pemilih Tambahan dari SPR</p>
                    </div>
                </div>

                {/* Flash Messages */}
                {flash?.success && (
                    <div className="mb-6 rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700 flex items-center gap-2">
                        <CheckCircle className="h-4 w-4 flex-shrink-0" />
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-6 rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700 flex items-center gap-2">
                        <XCircle className="h-4 w-4 flex-shrink-0" />
                        {flash.error}
                    </div>
                )}

                {/* Upload Form */}
                <div className="bg-white rounded-xl border border-slate-200 p-6 mb-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <Upload className="h-5 w-5" />
                        Muat Naik DPT
                    </h2>
                    <form onSubmit={handleUpload} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">Fail PDF Daftar Pemilih Tambahan</label>
                            <div className="flex items-center gap-3">
                                <input
                                    type="file"
                                    accept=".pdf"
                                    onChange={(e) => setData('file', e.target.files[0])}
                                    className="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
                                />
                                <button
                                    type="submit"
                                    disabled={processing || !data.file}
                                    className="px-4 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700 text-sm font-medium disabled:opacity-50 flex items-center gap-2 whitespace-nowrap"
                                >
                                    {processing ? (
                                        <>
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            Memproses...
                                        </>
                                    ) : (
                                        <>
                                            <Upload className="h-4 w-4" />
                                            Muat Naik & Proses
                                        </>
                                    )}
                                </button>
                            </div>
                            <p className="text-xs text-slate-500 mt-2">Format: PDF (maks 50MB). Sistem akan mengekstrak data pengundi, kematian dan pertukaran alamat secara automatik.</p>
                        </div>
                        {progress && (
                            <div className="w-full bg-slate-200 rounded-full h-2">
                                <div className="bg-sky-600 h-2 rounded-full transition-all" style={{ width: `${progress.percentage}%` }} />
                            </div>
                        )}
                    </form>
                </div>

                {/* Upload History */}
                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4">Sejarah Muat Naik DPT</h2>

                    {/* Search & Filter */}
                    <div className="mb-4 flex flex-col sm:flex-row gap-3">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari label, parlimen, negeri atau nama fail..."
                                className="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <label className="text-sm text-slate-600 whitespace-nowrap">Paparkan:</label>
                            <select
                                value={perPage}
                                onChange={(e) => setPerPage(Number(e.target.value))}
                                className="py-2 pl-3 pr-8 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                            >
                                <option value={10}>10</option>
                                <option value={20}>20</option>
                                <option value={50}>50</option>
                                <option value={100}>100</option>
                            </select>
                        </div>
                    </div>

                    {uploads.data.length === 0 ? (
                        <p className="text-sm text-slate-500 text-center py-8">
                            {search ? 'Tiada rekod sepadan dengan carian.' : 'Tiada rekod muat naik DPT lagi.'}
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {uploads.data.map((upload) => (
                                <div key={upload.id} className={`rounded-lg border p-4 ${upload.status === 'failed' ? 'border-rose-200 bg-rose-50' : 'border-slate-200'}`}>
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <FileText className="h-4 w-4 text-sky-600 flex-shrink-0" />
                                                <h3 className="text-sm font-semibold text-slate-900">{upload.label}</h3>
                                                <span className={`px-1.5 py-0.5 rounded text-xs font-medium ${
                                                    upload.status === 'completed' ? 'bg-emerald-100 text-emerald-700' :
                                                    upload.status === 'processing' ? 'bg-amber-100 text-amber-700' :
                                                    'bg-rose-100 text-rose-700'
                                                }`}>
                                                    {upload.status === 'completed' ? 'Selesai' : upload.status === 'processing' ? 'Memproses' : 'Gagal'}
                                                </span>
                                            </div>
                                            <p className="text-xs text-slate-500 mt-1">
                                                {upload.parlimen && <span>Parlimen: {upload.parlimen} | </span>}
                                                {upload.negeri && <span>Negeri: {upload.negeri} | </span>}
                                                Fail: {upload.filename}
                                            </p>
                                            {upload.status === 'completed' && (
                                                <div className="flex items-center gap-4 mt-2">
                                                    <span className="flex items-center gap-1 text-xs text-slate-600">
                                                        <Users className="h-3 w-3" />
                                                        {upload.total_records} jumlah
                                                    </span>
                                                    <span className="flex items-center gap-1 text-xs text-emerald-600">
                                                        {upload.total_new} baru
                                                    </span>
                                                    <span className="flex items-center gap-1 text-xs text-rose-600">
                                                        <UserX className="h-3 w-3" />
                                                        {upload.total_deceased} kematian
                                                    </span>
                                                    <span className="flex items-center gap-1 text-xs text-amber-600">
                                                        <ArrowRightLeft className="h-3 w-3" />
                                                        {upload.total_moved} bertukar
                                                    </span>
                                                </div>
                                            )}
                                            {upload.error && (
                                                <p className="text-xs text-rose-600 mt-1">Ralat: {upload.error}</p>
                                            )}
                                            <p className="text-xs text-slate-400 mt-1">
                                                Oleh: {upload.uploader?.name || '-'} | {new Date(upload.created_at).toLocaleDateString('ms-MY', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                            </p>
                                        </div>
                                        <div>
                                            {confirmDelete === upload.id ? (
                                                <div className="flex items-center gap-1">
                                                    <button onClick={() => handleDelete(upload.id)} className="px-2 py-1 bg-rose-600 text-white text-xs rounded hover:bg-rose-700">Padam</button>
                                                    <button onClick={() => setConfirmDelete(null)} className="px-2 py-1 bg-slate-200 text-slate-600 text-xs rounded hover:bg-slate-300">Batal</button>
                                                </div>
                                            ) : (
                                                <button onClick={() => setConfirmDelete(upload.id)} className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {uploads.last_page > 1 && (
                        <div className="mt-4 pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div className="text-sm text-slate-600">
                                Menunjukkan {uploads.from} hingga {uploads.to} daripada {uploads.total} rekod
                            </div>
                            <div className="flex items-center flex-wrap gap-1">
                                {uploads.links.map((link, index) => (
                                    <button
                                        key={index}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                        disabled={!link.url}
                                        className={`px-3 py-1 rounded-lg text-sm transition-colors ${
                                            link.active
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
        </AuthenticatedLayout>
    );
}
