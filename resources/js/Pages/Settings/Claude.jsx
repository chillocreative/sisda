import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { Settings, Wifi, CheckCircle, XCircle, Brain, Sparkles } from 'lucide-react';
import { useState } from 'react';

export default function Claude({ settings }) {
    const { flash = {} } = usePage().props;
    const [testingConnection, setTestingConnection] = useState(false);

    const { data, setData, post, processing } = useForm({
        api_key: settings?.api_key || '',
        model: settings?.model || 'claude-sonnet-4-20250514',
        max_tokens: settings?.max_tokens || 4096,
        is_active: settings?.is_active || false,
    });

    const models = [
        { value: 'claude-sonnet-4-20250514', label: 'Claude Sonnet 4 (Terbaru, Seimbang)' },
        { value: 'claude-haiku-4-5-20251001', label: 'Claude Haiku 4.5 (Pantas, Jimat)' },
        { value: 'claude-opus-4-20250514', label: 'Claude Opus 4 (Terkuat)' },
    ];

    const handleSave = (e) => {
        e.preventDefault();
        post(route('settings.claude.update'));
    };

    const handleTestConnection = () => {
        setTestingConnection(true);
        router.post(route('settings.claude.test'), {
            api_key: data.api_key,
            model: data.model,
        }, {
            preserveScroll: true,
            onFinish: () => setTestingConnection(false),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Tetapan Claude AI" />

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex items-center gap-3 mb-8">
                    <div className="p-2 bg-violet-100 rounded-lg">
                        <Brain className="h-6 w-6 text-violet-600" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Tetapan Claude AI</h1>
                        <p className="text-sm text-slate-500">Konfigurasi API Claude untuk analitik dan pemprosesan dokumen PDF</p>
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

                {/* Configuration Form */}
                <div className="bg-white rounded-xl border border-slate-200 p-6 mb-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <Settings className="h-5 w-5" />
                        Konfigurasi API
                    </h2>
                    <form onSubmit={handleSave} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">API Key</label>
                            <input
                                type="password"
                                value={data.api_key}
                                onChange={(e) => setData('api_key', e.target.value)}
                                placeholder={settings?.has_key ? '••••••••' : 'sk-ant-api03-...'}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            />
                            <p className="text-xs text-slate-500 mt-1">Dapatkan API key dari <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener" className="text-violet-600 underline">console.anthropic.com</a></p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Model</label>
                                <select
                                    value={data.model}
                                    onChange={(e) => setData('model', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                >
                                    {models.map((m) => (
                                        <option key={m.value} value={m.value}>{m.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Max Tokens</label>
                                <input
                                    type="number"
                                    value={data.max_tokens}
                                    onChange={(e) => setData('max_tokens', parseInt(e.target.value) || 4096)}
                                    min="256"
                                    max="128000"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="w-4 h-4 text-violet-600 border-slate-300 rounded focus:ring-violet-500"
                                />
                                <span className="text-sm font-medium text-slate-700">Aktifkan Claude AI</span>
                            </label>
                            {data.is_active && (
                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">Aktif</span>
                            )}
                        </div>

                        <div className="flex items-center gap-3 pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 text-sm font-medium disabled:opacity-50"
                            >
                                {processing ? 'Menyimpan...' : 'Simpan Tetapan'}
                            </button>
                            <button
                                type="button"
                                onClick={handleTestConnection}
                                disabled={testingConnection}
                                className="px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 text-sm font-medium disabled:opacity-50 flex items-center gap-2"
                            >
                                <Wifi className="h-4 w-4" />
                                {testingConnection ? 'Menguji...' : 'Uji Sambungan'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Features Info */}
                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <Sparkles className="h-5 w-5 text-violet-500" />
                        Kegunaan Claude AI dalam SISDA
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="p-4 rounded-lg bg-violet-50 border border-violet-100">
                            <h3 className="text-sm font-semibold text-violet-800 mb-1">Pemprosesan PDF</h3>
                            <p className="text-xs text-violet-600">Baca dan ekstrak data dari dokumen PDF seperti Data Pengundi Tambahan (DPT) secara automatik.</p>
                        </div>
                        <div className="p-4 rounded-lg bg-sky-50 border border-sky-100">
                            <h3 className="text-sm font-semibold text-sky-800 mb-1">Analitik & Graf</h3>
                            <p className="text-xs text-sky-600">Analisis data pengundi dengan AI untuk menghasilkan laporan, trend, dan graf yang bermakna.</p>
                        </div>
                        <div className="p-4 rounded-lg bg-amber-50 border border-amber-100">
                            <h3 className="text-sm font-semibold text-amber-800 mb-1">Pengekstrakan Data</h3>
                            <p className="text-xs text-amber-600">Ekstrak maklumat penting dari dokumen dan masukkan terus ke dalam pangkalan data.</p>
                        </div>
                        <div className="p-4 rounded-lg bg-emerald-50 border border-emerald-100">
                            <h3 className="text-sm font-semibold text-emerald-800 mb-1">Ringkasan Pintar</h3>
                            <p className="text-xs text-emerald-600">Janakan ringkasan automatik dari data pengundi untuk membuat keputusan yang lebih baik.</p>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
