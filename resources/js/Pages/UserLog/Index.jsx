import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ShieldAlert, Activity, LogIn, Eye, PencilLine, RefreshCw, Check, X, Filter } from 'lucide-react';
import { useState } from 'react';

const SEVERITY_STYLES = {
    critical: 'bg-rose-100 text-rose-800 border-rose-200',
    high: 'bg-orange-100 text-orange-800 border-orange-200',
    medium: 'bg-amber-100 text-amber-800 border-amber-200',
    low: 'bg-slate-100 text-slate-700 border-slate-200',
};

const EVENT_LABELS = {
    login_success: 'Log Masuk Berjaya',
    login_failed: 'Log Masuk Gagal',
    logout: 'Log Keluar',
};

const formatDateTime = (ts) => {
    if (!ts) return '-';
    try {
        return new Date(ts).toLocaleString('ms-MY', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return ts;
    }
};

function SeverityPill({ severity }) {
    const cls = SEVERITY_STYLES[severity] || SEVERITY_STYLES.low;
    return (
        <span className={`inline-flex items-center px-2 py-0.5 text-xs font-semibold uppercase tracking-wide rounded border ${cls}`}>
            {severity}
        </span>
    );
}

function StatCard({ label, value, icon: Icon, tone = 'slate' }) {
    const tones = {
        slate: 'text-slate-700 bg-slate-50',
        rose: 'text-rose-700 bg-rose-50',
        amber: 'text-amber-700 bg-amber-50',
        emerald: 'text-emerald-700 bg-emerald-50',
    };
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 flex items-center gap-3">
            <div className={`p-2 rounded-lg ${tones[tone]}`}>
                <Icon className="w-5 h-5" />
            </div>
            <div>
                <p className="text-xs text-slate-500">{label}</p>
                <p className="text-xl font-semibold text-slate-900">{value}</p>
            </div>
        </div>
    );
}

