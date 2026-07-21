import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { CheckCircle, Database, Landmark, Wifi, XCircle } from 'lucide-react';
import { useState } from 'react';

/**
 * Tetapan API electiondata.my.
 *
 * Kunci disimpan disulitkan dalam pangkalan data (bukan .env) mengikut
 * konvensyen Tetapan Claude, dan tidak pernah dihantar semula kepada pelayar —
 * medan menunjukkan topeng apabila kunci sudah wujud.
 */
export default function ElectionData({ settings, stats }) {
    const { flash = {} } = usePage().props;
    const [testing, setTesting] = useState(false);

    const { data, setData, post, processing } = useForm({
        api_key: settings?.api_key || '',
        is_active: settings?.is_active ?? true,
    });

    const save = (e) => {
        e.preventDefault();
        post(route('settings.election-data.update'));
    };

    const test = () => {
        setTesting(true);
        router.post(route('settings.election-data.test'), {}, {
            preserveScroll: true,
            onFinish: () => setTesting(false),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Tetapan electiondata.my" />

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex items-center gap-3 mb-8">
                    <div className="p-2 bg-emerald-100 rounded-lg">
                        <Landmark className="h-6 w-6 text-emerald-600" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Tetapan electiondata.my</h1>
                        <p className="text-sm text-slate-500">
                            Keputusan rasmi SPR bagi setiap kerusi Malaysia, digunakan sebagai garis dasar sejarah.
                        </p>
                    </div>
                </div>

                {flash.success && (
                    <div className="mb-4 flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <CheckCircle className="h-4 w-4" /> {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="mb-4 flex items-center gap-2 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <XCircle className="h-4 w-4" /> {flash.error}
                    </div>
                )}

                <form onSubmit={save} className="space-y-6 rounded-xl border border-slate-200 bg-white p-6">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Kunci API</label>
                        <input
                            type="password"
                            value={data.api_key}
                            onChange={(e) => setData('api_key', e.target.value)}
                            placeholder={settings?.has_key ? 'Kunci tersimpan — biarkan untuk mengekalkannya' : 'edmy_…'}
                            className="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            Kunci disimpan dalam keadaan disulitkan dan tidak pernah dipaparkan semula.
                            Jana kunci di konsol electiondata.my.
                        </p>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                            className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        Aktifkan integrasi
                    </label>

                    <div className="flex items-center gap-3">
                        <button type="submit" disabled={processing}
                            className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            Simpan
                        </button>
                        <button type="button" onClick={test} disabled={testing || !settings?.has_key}
                            className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                            <Wifi className="h-4 w-4" /> {testing ? 'Menguji…' : 'Uji Sambungan'}
                        </button>
                    </div>
                </form>

                <div className="mt-6 rounded-xl border border-slate-200 bg-white p-6">
                    <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <Database className="h-4 w-4" /> Data yang telah disegerakkan
                    </div>
                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <dt className="text-xs text-slate-500">Kerusi</dt>
                            <dd className="text-xl font-bold text-slate-900">{stats.seats.toLocaleString('ms-MY')}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-500">Keputusan</dt>
                            <dd className="text-xl font-bold text-slate-900">{stats.results.toLocaleString('ms-MY')}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-500">Tidak dipadan dengan kawasan SISDA</dt>
                            <dd className={`text-xl font-bold ${stats.unmatched > 0 ? 'text-amber-600' : 'text-slate-900'}`}>
                                {stats.unmatched.toLocaleString('ms-MY')}
                            </dd>
                        </div>
                    </dl>
                    <p className="mt-3 text-xs text-slate-500">
                        Segerakkan secara manual — ia tidak berjalan semasa deploy:
                        <code className="ml-1 rounded bg-slate-100 px-1.5 py-0.5">php artisan pilihanraya:sync-electiondata</code>
                    </p>
                    {settings?.last_synced_at && (
                        <p className="mt-1 text-xs text-slate-500">Segerak terakhir: {settings.last_synced_at}</p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
