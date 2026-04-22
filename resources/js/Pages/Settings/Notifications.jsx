import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router, Link } from '@inertiajs/react';
import {
    MessageSquare,
    Plus,
    Edit3,
    Trash2,
    Copy,
    Star,
    ToggleLeft,
    ToggleRight,
    Send,
    Eye,
    X,
    CheckCircle,
    XCircle,
    Search,
    Variable,
} from 'lucide-react';
import { useMemo, useState } from 'react';

const categoryOrder = ['whatsapp', 'password_reset', 'system'];

const extractVars = (body) => {
    const matches = (body || '').match(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g) || [];
    return [...new Set(matches.map((m) => m.replace(/[{}\s]/g, '')))];
};

const renderPreview = (body, vars) => {
    let out = body || '';
    Object.entries(vars || {}).forEach(([k, v]) => {
        if (v === null || v === undefined || v === '') return;
        const re = new RegExp(`\\{\\{\\s*${k}\\s*\\}\\}`, 'g');
        out = out.replace(re, v);
    });
    return out;
};

const emptyForm = (category = 'whatsapp') => ({
    id: null,
    category,
    code: '',
    name: '',
    description: '',
    body: '',
    is_active: true,
    is_default: false,
    sort_order: 0,
});

export default function Notifications({ templates, categories, activeCategory }) {
    const { flash = {} } = usePage().props;
    const [tab, setTab] = useState(activeCategory && categories[activeCategory] ? activeCategory : categoryOrder[0]);
    const [editing, setEditing] = useState(null);
    const [search, setSearch] = useState('');
    const [previewTpl, setPreviewTpl] = useState(null);
    const [testModal, setTestModal] = useState(null);

    const filtered = useMemo(() => {
        const list = templates.filter((t) => t.category === tab);
        if (!search.trim()) return list;
        const q = search.toLowerCase();
        return list.filter(
            (t) =>
                t.name.toLowerCase().includes(q) ||
                (t.code || '').toLowerCase().includes(q) ||
                (t.description || '').toLowerCase().includes(q) ||
                (t.body || '').toLowerCase().includes(q),
        );
    }, [templates, tab, search]);

    const counts = useMemo(() => {
        const out = {};
        categoryOrder.forEach((c) => {
            out[c] = templates.filter((t) => t.category === c).length;
        });
        return out;
    }, [templates]);

    const startNew = () => setEditing({ ...emptyForm(tab) });
    const startEdit = (tpl) =>
        setEditing({
            id: tpl.id,
            category: tpl.category,
            code: tpl.code,
            name: tpl.name,
            description: tpl.description || '',
            body: tpl.body || '',
            is_active: !!tpl.is_active,
            is_default: !!tpl.is_default,
            sort_order: tpl.sort_order || 0,
        });

    const onDelete = (tpl) => {
        if (!confirm(`Padam templat "${tpl.name}"?`)) return;
        router.delete(route('settings.notifications.destroy', tpl.id), { preserveScroll: true });
    };

    const onDuplicate = (tpl) => {
        router.post(route('settings.notifications.duplicate', tpl.id), {}, { preserveScroll: true });
    };

    const onToggle = (tpl) => {
        router.post(route('settings.notifications.toggle', tpl.id), {}, { preserveScroll: true });
    };

    const onMakeDefault = (tpl) => {
        router.post(route('settings.notifications.make-default', tpl.id), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Templat Notifikasi" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-sky-100 rounded-lg">
                            <MessageSquare className="h-6 w-6 text-sky-600" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">Templat Notifikasi</h1>
                            <p className="text-sm text-slate-500">
                                Urus templat WhatsApp, set semula kata laluan dan notifikasi sistem.
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={startNew}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 text-sm font-medium"
                    >
                        <Plus className="h-4 w-4" /> Templat Baharu
                    </button>
                </div>

                {flash?.success && (
                    <div className="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700 flex items-center gap-2">
                        <CheckCircle className="h-4 w-4" />
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700 flex items-center gap-2">
                        <XCircle className="h-4 w-4" />
                        {flash.error}
                    </div>
                )}

                {/* Tabs */}
                <div className="bg-white rounded-xl border border-slate-200 p-2 flex items-center gap-1 mb-4 overflow-x-auto">
                    {categoryOrder.map((c) => (
                        <button
                            key={c}
                            onClick={() => setTab(c)}
                            className={`px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 whitespace-nowrap ${
                                tab === c ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100'
                            }`}
                        >
                            {categories[c]}
                            <span
                                className={`px-1.5 py-0.5 rounded text-xs ${
                                    tab === c ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'
                                }`}
                            >
                                {counts[c] || 0}
                            </span>
                        </button>
                    ))}
                    <div className="flex-1" />
                    <div className="relative">
                        <Search className="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari templat..."
                            className="pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 w-56"
                        />
                    </div>
                </div>

                {/* List */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    {filtered.length === 0 && (
                        <div className="col-span-full bg-white rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                            Tiada templat dalam kategori ini.
                        </div>
                    )}
                    {filtered.map((tpl) => (
                        <div
                            key={tpl.id}
                            className={`bg-white rounded-xl border p-4 flex flex-col ${
                                tpl.is_active ? 'border-slate-200' : 'border-slate-200 opacity-60'
                            }`}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <h3 className="text-sm font-semibold text-slate-900 truncate">{tpl.name}</h3>
                                        {tpl.is_default && (
                                            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700">
                                                <Star className="h-3 w-3" /> Lalai
                                            </span>
                                        )}
                                        {!tpl.is_active && (
                                            <span className="px-1.5 py-0.5 rounded text-xs bg-slate-100 text-slate-500">
                                                Tidak Aktif
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500 font-mono truncate">{tpl.code}</p>
                                    {tpl.description && (
                                        <p className="mt-1 text-xs text-slate-600 line-clamp-1">{tpl.description}</p>
                                    )}
                                </div>
                            </div>

                            <pre className="mt-3 bg-slate-50 border border-slate-100 rounded-lg p-2 text-xs text-slate-700 max-h-24 overflow-hidden whitespace-pre-wrap line-clamp-5">
                                {tpl.body}
                            </pre>

                            {(tpl.variables || []).length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-1">
                                    {(tpl.variables || []).slice(0, 8).map((v) => (
                                        <span
                                            key={v}
                                            className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs bg-sky-50 text-sky-700"
                                        >
                                            <Variable className="h-3 w-3" />
                                            {v}
                                        </span>
                                    ))}
                                </div>
                            )}

                            <div className="mt-3 pt-3 border-t border-slate-100 flex items-center gap-1 flex-wrap">
                                <button
                                    onClick={() => setPreviewTpl(tpl)}
                                    className="inline-flex items-center gap-1 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100 rounded"
                                    title="Pratonton"
                                >
                                    <Eye className="h-3.5 w-3.5" /> Pratonton
                                </button>
                                <button
                                    onClick={() => startEdit(tpl)}
                                    className="inline-flex items-center gap-1 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100 rounded"
                                >
                                    <Edit3 className="h-3.5 w-3.5" /> Edit
                                </button>
                                <button
                                    onClick={() => setTestModal(tpl)}
                                    className="inline-flex items-center gap-1 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50 rounded"
                                >
                                    <Send className="h-3.5 w-3.5" /> Uji Hantar
                                </button>
                                <button
                                    onClick={() => onDuplicate(tpl)}
                                    className="inline-flex items-center gap-1 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100 rounded"
                                >
                                    <Copy className="h-3.5 w-3.5" /> Salin
                                </button>
                                {!tpl.is_default && (
                                    <button
                                        onClick={() => onMakeDefault(tpl)}
                                        className="inline-flex items-center gap-1 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50 rounded"
                                    >
                                        <Star className="h-3.5 w-3.5" /> Jadikan Lalai
                                    </button>
                                )}
                                <button
                                    onClick={() => onToggle(tpl)}
                                    className="inline-flex items-center gap-1 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100 rounded"
                                >
                                    {tpl.is_active ? (
                                        <>
                                            <ToggleRight className="h-3.5 w-3.5" /> Nyahaktif
                                        </>
                                    ) : (
                                        <>
                                            <ToggleLeft className="h-3.5 w-3.5" /> Aktif
                                        </>
                                    )}
                                </button>
                                <button
                                    onClick={() => onDelete(tpl)}
                                    className="inline-flex items-center gap-1 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 rounded ml-auto"
                                >
                                    <Trash2 className="h-3.5 w-3.5" /> Padam
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {editing && <EditModal editing={editing} setEditing={setEditing} categories={categories} />}
            {previewTpl && <PreviewModal tpl={previewTpl} onClose={() => setPreviewTpl(null)} />}
            {testModal && <TestSendModal tpl={testModal} onClose={() => setTestModal(null)} />}
        </AuthenticatedLayout>
    );
}

function EditModal({ editing, setEditing, categories }) {
    const isNew = !editing.id;
    const { data, setData, post, put, processing, errors } = useForm(editing);

    const submit = (e) => {
        e.preventDefault();
        if (isNew) {
            post(route('settings.notifications.store'), {
                preserveScroll: true,
                onSuccess: () => setEditing(null),
            });
        } else {
            put(route('settings.notifications.update', editing.id), {
                preserveScroll: true,
                onSuccess: () => setEditing(null),
            });
        }
    };

    const livePreviewVars = useMemo(() => {
        const obj = {};
        extractVars(data.body).forEach((v) => (obj[v] = `{${v}}`));
        return obj;
    }, [data.body]);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-slate-900/40" onClick={() => setEditing(null)} />
            <div className="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                    <h2 className="text-lg font-semibold text-slate-900">
                        {isNew ? 'Templat Baharu' : 'Edit Templat'}
                    </h2>
                    <button onClick={() => setEditing(null)} className="text-slate-400 hover:text-slate-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <form onSubmit={submit} className="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Kategori</label>
                            <select
                                value={data.category}
                                onChange={(e) => setData('category', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            >
                                {Object.entries(categories).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                            {errors.category && <p className="mt-1 text-xs text-rose-600">{errors.category}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                Kod (unik, pilihan)
                            </label>
                            <input
                                type="text"
                                value={data.code || ''}
                                onChange={(e) => setData('code', e.target.value)}
                                placeholder="contoh: wa_my_event"
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 font-mono text-sm"
                            />
                            {errors.code && <p className="mt-1 text-xs text-rose-600">{errors.code}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Nama</label>
                        <input
                            type="text"
                            value={data.name || ''}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                        />
                        {errors.name && <p className="mt-1 text-xs text-rose-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">
                            Keterangan (pilihan)
                        </label>
                        <input
                            type="text"
                            value={data.description || ''}
                            onChange={(e) => setData('description', e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                        />
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                Isi Mesej
                                <span className="ml-1 text-xs text-slate-400">
                                    — gunakan <code>{'{{nama}}'}</code> untuk pemboleh ubah
                                </span>
                            </label>
                            <textarea
                                value={data.body || ''}
                                onChange={(e) => setData('body', e.target.value)}
                                rows={14}
                                required
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 font-mono text-sm"
                            />
                            {errors.body && <p className="mt-1 text-xs text-rose-600">{errors.body}</p>}
                            <p className="mt-1 text-xs text-slate-500">
                                Dikesan: {extractVars(data.body).join(', ') || 'tiada'}
                            </p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Pratonton</label>
                            <pre className="bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm text-slate-700 whitespace-pre-wrap h-[calc(100%-1.75rem)] min-h-[20rem] overflow-auto">
                                {renderPreview(data.body, livePreviewVars)}
                            </pre>
                        </div>
                    </div>

                    <div className="flex items-center gap-4 flex-wrap">
                        <label className="flex items-center gap-2 cursor-pointer text-sm">
                            <input
                                type="checkbox"
                                checked={!!data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="w-4 h-4 text-emerald-600 rounded"
                            />
                            Aktif
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer text-sm">
                            <input
                                type="checkbox"
                                checked={!!data.is_default}
                                onChange={(e) => setData('is_default', e.target.checked)}
                                className="w-4 h-4 text-amber-600 rounded"
                            />
                            Jadikan lalai untuk kategori ini
                        </label>
                        <div className="flex items-center gap-2 text-sm">
                            <label className="text-slate-700">Susunan</label>
                            <input
                                type="number"
                                min={0}
                                value={data.sort_order || 0}
                                onChange={(e) => setData('sort_order', Number(e.target.value) || 0)}
                                className="w-24 px-2 py-1 border border-slate-300 rounded"
                            />
                        </div>
                    </div>
                </form>
                <div className="border-t border-slate-200 px-6 py-4 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={() => setEditing(null)}
                        className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        disabled={processing}
                        onClick={submit}
                        className="px-4 py-2 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        {processing ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function PreviewModal({ tpl, onClose }) {
    const vars = tpl.variables || extractVars(tpl.body);
    const [values, setValues] = useState(() => Object.fromEntries(vars.map((v) => [v, ''])));

    const rendered = useMemo(() => {
        const filled = {};
        vars.forEach((v) => {
            filled[v] = values[v]?.trim() || `{${v}}`;
        });
        return renderPreview(tpl.body, filled);
    }, [tpl.body, vars, values]);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
            <div className="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                    <h2 className="text-lg font-semibold text-slate-900">Pratonton — {tpl.name}</h2>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                    {vars.length > 0 && (
                        <div className="grid grid-cols-2 gap-2">
                            {vars.map((v) => (
                                <div key={v}>
                                    <label className="block text-xs font-medium text-slate-600 mb-1">{v}</label>
                                    <input
                                        value={values[v] || ''}
                                        onChange={(e) => setValues({ ...values, [v]: e.target.value })}
                                        placeholder={`{${v}}`}
                                        className="w-full px-2 py-1 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-slate-400"
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Hasil</label>
                        <pre className="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-sm text-slate-800 whitespace-pre-wrap">
                            {rendered}
                        </pre>
                    </div>
                </div>
            </div>
        </div>
    );
}

function TestSendModal({ tpl, onClose }) {
    const vars = tpl.variables || extractVars(tpl.body);
    const { data, setData, post, processing, errors } = useForm({
        phone: '',
        variables: Object.fromEntries(vars.map((v) => [v, ''])),
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('settings.notifications.test-send', tpl.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
            <div className="relative bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden">
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                    <h2 className="text-lg font-semibold text-slate-900">Hantar Mesej Ujian</h2>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <form onSubmit={submit} className="flex-1 overflow-y-auto px-6 py-4 space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">No. Telefon</label>
                        <input
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder="0123456789"
                            required
                            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400"
                        />
                        {errors.phone && <p className="mt-1 text-xs text-rose-600">{errors.phone}</p>}
                    </div>
                    {vars.length > 0 && (
                        <div>
                            <p className="text-sm font-medium text-slate-700 mb-1">Nilai Pemboleh Ubah</p>
                            <div className="grid grid-cols-2 gap-2">
                                {vars.map((v) => (
                                    <div key={v}>
                                        <label className="block text-xs text-slate-600 mb-0.5">{v}</label>
                                        <input
                                            value={data.variables[v] || ''}
                                            onChange={(e) =>
                                                setData('variables', { ...data.variables, [v]: e.target.value })
                                            }
                                            className="w-full px-2 py-1 text-sm border border-slate-300 rounded"
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                    <div className="pt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50"
                        >
                            <Send className="h-4 w-4" />
                            {processing ? 'Menghantar...' : 'Hantar Ujian'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