export default function UserLogIndex({ alerts, logins, pageViews, edits, stats, users, filters, latestVerdict }) {
    const { auth } = usePage().props;
    const [tab, setTab] = useState(filters.tab || 'alerts');
    const [localFilters, setLocalFilters] = useState({
        user_id: filters.user_id || '',
        event: filters.event || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });
    const [analyzing, setAnalyzing] = useState(false);

    const applyFilters = (next) => {
        setLocalFilters(next);
        router.get(route('user-log.index'), { ...next, tab }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleAnalyze = () => {
        setAnalyzing(true);
        router.post(route('user-log.analyze'), localFilters.user_id ? { user_id: localFilters.user_id } : {}, {
            preserveScroll: true,
            onFinish: () => setAnalyzing(false),
        });
    };

    const handleAcknowledge = (id) => {
        router.post(route('user-log.alerts.acknowledge', id), {}, { preserveScroll: true });
    };

    const handleDeleteAlert = (id) => {
        if (!confirm('Padam amaran ini?')) return;
        router.delete(route('user-log.alerts.destroy', id), { preserveScroll: true });
    };

    const switchTab = (t) => {
        setTab(t);
        router.get(route('user-log.index'), { ...localFilters, tab: t }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Log Pengguna" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 flex items-center gap-2">
                            <ShieldAlert className="w-6 h-6 text-rose-600" /> Log Pengguna
                        </h1>
                        <p className="text-sm text-slate-600 mt-1">
                            Pemantauan log masuk, lawatan halaman, dan perubahan rekod bagi akaun <code className="px-1 bg-slate-100 rounded">user</code> dan <code className="px-1 bg-slate-100 rounded">super_user</code>. Amaran dijana oleh Claude AI.
                        </p>
                    </div>
                    <button
                        onClick={handleAnalyze}
                        disabled={analyzing}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        <RefreshCw className={`w-4 h-4 ${analyzing ? 'animate-spin' : ''}`} />
                        {analyzing ? 'Menganalisis…' : 'Analisis Sekarang'}
                    </button>
                </div>

                {/* Claude verdict banner */}
                {latestVerdict && (
                    <div className={`rounded-xl border-2 p-5 ${SEVERITY_STYLES[latestVerdict.severity] || SEVERITY_STYLES.low}`}>
                        <div className="flex items-start gap-3">
                            <ShieldAlert className="w-6 h-6 flex-shrink-0 mt-0.5" />
                            <div className="flex-1">
                                <div className="flex items-center gap-2 flex-wrap mb-1">
                                    <SeverityPill severity={latestVerdict.severity} />
                                    <span className="text-xs font-mono bg-white/50 px-2 py-0.5 rounded">{latestVerdict.rule_code}</span>
                                    {latestVerdict.user && (
                                        <Link href={route('user-log.show', latestVerdict.user.id)} className="text-xs underline">
                                            {latestVerdict.user.name} ({latestVerdict.user.role})
                                        </Link>
                                    )}
                                </div>
                                <p className="font-semibold text-base">{latestVerdict.verdict || 'Aktiviti mencurigakan dikesan'}</p>
                                {latestVerdict.summary && <p className="text-sm mt-1 whitespace-pre-wrap">{latestVerdict.summary}</p>}
                            </div>
                        </div>
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <StatCard label="Amaran Terbuka" value={stats.open_alerts} icon={ShieldAlert} tone="rose" />
                    <StatCard label="Amaran High/Critical" value={stats.high_alerts} icon={Activity} tone="amber" />
                    <StatCard label="Log Masuk (24j)" value={stats.logins_24h} icon={LogIn} />
                    <StatCard label="Lawatan Halaman (24j)" value={stats.page_views_24h} icon={Eye} />
                    <StatCard label="Perubahan Rekod (24j)" value={stats.edits_24h} icon={PencilLine} />
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl border border-slate-200 p-4">
                    <div className="flex items-center gap-2 mb-3 text-sm font-medium text-slate-700">
                        <Filter className="w-4 h-4" /> Penapis
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label className="block text-xs text-slate-600 mb-1">Pengguna</label>
                            <select
                                value={localFilters.user_id}
                                onChange={(e) => applyFilters({ ...localFilters, user_id: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                            >
                                <option value="">Semua pengguna</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name} ({u.role})</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs text-slate-600 mb-1">Jenis Log Masuk</label>
                            <select
                                value={localFilters.event}
                                onChange={(e) => applyFilters({ ...localFilters, event: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                            >
                                <option value="">Semua</option>
                                <option value="login_success">Berjaya</option>
                                <option value="login_failed">Gagal</option>
                                <option value="logout">Log Keluar</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs text-slate-600 mb-1">Dari Tarikh</label>
                            <input
                                type="date"
                                value={localFilters.date_from}
                                onChange={(e) => applyFilters({ ...localFilters, date_from: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-xs text-slate-600 mb-1">Hingga Tarikh</label>
                            <input
                                type="date"
                                value={localFilters.date_to}
                                onChange={(e) => applyFilters({ ...localFilters, date_to: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                            />
                        </div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="border-b border-slate-200 flex gap-1">
                    {[
                        { id: 'alerts', label: 'Amaran', icon: ShieldAlert, count: alerts.total },
                        { id: 'logins', label: 'Log Masuk', icon: LogIn, count: logins.total },
                        { id: 'views', label: 'Lawatan Halaman', icon: Eye, count: pageViews.total },
                        { id: 'edits', label: 'Perubahan Rekod', icon: PencilLine, count: edits.total },
                    ].map((t) => (
                        <button
                            key={t.id}
                            onClick={() => switchTab(t.id)}
                            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 ${
                                tab === t.id ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            <t.icon className="w-4 h-4" /> {t.label}
                            <span className="text-xs bg-slate-100 text-slate-600 rounded-full px-2 py-0.5">{t.count}</span>
                        </button>
                    ))}
                </div>

                {/* Tab content */}
                {tab === 'alerts' && <AlertsTable alerts={alerts} onAcknowledge={handleAcknowledge} onDelete={handleDeleteAlert} />}
                {tab === 'logins' && <LoginsTable logins={logins} />}
                {tab === 'views' && <PageViewsTable pageViews={pageViews} />}
                {tab === 'edits' && <EditsTable edits={edits} />}
            </div>
        </AuthenticatedLayout>
    );
}

function Pagination({ page }) {
    if (!page?.links) return null;
    return (
        <div className="flex flex-wrap gap-1 p-3 border-t border-slate-100 bg-slate-50 rounded-b-xl">
            {page.links.map((l, i) => (
                <Link
                    key={i}
                    href={l.url || '#'}
                    preserveScroll
                    preserveState
                    className={`px-3 py-1 text-xs rounded border ${l.active ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'} ${!l.url ? 'opacity-40 pointer-events-none' : ''}`}
                    dangerouslySetInnerHTML={{ __html: l.label }}
                />
            ))}
        </div>
    );
}

function AlertsTable({ alerts, onAcknowledge, onDelete }) {
    if (!alerts.data.length) {
        return <div className="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-500">Tiada amaran terbuka.</div>;
    }
    return (
        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-2 text-left">Tahap</th>
                            <th className="px-4 py-2 text-left">Peraturan</th>
                            <th className="px-4 py-2 text-left">Pengguna</th>
                            <th className="px-4 py-2 text-left">Ringkasan</th>
                            <th className="px-4 py-2 text-left">WA</th>
                            <th className="px-4 py-2 text-left">Masa</th>
                            <th className="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {alerts.data.map((a) => (
                            <tr key={a.id} className="hover:bg-slate-50">
                                <td className="px-4 py-2"><SeverityPill severity={a.severity} /></td>
                                <td className="px-4 py-2 font-mono text-xs">{a.rule_code}</td>
                                <td className="px-4 py-2">
                                    {a.user ? (
                                        <Link href={route('user-log.show', a.user.id)} className="text-sky-700 hover:underline">
                                            {a.user.name} <span className="text-xs text-slate-500">({a.user.role})</span>
                                        </Link>
                                    ) : <span className="text-slate-400 italic">—</span>}
                                </td>
                                <td className="px-4 py-2 max-w-md">
                                    <p className="font-medium text-slate-800">{a.verdict || '-'}</p>
                                    {a.summary && <p className="text-xs text-slate-500 line-clamp-2">{a.summary}</p>}
                                </td>
                                <td className="px-4 py-2 text-xs">
                                    <span className={`inline-block px-2 py-0.5 rounded ${a.whatsapp_status === 'sent' ? 'bg-emerald-100 text-emerald-700' : a.whatsapp_status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600'}`}>
                                        {a.whatsapp_status}
                                    </span>
                                </td>
                                <td className="px-4 py-2 text-xs text-slate-500 whitespace-nowrap">{formatDateTime(a.created_at)}</td>
                                <td className="px-4 py-2 text-right whitespace-nowrap">
                                    <button onClick={() => onAcknowledge(a.id)} className="text-emerald-700 hover:text-emerald-800 mr-2" title="Akui">
                                        <Check className="w-4 h-4 inline" />
                                    </button>
                                    <button onClick={() => onDelete(a.id)} className="text-rose-600 hover:text-rose-700" title="Padam">
                                        <X className="w-4 h-4 inline" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination page={alerts} />
        </div>
    );
}

function LoginsTable({ logins }) {
    if (!logins.data.length) {
        return <div className="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-500">Tiada log masuk untuk tempoh ini.</div>;
    }
    return (
        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-2 text-left">Masa</th>
                            <th className="px-4 py-2 text-left">Pengguna</th>
                            <th className="px-4 py-2 text-left">Acara</th>
                            <th className="px-4 py-2 text-left">IP</th>
                            <th className="px-4 py-2 text-left">User Agent</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {logins.data.map((l) => (
                            <tr key={l.id} className="hover:bg-slate-50">
                                <td className="px-4 py-2 text-xs text-slate-500 whitespace-nowrap">{formatDateTime(l.created_at)}</td>
                                <td className="px-4 py-2">
                                    {l.user ? (
                                        <Link href={route('user-log.show', l.user.id)} className="text-sky-700 hover:underline">
                                            {l.user.name} <span className="text-xs text-slate-500">({l.user.role})</span>
                                        </Link>
                                    ) : <span className="text-slate-400 italic">{l.email_attempted || '—'}</span>}
                                </td>
                                <td className="px-4 py-2">
                                    <span className={`px-2 py-0.5 text-xs rounded ${l.event === 'login_success' ? 'bg-emerald-100 text-emerald-700' : l.event === 'login_failed' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700'}`}>
                                        {EVENT_LABELS[l.event] || l.event}
                                    </span>
                                </td>
                                <td className="px-4 py-2 font-mono text-xs">{l.ip || '-'}</td>
                                <td className="px-4 py-2 text-xs text-slate-500 max-w-sm truncate" title={l.user_agent}>{l.user_agent || '-'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination page={logins} />
        </div>
    );
}

function PageViewsTable({ pageViews }) {
    if (!pageViews.data.length) {
        return <div className="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-500">Tiada lawatan halaman untuk tempoh ini.</div>;
    }
    return (
        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-2 text-left">Masa</th>
                            <th className="px-4 py-2 text-left">Pengguna</th>
                            <th className="px-4 py-2 text-left">Laluan</th>
                            <th className="px-4 py-2 text-left">URL</th>
                            <th className="px-4 py-2 text-left">IP</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {pageViews.data.map((v) => (
                            <tr key={v.id} className="hover:bg-slate-50">
                                <td className="px-4 py-2 text-xs text-slate-500 whitespace-nowrap">{formatDateTime(v.created_at)}</td>
                                <td className="px-4 py-2">
                                    {v.user ? (
                                        <Link href={route('user-log.show', v.user.id)} className="text-sky-700 hover:underline">
                                            {v.user.name}
                                        </Link>
                                    ) : '—'}
                                </td>
                                <td className="px-4 py-2 font-mono text-xs">{v.route_name || '-'}</td>
                                <td className="px-4 py-2 text-xs text-slate-500 max-w-md truncate" title={v.url}>{v.url}</td>
                                <td className="px-4 py-2 font-mono text-xs">{v.ip || '-'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination page={pageViews} />
        </div>
    );
}

function EditsTable({ edits }) {
    if (!edits.data.length) {
        return <div className="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-500">Tiada perubahan rekod untuk tempoh ini.</div>;
    }
    return (
        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-2 text-left">Masa</th>
                            <th className="px-4 py-2 text-left">Pengguna</th>
                            <th className="px-4 py-2 text-left">Jenis Rekod</th>
                            <th className="px-4 py-2 text-left">ID</th>
                            <th className="px-4 py-2 text-left">Tindakan</th>
                            <th className="px-4 py-2 text-left">Medan Ditukar</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {edits.data.map((e) => (
                            <tr key={e.id} className="hover:bg-slate-50">
                                <td className="px-4 py-2 text-xs text-slate-500 whitespace-nowrap">{formatDateTime(e.created_at)}</td>
                                <td className="px-4 py-2">
                                    {e.user ? (
                                        <Link href={route('user-log.show', e.user.id)} className="text-sky-700 hover:underline">
                                            {e.user.name}
                                        </Link>
                                    ) : '—'}
                                </td>
                                <td className="px-4 py-2 font-mono text-xs">{e.model_type}</td>
                                <td className="px-4 py-2 font-mono text-xs">{e.model_id}</td>
                                <td className="px-4 py-2">
                                    <span className={`px-2 py-0.5 text-xs rounded ${e.action === 'created' ? 'bg-emerald-100 text-emerald-700' : e.action === 'updated' ? 'bg-sky-100 text-sky-700' : 'bg-rose-100 text-rose-700'}`}>
                                        {e.action}
                                    </span>
                                </td>
                                <td className="px-4 py-2 text-xs text-slate-500">
                                    {e.changes ? Object.keys(e.changes).join(', ') : '-'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination page={edits} />
        </div>
    );
}
