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
    ChevronDown,
    Filter
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
    totalPengundi = 15820,
    totalCulaan = 5240,
    sokongan = {
        ph: 38,
        bn: 28,
        pn: 22,
        tidakPasti: 12,
    },
    bangsa = {
        melayu: 10450,
        cina: 3200,
        india: 1500,
        lain: 670
    },
    umurDistribution = [
        { range: '18-25', jumlah: 2340 },
        { range: '26-35', jumlah: 4120 },
        { range: '36-45', jumlah: 3890 },
        { range: '46-55', jumlah: 3210 },
        { range: '56-65', jumlah: 1680 },
        { range: '65+', jumlah: 580 },
    ],
    trendBulanan = [
        { bulan: 'Jan', jumlah: 420 },
        { bulan: 'Feb', jumlah: 680 },
        { bulan: 'Mar', jumlah: 890 },
        { bulan: 'Apr', jumlah: 1120 },
        { bulan: 'Mei', jumlah: 950 },
        { bulan: 'Jun', jumlah: 1180 },
    ],
    mpkkStats = [
        { mpkk: 'Taman Sejahtera', pengundi: 1240, culaan: 450, ph: 42, bn: 30, tidakPasti: 28 },
        { mpkk: 'Kampung Makmur', pengundi: 980, culaan: 380, ph: 38, bn: 35, tidakPasti: 27 },
        { mpkk: 'Taman Harmoni', pengundi: 1150, culaan: 420, ph: 45, bn: 28, tidakPasti: 27 },
        { mpkk: 'Desa Sentosa', pengundi: 890, culaan: 310, ph: 35, bn: 32, tidakPasti: 33 },
        { mpkk: 'Bandar Permai', pengundi: 1320, culaan: 490, ph: 40, bn: 31, tidakPasti: 29 },
    ],
    petugasStats = [
        { nama: 'Ahmad bin Abdullah', jumlah: 342, kawasan: 'Taman Sejahtera', tarikh: '2025-11-23' },
        { nama: 'Siti Nurhaliza', jumlah: 298, kawasan: 'Kampung Makmur', tarikh: '2025-11-23' },
        { nama: 'Lim Wei Chen', jumlah: 276, kawasan: 'Taman Harmoni', tarikh: '2025-11-22' },
        { nama: 'Kumar Selvam', jumlah: 245, kawasan: 'Desa Sentosa', tarikh: '2025-11-22' },
        { nama: 'Fatimah Zahra', jumlah: 231, kawasan: 'Bandar Permai', tarikh: '2025-11-21' },
    ],
    negeriList = [],
    bandarList = [],
    kadunList = [],
    mpkkList = []
}) {
    const [showFilters, setShowFilters] = useState(false);
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
        { name: 'PH/BN', value: sokongan.ph, color: '#10b981' },
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

    const handleFilter = () => {
        router.get(route('dashboard'), {
            negeri_id: filters.negeri,
            bandar_id: filters.bandar,
            kadun_id: filters.kadun,
            mpkk_id: filters.mpkk,
            tarikh_dari: filters.tarikhDari,
            tarikh_hingga: filters.tarikhHingga
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
                    <button
                        onClick={() => setShowFilters(!showFilters)}
                        className="flex items-center space-x-2 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        <Filter className="h-4 w-4" />
                        <span>{showFilters ? 'Sembunyikan' : 'Tunjukkan'} Penapis</span>
                        <ChevronDown className={`h-4 w-4 transition-transform ${showFilters ? 'rotate-180' : ''}`} />
                    </button>
                </div>

                {/* Filters Panel */}
                {showFilters && (
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-2">Negeri</label>
                                <select
                                    value={filters.negeri}
                                    onChange={(e) => setFilters({ ...filters, negeri: e.target.value })}
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
                                    onChange={(e) => setFilters({ ...filters, bandar: e.target.value })}
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
                                    onChange={(e) => setFilters({ ...filters, kadun: e.target.value })}
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
                                    onChange={(e) => setFilters({ ...filters, mpkk: e.target.value })}
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
                                    onChange={(e) => setFilters({ ...filters, tarikhDari: e.target.value })}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-2">Tarikh Hingga</label>
                                <input
                                    type="date"
                                    value={filters.tarikhHingga}
                                    onChange={(e) => setFilters({ ...filters, tarikhHingga: e.target.value })}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                />
                            </div>
                        </div>
                        <div className="flex items-center justify-end space-x-3 mt-4">
                            <button
                                onClick={handleReset}
                                className="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                            >
                                Set Semula
                            </button>
                            <button
                                onClick={handleFilter}
                                className="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                            >
                                Tapis
                            </button>
                        </div>
                    </div>
                )}

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
                                <p className="text-3xl font-bold text-emerald-600 mt-2">{sokongan.ph}%</p>
                            </div>
                            <div className="p-3 bg-emerald-100 rounded-lg">
                                <TrendingUp className="h-6 w-6 text-emerald-600" />
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
                                <p className="text-sm font-medium text-slate-600">Pengundi Mengikut KADUN</p>
                                <p className="text-3xl font-bold text-slate-900 mt-2">{mpkkStats.length}</p>
                            </div>
                            <div className="p-3 bg-purple-100 rounded-lg">
                                <MapPin className="h-6 w-6 text-purple-600" />
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

                {/* Sokongan Politik Mengikut MPKK - Stacked Bar */}
                <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <h3 className="text-lg font-semibold text-slate-900 mb-4">Sokongan Politik Mengikut MPKK</h3>
                    <ResponsiveContainer width="100%" height={350}>
                        <BarChart data={sokonganMpkkData}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                            <XAxis dataKey="mpkk" stroke="#64748b" />
                            <YAxis stroke="#64748b" />
                            <Tooltip />
                            <Legend />
                            <Bar dataKey="PH/BN" stackId="a" fill="#10b981" radius={[0, 0, 0, 0]} />
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
                                        <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Culaan</th>
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
                                            <td className="py-3 px-4 text-sm text-emerald-600 font-medium">{item.ph}%</td>
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
