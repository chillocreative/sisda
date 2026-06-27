import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import useDragScroll from '@/Hooks/useDragScroll';
import {
    Users,
    ClipboardList,
    TrendingUp,
    TrendingDown,
    MapPin,
    UserCheck,
    Award,
    Building2
} from 'lucide-react';
import {
    PieChart,
    Pie,
    BarChart,
    Bar,
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
    Cell
} from 'recharts';

export default function Dashboard({
    // Defaults are empty/zero so the dashboard only ever shows real system data
    // (never placeholder figures) if a prop is missing.
    totalPengundi = 0,
    kadunCount = 0,
    mpkkCount = 0,
    totalCulaan = 0,
    sokongan = {
        ph: 0,
        bn: 0,
        tidakPasti: 0,
    },
    bangsa = {
        melayu: 0,
        cina: 0,
        india: 0,
        lain: 0
    },
    umurDistribution = [],
    trendBulanan = [],
    mpkkStats = [],
    petugasStats = [],
    keanggotaan = { total: 0, wings: [] },
    jawatankuasa = { total: 0, jenis: [] },
    negeriList = [],
    bandarList = [],
    kadunList = [],
    mpkkList = []
}) {
    const scrollRef1 = useDragScroll();
    const scrollRef2 = useDragScroll();
    const [filters, setFilters] = useState({
        negeri: '',
        bandar: '',
        kadun: '',
        mpkk: '',
        tarikhDari: '',
        tarikhHingga: ''
    });

    // Prepare chart data
    const kecenderunganData = [
        { name: 'PH/BN', value: sokongan.ph, color: '#dc2626' },
        { name: 'BN/PN', value: sokongan.bn, color: '#f59e0b' },
        { name: 'Tidak Pasti', value: sokongan.tidakPasti, color: '#94a3b8' },
    ];

    const bangsaData = [
        { name: 'Melayu', jumlah: bangsa.melayu },
        { name: 'Cina', jumlah: bangsa.cina },
        { name: 'India', jumlah: bangsa.india },
        { name: 'Lain-lain', jumlah: bangsa.lain },
    ];

    const sokonganMpkkData = mpkkStats.map(item => ({
        mpkk: item.mpkk,
        'PH/BN': item.ph,
        'BN/PN': item.bn,
        'Tidak Pasti': item.tidakPasti
    }));

    const COLORS = {
        primary: '#10b981',
        secondary: '#f59e0b',
        tertiary: '#3b82f6',
        neutral: '#94a3b8',
        danger: '#ef4444'
    };

    // Apply filters automatically whenever a control changes (no "Tapis" click).
    const applyFilters = (next = {}) => {
        const merged = { ...filters, ...next };
        setFilters(merged);
        router.get(route('dashboard'), {
            negeri_id: merged.negeri,
            bandar_id: merged.bandar,
            kadun_id: merged.kadun,
            mpkk_id: merged.mpkk,
            tarikh_dari: merged.tarikhDari,
            tarikh_hingga: merged.tarikhHingga
        }, {
            preserveState: true,
            preserveScroll: true
        });
    };

    const handleReset = () => {
        setFilters({
            negeri: '',
            bandar: '',
            kadun: '',
            mpkk: '',
            tarikhDari: '',
            tarikhHingga: ''
        });
        router.get(route('dashboard'), {}, {
            preserveState: true,
            preserveScroll: true
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Dashboard</h1>
                        <p className="text-sm text-slate-600 mt-1">Ringkasan data dan statistik SISDA</p>
                    </div>
                </div>

                {/* Filters — apply automatically on change; default scope = seluruh Malaysia */}
                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">Negeri</label>
                            <select
                                value={filters.negeri}
                                onChange={(e) => applyFilters({ negeri: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            >
                                <option value="">Semua Negeri</option>
                                {negeriList.map((negeri) => (
                                    <option key={negeri.id} value={negeri.id}>{negeri.nama}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">Bandar (Parlimen)</label>
                            <select
                                value={filters.bandar}
                                onChange={(e) => applyFilters({ bandar: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            >
                                <option value="">Semua Bandar</option>
                                {bandarList.map((bandar) => (
                                    <option key={bandar.id} value={bandar.id}>{bandar.nama}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">KADUN</label>
                            <select
                                value={filters.kadun}
                                onChange={(e) => applyFilters({ kadun: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            >
                                <option value="">Semua KADUN</option>
                                {kadunList.map((kadun) => (
                                    <option key={kadun.id} value={kadun.id}>{kadun.nama}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">MPKK</label>
                            <select
                                value={filters.mpkk}
                                onChange={(e) => applyFilters({ mpkk: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            >
                                <option value="">Semua MPKK</option>
                                {mpkkList.map((mpkk) => (
                                    <option key={mpkk.id} value={mpkk.id}>{mpkk.nama}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">Tarikh Dari</label>
                            <input
                                type="date"
                                value={filters.tarikhDari}
                                onChange={(e) => applyFilters({ tarikhDari: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">Tarikh Hingga</label>
                            <input
                                type="date"
                                value={filters.tarikhHingga}
                                onChange={(e) => applyFilters({ tarikhHingga: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            />
                        </div>
                    </div>
                    <div className="flex items-center justify-end mt-4">
                        <button
                            onClick={handleReset}
                            className="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                        >
                            Set Semula
                        </button>
                    </div>
                </div>

                {/* Metric Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {/* Total Pengundi */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-600">Jumlah Pengundi</p>
                                <p className="text-3xl font-bold text-slate-900 mt-2">{totalPengundi.toLocaleString()}</p>
                            </div>
                            <div className="p-3 bg-emerald-100 rounded-lg">
                                <Users className="h-6 w-6 text-emerald-600" />
                            </div>
                        </div>
                    </div>

                    {/* Total Culaan */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-600">Jumlah Hasil Culaan</p>
                                <p className="text-3xl font-bold text-slate-900 mt-2">{totalCulaan.toLocaleString()}</p>
                            </div>
                            <div className="p-3 bg-sky-100 rounded-lg">
                                <ClipboardList className="h-6 w-6 text-sky-600" />
                            </div>
                        </div>
                    </div>

                    {/* PH/BN Support */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-600">Sokongan PH/BN</p>
                                <p className="text-3xl font-bold text-red-600 mt-2">{sokongan.ph}%</p>
                            </div>
                            <div className="p-3 bg-red-100 rounded-lg">
                                <TrendingUp className="h-6 w-6 text-red-600" />
                            </div>
                        </div>
                    </div>

                    {/* BN/PN Support */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-600">Sokongan BN/PN</p>
                                <p className="text-3xl font-bold text-amber-600 mt-2">{sokongan.bn}%</p>
                            </div>
                            <div className="p-3 bg-amber-100 rounded-lg">
                                <TrendingDown className="h-6 w-6 text-amber-600" />
                            </div>
                        </div>
                    </div>

                    {/* MPKK Count */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-600">Bilangan KADUN</p>
                                <p className="text-3xl font-bold text-slate-900 mt-2">{kadunCount.toLocaleString()}</p>
                            </div>
                            <div className="p-3 bg-purple-100 rounded-lg">
                                <MapPin className="h-6 w-6 text-purple-600" />
                            </div>
                        </div>
                    </div>

                    {/* Ahli Keanggotaan */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-600">Ahli Keanggotaan</p>
                                <p className="text-3xl font-bold text-slate-900 mt-2">{(keanggotaan.total || 0).toLocaleString()}</p>
                            </div>
                            <div className="p-3 bg-rose-100 rounded-lg">
                                <UserCheck className="h-6 w-6 text-rose-600" />
                            </div>
                        </div>
                    </div>

                    {/* Ahli Jawatankuasa */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-600">Ahli Jawatankuasa</p>
                                <p className="text-3xl font-bold text-slate-900 mt-2">{(jawatankuasa.total || 0).toLocaleString()}</p>
                            </div>
                            <div className="p-3 bg-indigo-100 rounded-lg">
                                <Award className="h-6 w-6 text-indigo-600" />
                            </div>
                        </div>
                    </div>

                    {/* Bilangan MPKK */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-600">Bilangan MPKK</p>
                                <p className="text-3xl font-bold text-slate-900 mt-2">{mpkkCount.toLocaleString()}</p>
                            </div>
                            <div className="p-3 bg-teal-100 rounded-lg">
                                <Building2 className="h-6 w-6 text-teal-600" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Charts Section */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Kecenderungan Politik - Donut Chart */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900 mb-4">Kecenderungan Politik</h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <PieChart>
                                <Pie
                                    data={kecenderunganData}
                                    cx="50%"
                                    cy="50%"
                                    innerRadius={60}
                                    outerRadius={100}
                                    paddingAngle={2}
                                    dataKey="value"
                                >
                                    {kecenderunganData.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={entry.color} />
                                    ))}
                                </Pie>
                                <Tooltip />
                                <Legend />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Bangsa Distribution - Bar Chart */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900 mb-4">Taburan Bangsa</h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={bangsaData}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                                <XAxis dataKey="name" stroke="#64748b" />
                                <YAxis stroke="#64748b" />
                                <Tooltip />
                                <Bar dataKey="jumlah" fill="#3b82f6" radius={[8, 8, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Umur Distribution - Bar Chart */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900 mb-4">Taburan Umur</h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={umurDistribution}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                                <XAxis dataKey="range" stroke="#64748b" />
                                <YAxis stroke="#64748b" />
                                <Tooltip />
                                <Bar dataKey="jumlah" fill="#10b981" radius={[8, 8, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Trend Bulanan - Line Chart */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900 mb-4">Trend Pengumpulan Data Bulanan</h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <LineChart data={trendBulanan}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                                <XAxis dataKey="bulan" stroke="#64748b" />
                                <YAxis stroke="#64748b" />
                                <Tooltip />
                                <Line
                                    type="monotone"
                                    dataKey="jumlah"
                                    stroke="#8b5cf6"
                                    strokeWidth={2}
                                    dot={{ fill: '#8b5cf6', r: 4 }}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Keanggotaan & Jawatankuasa breakdowns */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Keanggotaan Mengikut Sayap */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900 mb-4">Keanggotaan Mengikut Sayap</h3>
                        {keanggotaan.wings.length === 0 ? (
                            <p className="text-sm text-slate-500 py-24 text-center">Tiada data keanggotaan.</p>
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart data={keanggotaan.wings}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                                    <XAxis dataKey="name" stroke="#64748b" />
                                    <YAxis stroke="#64748b" />
                                    <Tooltip formatter={(v) => v.toLocaleString()} />
                                    <Bar dataKey="jumlah" radius={[8, 8, 0, 0]}>
                                        {keanggotaan.wings.map((entry) => (
                                            <Cell
                                                key={entry.name}
                                                fill={{ AMK: '#2563eb', Srikandi: '#db2777', Wanita: '#9333ea' }[entry.name] || '#94a3b8'}
                                            />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </div>

                    {/* Jawatankuasa Mengikut Jenis */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900 mb-4">Jawatankuasa Mengikut Jenis</h3>
                        {jawatankuasa.jenis.length === 0 ? (
                            <p className="text-sm text-slate-500 py-24 text-center">Tiada data jawatankuasa.</p>
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart data={jawatankuasa.jenis}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                                    <XAxis dataKey="name" stroke="#64748b" />
                                    <YAxis stroke="#64748b" />
                                    <Tooltip formatter={(v) => v.toLocaleString()} />
                                    <Bar dataKey="jumlah" fill="#4f46e5" radius={[8, 8, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </div>
                </div>

                {/* Sokongan Politik Mengikut MPKK - Stacked Bar */}
                <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <h3 className="text-lg font-semibold text-slate-900 mb-4">Sokongan Politik Mengikut DUN</h3>
                    <ResponsiveContainer width="100%" height={350}>
                        <BarChart data={sokonganMpkkData}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                            <XAxis dataKey="mpkk" stroke="#64748b" />
                            <YAxis stroke="#64748b" />
                            <Tooltip />
                            <Legend />
                            <Bar dataKey="PH/BN" stackId="a" fill="#dc2626" radius={[0, 0, 0, 0]} />
                            <Bar dataKey="BN/PN" stackId="a" fill="#f59e0b" radius={[0, 0, 0, 0]} />
                            <Bar dataKey="Tidak Pasti" stackId="a" fill="#94a3b8" radius={[8, 8, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                </div>

                {/* Tables Section */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Kawasan Paling Aktif */}
                    <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                        <div className="p-6 border-b border-slate-200">
                            <h3 className="text-lg font-semibold text-slate-900">Kawasan Paling Aktif</h3>
                        </div>
                        <div ref={scrollRef1} className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">MPKK</th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Pengundi</th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Rekod</th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">PH/BN</th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">BN/PN</th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Tidak Pasti</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {mpkkStats.map((item, index) => (
                                        <tr key={index} className="hover:bg-slate-50 transition-colors">
                                            <td className="py-3 px-4 text-sm font-medium text-slate-900">{item.mpkk}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.pengundi.toLocaleString()}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.culaan.toLocaleString()}</td>
                                            <td className="py-3 px-4 text-sm text-red-600 font-medium">{item.ph}%</td>
                                            <td className="py-3 px-4 text-sm text-amber-600 font-medium">{item.bn}%</td>
                                            <td className="py-3 px-4 text-sm text-slate-600 font-medium">{item.tidakPasti}%</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Petugas Teraktif */}
                    <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                        <div className="p-6 border-b border-slate-200">
                            <h3 className="text-lg font-semibold text-slate-900">Petugas Teraktif</h3>
                        </div>
                        <div ref={scrollRef2} className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Nama Petugas</th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Jumlah Rekod</th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Kawasan</th>
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Tarikh Terakhir</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {petugasStats.map((item, index) => (
                                        <tr key={index} className="hover:bg-slate-50 transition-colors">
                                            <td className="py-3 px-4 text-sm font-medium text-slate-900">{item.nama}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.jumlah}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.kawasan}</td>
                                            <td className="py-3 px-4 text-sm text-slate-600">{item.tarikh}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
