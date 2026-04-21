import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, LogIn, Eye, PencilLine, ShieldAlert, RefreshCw } from 'lucide-react';
import { useState } from 'react';

const SEVERITY_STYLES = {
    critical: 'bg-rose-100 text-rose-800 border-rose-200',
    high: 'bg-orange-100 text-orange-800 border-orange-200',
    medium: 'bg-amber-100 text-amber-800 border-amber-200',
    low: 'bg-slate-100 text-slate-700 border-slate-200',
};

const TYPE_META = {
    login: { label: 'Log Masuk', icon: LogIn, color: 'text-emerald-600' },
    view: { label: 'Lawatan', icon: Eye, color: 'text-sky-600' },
    edit: { label: 'Perubahan', icon: PencilLine, color: 'text-amber-600' },
};

const formatDateTime = (ts) => {
    if (!ts) return '-';
    try {
        return new Date(ts).toLocaleString('ms-MY', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return ts;
    }
};

export default function UserLogShow({ monitoredUser, timeline, alerts, filters }) {
    const [localFilters, setLocalFilters] = useState(filters);
    const [analyzing, setAnalyzing] = useState(false);

    const applyFilters = (next) => {
        setLocalFilters(next);
        router.get(route('user-log.show', monitoredUser.id), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleAnalyze = () => {
        setAnalyzing(true);
        router.post(route('user-log.analyze'), { user_id: monitoredUser.id }, {
            preserveScroll: true,
            onFinish: () => setAnalyzing(false),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Log: ${monitoredUser.name}`} />

            <div className="max-w-5xl mx-auto space-y-6">
                <div className="flex items-center gap-3">
                    <Link href={route('user-log.index')} className="p-2 hover:bg-slate-100 rounded-lg">
                        <ArrowLeft className="w-5 h-5 text-slate-600" />
                    </Link>
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold text-slate-900">{monitoredUser.name}</h1>
                        <p className="text-sm text-slate-500">
                            <span className="px-2 py-0.5 bg-slate-100 rounded text-xs font-mono">{monitoredUser.role}</span>
                            <span className="ml-2">{monitoredUser.email || monitoredUser.telephone}</span>
                        </p>
                    </div>
                    <button
                        onClick={handleAnalyze}
                        disabled={analyzing}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        <RefreshCw className={`w-4 h-4 ${analyzing ? 'animate-spin' : ''}`} />
                        Hantar Analisis / Amaran
                    </button>
                </div>

                {/* Meta card */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div className="bg-white rounded-xl border border-slate-200 p-4">
                        <p className="text-xs text-slate-500">Log Masuk Terakhir</p>
                        <p className="font-medium text-slate-900 mt-1">{formatDateTime(monitoredUser.last_login)}</p>
                    </div>
                    <div className="bg-white rounded-xl border border-slate-200 p-4">
                        <p className="text-xs text-slate-500">IP Log Masuk Terakhir</p>
                        <p className="font-mono text-sm text-slate-900 mt-1">{monitoredUser.last_login_ip || '-'}</p>
                    </div>
                    <div className="bg-white rounded-xl border border-slate-200 p-4">
                        <p className="text-xs text-slate-500">Bilangan Amaran</p>
                        <p className="font-medium text-slate-900 mt-1">{alerts.length} rekod</p>
                    </div>
                </div>

                {/* Date filter */}
                <div className="bg-white rounded-xl border border-slate-200 p-4 flex gap-3 flex-wrap items-end">
                    <div>
                        <label className="block text-xs text-slate-600 mb-1">Dari</label>
                        <input
                            type="date"
                            value={localFilters.date_from}
                            onChange={(e) => applyFilters({ ...localFilters, date_from: e.target.value })}
                            className="px-3 py-2 border border-slate-300 rounded-lg text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-xs text-slate-600 mb-1">Hingga</label>
                        <input
                            type="date"
                            value={localFilters.date_to}
                            onChange={(e) => applyFilters({ ...localFilters, date_to: e.target.value })}
                            className="px-3 py-2 border border-slate-300 rounded-lg text-sm"
                        />
                    </div>
                </div>

                {/* Alerts list */}
                {alerts.length > 0 && (
                    <div className="bg-white rounded-xl border border-slate-200">
                        <div className="px-4 py-3 border-b border-slate-100">
                            <h2 className="font-semibold text-slate-900 flex items-center gap-2">
                                <ShieldAlert className="w-4 h-4 text-rose-600" /> Amaran
                            </h2>
                        </div>
                        <div className="divide-y divide-slate-100">
                            {alerts.map((a) => (
                                <div key={a.id} className="p-4 flex items-start gap-3">
                                    <span className={`inline-flex items-center px-2 py-0.5 text-xs font-semibold uppercase rounded border ${SEVERITY_STYLES[a.severity] || SEVERITY_STYLES.low}`}>
                                        {a.severity}
                                    </span>
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2 text-xs text-slate-500 mb-1">
                                            <span className="font-mono">{a.rule_code}</span>
                                            <span>·</span>
                                            <span>{formatDateTime(a.created_at)}</span>
                                            {a.acknowledged_at && <span className="text-emerald-600">✓ Diakui</span>}
                                        </div>
                                        <p className="font-medium text-slate-800">{a.verdict || '-'}</p>
                                        {a.summary && <p className="text-sm text-slate-600 mt-1 whitespace-pre-wrap">{a.summary}</p>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Timeline */}
                <div className="bg-white rounded-xl border border-slate-200">
                    <div className="px-4 py-3 border-b border-slate-100">
                        <h2 className="font-semibold text-slate-900">Garis Masa Aktiviti</h2>
                        <p className="text-xs text-slate-500">{timeline.length} peristiwa</p>
                    </div>
                    {timeline.length === 0 ? (
                        <div className="p-8 text-center text-sm text-slate-500">Tiada aktiviti untuk tempoh ini.</div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {timeline.map((item, idx) => {
                                const meta = TYPE_META[item.type] || TYPE_META.view;
                                const Icon = meta.icon;
                                return (
                                    <li key={idx} className="p-3 flex items-start gap-3 hover:bg-slate-50">
                                        <div className={`p-1.5 rounded bg-slate-50 ${meta.color}`}>
                                            <Icon className="w-4 h-4" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 text-xs text-slate-500">
                                                <span>{formatDateTime(item.ts)}</span>
                                                <span className="px-1.5 py-0.5 bg-slate-100 rounded font-mono">{meta.label}</span>
                                            </div>
                                            {item.type === 'login' && (
                                                <p className="text-sm mt-0.5">
                                                    <span className="font-medium">{item.event}</span>
                                                    {item.ip && <span className="ml-2 font-mono text-xs text-slate-500">{item.ip}</span>}
                                                </p>
                                            )}
                                            {item.type === 'view' && (
                                                <p className="text-sm mt-0.5">
                                                    <span className="font-mono text-xs">{item.route_name || item.url}</span>
                                                    {item.ip && <span className="ml-2 font-mono text-xs text-slate-400">{item.ip}</span>}
                                                </p>
                                            )}
                                            {item.type === 'edit' && (
                                                <p className="text-sm mt-0.5">
                                                    <span className="font-mono text-xs">{item.model_type} #{item.model_id}</span>
                                                    <span className="ml-2 text-xs text-slate-500">{item.action}</span>
                                                    {item.fields_changed?.length > 0 && (
                                                        <span className="ml-2 text-xs text-slate-500">({item.fields_changed.join(', ')})</span>
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
