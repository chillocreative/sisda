import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { Settings, Wifi, Send, MessageSquare, CheckCircle, XCircle, Phone } from 'lucide-react';
import { useState } from 'react';

export default function Sendora({ settings, recentMessages }) {
    const { flash = {} } = usePage().props;
    const [devices, setDevices] = useState([]);
    const [testingConnection, setTestingConnection] = useState(false);

    const { data, setData, post, processing } = useForm({
        api_url: settings?.api_url || 'https://sendora.app',
        api_token: settings?.api_token || '',
        device_id: settings?.device_id || '',
        admin_phone: settings?.admin_phone || '',
        is_active: settings?.is_active || false,
    });

    const { data: testData, setData: setTestData, post: postTest, processing: testProcessing } = useForm({
        phone: '',
        message: '',
    });

    const handleSave = (e) => {
        e.preventDefault();
        post(route('settings.sendora.update'));
    };

    const handleTestConnection = () => {
        setTestingConnection(true);
        router.post(route('settings.sendora.test'), {
            api_url: data.api_url,
            api_token: data.api_token,
        }, {
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.props.flash?.devices) {
                    setDevices(page.props.flash.devices);
                }
            },
            onFinish: () => setTestingConnection(false),
        });
    };

    const handleTestSend = (e) => {
        e.preventDefault();
        postTest(route('settings.sendora.test-send'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Tetapan Sendora" />

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex items-center gap-3 mb-8">
                    <div className="p-2 bg-emerald-100 rounded-lg">
                        <MessageSquare className="h-6 w-6 text-emerald-600" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Tetapan Sendora WhatsApp</h1>
                        <p className="text-sm text-slate-500">Konfigurasi API WhatsApp untuk penghantaran mesej</p>
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
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">API URL</label>
                                <input
                                    type="url"
                                    value={data.api_url}
                                    onChange={(e) => setData('api_url', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">API Token</label>
                                <input
                                    type="password"
                                    value={data.api_token}
                                    onChange={(e) => setData('api_token', e.target.value)}
                                    placeholder={settings?.has_token ? '••••••••' : 'Masukkan API token'}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Device ID</label>
                                {devices.length > 0 ? (
                                    <select
                                        value={data.device_id}
                                        onChange={(e) => setData('device_id', e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    >
                                        <option value="">Pilih Device</option>
                                        {devices.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.phone_number} ({d.status})
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <input
                                        type="number"
                                        value={data.device_id}
                                        onChange={(e) => setData('device_id', e.target.value)}
                                        placeholder="Tekan 'Uji Sambungan' untuk dapatkan senarai"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    />
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">No. Telefon Admin</label>
                                <input
                                    type="tel"
                                    value={data.admin_phone}
                                    onChange={(e) => setData('admin_phone', e.target.value)}
                                    placeholder="0123456789"
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
                                    className="w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500"
                                />
                                <span className="text-sm font-medium text-slate-700">Aktifkan Sendora</span>
                            </label>
                            {data.is_active && (
                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Aktif</span>
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
                                className="px-4 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700 text-sm font-medium disabled:opacity-50 flex items-center gap-2"
                            >
                                <Wifi className="h-4 w-4" />
                                {testingConnection ? 'Menguji...' : 'Uji Sambungan'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Test Send */}
                <div className="bg-white rounded-xl border border-slate-200 p-6 mb-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <Send className="h-5 w-5" />
                        Hantar Mesej Ujian
                    </h2>
                    <form onSubmit={handleTestSend} className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">No. Telefon</label>
                                <input
                                    type="tel"
                                    value={testData.phone}
                                    onChange={(e) => setTestData('phone', e.target.value)}
                                    placeholder="0123456789"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Mesej</label>
                                <input
                                    type="text"
                                    value={testData.message}
                                    onChange={(e) => setTestData('message', e.target.value)}
                                    placeholder="Mesej ujian dari SISDA"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                            </div>
                        </div>
                        <button
                            type="submit"
                            disabled={testProcessing}
                            className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium disabled:opacity-50 flex items-center gap-2"
                        >
                            <Send className="h-4 w-4" />
                            {testProcessing ? 'Menghantar...' : 'Hantar Ujian'}
                        </button>
                    </form>
                </div>

                {/* Recent Messages */}
                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <Phone className="h-5 w-5" />
                        Mesej Terkini
                    </h2>
                    {recentMessages.length === 0 ? (
                        <p className="text-sm text-slate-500">Tiada mesej lagi.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200">
                                        <th className="text-left py-2 px-3 text-xs font-semibold text-slate-600">Telefon</th>
                                        <th className="text-left py-2 px-3 text-xs font-semibold text-slate-600">Mesej</th>
                                        <th className="text-left py-2 px-3 text-xs font-semibold text-slate-600">Jenis</th>
                                        <th className="text-left py-2 px-3 text-xs font-semibold text-slate-600">Status</th>
                                        <th className="text-left py-2 px-3 text-xs font-semibold text-slate-600">Tarikh</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recentMessages.map((msg) => (
                                        <tr key={msg.id}>
                                            <td className="py-2 px-3 font-mono text-xs">{msg.phone}</td>
                                            <td className="py-2 px-3 text-slate-600 max-w-[200px] truncate">{msg.message}</td>
                                            <td className="py-2 px-3">
                                                <span className="px-1.5 py-0.5 rounded text-xs bg-slate-100 text-slate-600">{msg.type}</span>
                                            </td>
                                            <td className="py-2 px-3">
                                                <span className={`px-1.5 py-0.5 rounded text-xs font-medium ${msg.status === 'sent' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`}>
                                                    {msg.status === 'sent' ? 'Berjaya' : 'Gagal'}
                                                </span>
                                            </td>
                                            <td className="py-2 px-3 text-xs text-slate-500">
                                                {new Date(msg.created_at).toLocaleDateString('ms-MY', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
