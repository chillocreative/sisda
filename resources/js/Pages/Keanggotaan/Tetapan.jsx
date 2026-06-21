import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { CalendarRange, Save } from 'lucide-react';
import KeanggotaanNav from './Nav';

export default function Tetapan({ setting, flash }) {
    const { data, setData, post, processing, errors } = useForm({
        tahun_mula: setting.tahun_mula ?? '',
        tahun_tamat: setting.tahun_tamat ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('keanggotaan.tetapan.update'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Keanggotaan — Tetapan" />
            <div className="max-w-7xl mx-auto space-y-6">
                <h1 className="text-2xl font-bold text-slate-900">Tetapan Keanggotaan</h1>
                <KeanggotaanNav />

                {flash?.success && <div className="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{flash.success}</div>}

                <div className="bg-white rounded-xl border border-slate-200 p-6 max-w-xl">
                    <div className="flex items-center gap-2 mb-1">
                        <CalendarRange className="h-5 w-5 text-slate-500" />
                        <h3 className="text-lg font-semibold text-slate-900">Penggal Pemilihan Parti</h3>
                    </div>
                    <p className="text-sm text-slate-500 mb-5">
                        Tahun mula &amp; tahun tamat penggal. Ahli yang melepasi umur 35 kekal sah sebagai
                        AMK / Srikandi / Wanita (ditanda merah muda) sehingga tahun tamat penggal berlalu.
                    </p>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Tahun Mula</label>
                                <input
                                    type="number" min="2000" max="2100" placeholder="cth. 2025"
                                    value={data.tahun_mula}
                                    onChange={(e) => setData('tahun_mula', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                />
                                {errors.tahun_mula && <p className="text-sm text-rose-600 mt-1">{errors.tahun_mula}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Tahun Tamat</label>
                                <input
                                    type="number" min="2000" max="2100" placeholder="cth. 2028"
                                    value={data.tahun_tamat}
                                    onChange={(e) => setData('tahun_tamat', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                />
                                {errors.tahun_tamat && <p className="text-sm text-rose-600 mt-1">{errors.tahun_tamat}</p>}
                            </div>
                        </div>
                        <button type="submit" disabled={processing} className="flex items-center gap-2 px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50">
                            <Save className="h-4 w-4" /> Simpan
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
