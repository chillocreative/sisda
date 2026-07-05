import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState, useRef, useEffect, Fragment } from 'react';
import { Upload, Loader2, CheckCircle, XCircle, Trash2, Database, AlertTriangle, ChevronDown, ChevronUp } from 'lucide-react';

export default function Index({ uploads, flash }) {
    const [confirmDelete, setConfirmDelete] = useState(null);
    const [expanded, setExpanded] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        fail: null,
    });

    const fileInputRef = useRef(null);
    const hasProcessing = uploads.data.some((u) => u.status === 'processing');

    // Poll every 5 seconds while any upload is processing.
    useEffect(() => {
        if (!hasProcessing) return;
        const interval = setInterval(() => {
            router.reload({ only: ['uploads'] });
        }, 5000);
        return () => clearInterval(interval);
    }, [hasProcessing]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('upload-culaan.store'), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const confirmDeleteAction = () => {
        if (!confirmDelete) return;
        router.delete(route('upload-culaan.destroy', confirmDelete.id), {
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

    const num = (n) => Number(n || 0).toLocaleString();

    return (
        <AuthenticatedLayout>
            <Head title="Upload Culaan" />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Upload Culaan</h1>
                    <p className="text-sm text-slate-600 mt-1">
                        Muat naik fail CSV data culaan (sentimen canvassing). Sistem memadankan <strong>nama pengundi</strong> dengan daftar pemilih (diskop mengikut Parlimen &amp; DUN), kemudian menetapkan <strong>kecenderungan politik</strong> + warna pengundi pada Data Pengundi. Nama yang tidak dijumpai atau bertindih (taksah) dilaporkan — tidak direka.
                    </p>
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
                            Pemprosesan culaan sedang berjalan di latar belakang. Halaman ini akan dikemaskini secara automatik setiap 5 saat.
                        </span>
                        <Loader2 className="h-4 w-4 animate-spin flex-shrink-0 ml-auto" />
                    </div>
                )}

                {/* Upload Form */}
                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <Upload className="h-5 w-5" />
                        Muat Naik Fail CSV
                    </h2>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                Fail CSV <span className="text-rose-500">*</span>
                            </label>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept=".csv,.txt"
                                onChange={(e) => setData('fail', e.target.files[0])}
                                className="block w-full text-sm text-slate-700 border border-slate-300 rounded-lg cursor-pointer bg-slate-50 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-l-lg file:border-0 file:text-sm file:font-medium file:bg-slate-900 file:text-white hover:file:bg-slate-800"
                                required
                            />
                            {errors.fail && <p className="text-sm text-rose-600 mt-1">{errors.fail}</p>}
                            <p className="text-xs text-slate-500 mt-1">Format: .csv (eksport DATA CULAAN). Perlu lajur <code>voter_name</code>, <code>sentiment</code>, <code>constituency_code</code>, <code>operation_name</code>. Saiz maksimum: 50MB.</p>
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
                    <h2 className="text-lg font-semibold text-slate-900 flex items-center gap-2 mb-4">
                        <Database className="h-5 w-5" />
                        Sejarah Muat Naik Culaan
                    </h2>

                    {uploads.data.length === 0 ? (
                        <p className="text-sm text-slate-500 text-center py-8">Tiada rekod muat naik.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200">
                                        <th className="text-left py-3 px-3 font-medium text-slate-600">Bil</th>
                                        <th className="text-left py-3 px-3 font-medium text-slate-600">Nama Fail</th>
                                        <th className="text-left py-3 px-3 font-medium text-slate-600">Tarikh</th>
                                        <th className="text-right py-3 px-3 font-medium text-slate-600">Baris</th>
                                        <th className="text-right py-3 px-3 font-medium text-slate-600">Dipadan</th>
                                        <th className="text-right py-3 px-3 font-medium text-slate-600">Dicipta</th>
                                        <th className="text-right py-3 px-3 font-medium text-slate-600">Kemaskini</th>
                                        <th className="text-right py-3 px-3 font-medium text-slate-600">Tak Jumpa</th>
                                        <th className="text-right py-3 px-3 font-medium text-slate-600">Taksah</th>
                                        <th className="text-center py-3 px-3 font-medium text-slate-600">Status</th>
                                        <th className="text-center py-3 px-3 font-medium text-slate-600">Tindakan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {uploads.data.map((u, index) => (
                                        <Fragment key={u.id}>
                                            <tr className="hover:bg-slate-50">
                                                <td className="py-3 px-3 text-slate-600">
                                                    {(uploads.current_page - 1) * uploads.per_page + index + 1}
                                                </td>
                                                <td className="py-3 px-3 text-slate-900 font-medium">
                                                    <span className="max-w-[220px] truncate inline-block align-middle">{u.nama_fail}</span>
                                                </td>
                                                <td className="py-3 px-3 text-slate-600 whitespace-nowrap">
                                                    {new Date(u.created_at).toLocaleString('ms-MY', {
                                                        day: '2-digit', month: '2-digit', year: 'numeric',
                                                        hour: '2-digit', minute: '2-digit'
                                                    })}
                                                </td>
                                                <td className="py-3 px-3 text-right text-slate-700">{num(u.jumlah_baris)}</td>
                                                <td className="py-3 px-3 text-right text-slate-900 font-medium">{num(u.matched)}</td>
                                                <td className="py-3 px-3 text-right text-emerald-700">{num(u.dicipta)}</td>
                                                <td className="py-3 px-3 text-right text-sky-700">{num(u.dikemaskini)}</td>
                                                <td className="py-3 px-3 text-right text-amber-700">{num(u.tidak_dijumpai)}</td>
                                                <td className="py-3 px-3 text-right text-rose-700">{num(u.taksah)}</td>
                                                <td className="py-3 px-3 text-center">{statusBadge(u.status)}</td>
                                                <td className="py-3 px-3">
                                                    <div className="flex items-center justify-center gap-2">
                                                        {u.status === 'completed' && (
                                                            <button
                                                                onClick={() => setExpanded(expanded === u.id ? null : u.id)}
                                                                title="Lihat laporan"
                                                                className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors"
                                                            >
                                                                {expanded === u.id ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                                                                Laporan
                                                            </button>
                                                        )}
                                                        <button
                                                            onClick={() => setConfirmDelete(u)}
                                                            title="Padam"
                                                            className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-red-300 text-red-700 hover:bg-red-50 transition-colors"
                                                        >
                                                            <Trash2 className="h-3 w-3" />
                                                            Padam
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            {expanded === u.id && (
                                                <tr className="bg-slate-50">
                                                    <td colSpan="11" className="px-4 py-4">
                                                        {u.status === 'failed' ? (
                                                            <p className="text-sm text-rose-700">Ralat: {u.error || 'tidak diketahui'}</p>
                                                        ) : (
                                                            <ReportPanel report={u.report} u={u} num={num} />
                                                        )}
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    ))}
                                </tbody>
                            </table>

                            {/* Pagination */}
                            {uploads.last_page > 1 && (
                                <div className="flex items-center justify-between mt-4 pt-4 border-t border-slate-200">
                                    <p className="text-sm text-slate-600">Halaman {uploads.current_page} daripada {uploads.last_page}</p>
                                    <div className="flex gap-2">
                                        {uploads.prev_page_url && (
                                            <a href={uploads.prev_page_url} className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-100">Sebelum</a>
                                        )}
                                        {uploads.next_page_url && (
                                            <a href={uploads.next_page_url} className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-100">Seterusnya</a>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
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
                                <h3 className="text-base font-semibold text-slate-900">Padam Rekod Muat Naik</h3>
                                <p className="text-sm text-slate-600">Tindakan ini tidak boleh diundur.</p>
                            </div>
                        </div>
                        <p className="text-sm text-slate-700">
                            Padam rekod <strong>{confirmDelete.nama_fail}</strong>? Kecenderungan yang telah ditetapkan pada pengundi <strong>tidak</strong> diundurkan — hanya rekod muat naik ini dibuang.
                        </p>
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

function ReportPanel({ report, u, num }) {
    if (!report) {
        return <p className="text-sm text-slate-500">Tiada laporan.</p>;
    }
    const perKons = report.per_konstituensi || {};
    const konsRows = Object.entries(perKons);

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-700">
                <span><strong>{num(report.jumlah_baris)}</strong> baris</span>
                <span><strong>{num(report.pengundi_unik)}</strong> pengundi unik</span>
                <span>Dipadan <strong>{num(report.matched)}</strong></span>
                <span className="text-emerald-700">Dicipta {num(report.dicipta)}</span>
                <span className="text-sky-700">Kemaskini {num(report.dikemaskini)}</span>
                <span className="text-slate-500">Tak berubah {num(report.tak_berubah)}</span>
                <span className="text-amber-700">Tak dijumpai {num(report.tidak_dijumpai)}</span>
                <span className="text-rose-700">Bertindih {num(report.taksah)} (1 dipilih)</span>
                <span>Tiada sentimen→TIDAK PASTI {num(report.tiada_sentimen)}</span>
                {report.unresolved_constituency > 0 && (
                    <span className="text-rose-700">Kawasan tak dikenali {num(report.unresolved_constituency)}</span>
                )}
            </div>

            {report.baris_tanpa_dun > 0 && report.baris_tanpa_dun >= (report.jumlah_baris || 0) * 0.5 && (
                <p className="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                    ⚠ Fail ini tiada lajur DUN (<code>operation_name</code>) — padanan dibuat ikut <strong>Parlimen sahaja</strong>, jadi banyak nama "bertindih". Untuk ketepatan peringkat-DUN, guna fail culaan harian (<code>culaan_culaan-today_*.csv</code>).
                </p>
            )}

            {report.matched === 0 && (
                <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                    0 padanan — pastikan daftar pemilih (roll) Johor telah dimuat naik &amp; <strong>aktif</strong> di Upload Database.
                </p>
            )}

            {konsRows.length > 0 && (
                <div className="overflow-x-auto">
                    <table className="text-xs border border-slate-200 rounded">
                        <thead className="bg-slate-100 text-slate-600">
                            <tr>
                                <th className="text-left py-1.5 px-2">Parlimen</th>
                                <th className="text-right py-1.5 px-2">Dipadan</th>
                                <th className="text-right py-1.5 px-2">Dicipta</th>
                                <th className="text-right py-1.5 px-2">Kemaskini</th>
                                <th className="text-right py-1.5 px-2">Tak Jumpa</th>
                                <th className="text-right py-1.5 px-2">Taksah</th>
                            </tr>
                        </thead>
                        <tbody>
                            {konsRows.map(([kons, c]) => (
                                <tr key={kons} className="border-t border-slate-200">
                                    <td className="py-1.5 px-2 text-slate-800">{kons}</td>
                                    <td className="py-1.5 px-2 text-right">{num(c.matched)}</td>
                                    <td className="py-1.5 px-2 text-right text-emerald-700">{num(c.dicipta)}</td>
                                    <td className="py-1.5 px-2 text-right text-sky-700">{num(c.dikemaskini)}</td>
                                    <td className="py-1.5 px-2 text-right text-amber-700">{num(c.tidak_dijumpai)}</td>
                                    <td className="py-1.5 px-2 text-right text-rose-700">{num(c.taksah)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {(report.sample_tidak_dijumpai || []).length > 0 && (
                    <div>
                        <p className="text-xs font-semibold text-amber-700 mb-1">Tidak dijumpai dalam roll (contoh)</p>
                        <div className="text-xs text-slate-600 bg-white border border-slate-200 rounded p-2 max-h-40 overflow-y-auto space-y-0.5">
                            {report.sample_tidak_dijumpai.map((s, i) => <div key={i}>{s}</div>)}
                        </div>
                    </div>
                )}
                {(report.sample_taksah || []).length > 0 && (
                    <div>
                        <p className="text-xs font-semibold text-rose-700 mb-1">Nama bertindih — 1 dipilih automatik (contoh)</p>
                        <div className="text-xs text-slate-600 bg-white border border-slate-200 rounded p-2 max-h-40 overflow-y-auto space-y-0.5">
                            {report.sample_taksah.map((s, i) => <div key={i}>{s}</div>)}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
