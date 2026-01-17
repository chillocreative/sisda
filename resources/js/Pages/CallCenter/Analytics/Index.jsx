import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
    PieChart, Pie, Cell, LineChart, Line, AreaChart, Area
} from 'recharts';
import {
    Phone,
    Users,
    Clock,
    CheckCircle,
    TrendingUp,
    AlertCircle,
    Filter,
    Calendar,
    Map,
    MapPin,
    ArrowUpRight,
    ArrowDownRight
} from 'lucide-react';

export default function Index({ locality }) {
    const [filters, setFilters] = useState({
        dateRange: 'minggu',
        campaign: 'pru16',
        region: locality?.is_restricted ? 'locality' : 'all'
    });

    // Mock Data
    const stats = [
        { title: 'Jumlah Panggilan', value: '45,820', sub: '+12% dari minggu lepas', icon: Phone, color: 'blue', trend: 'up' },
        { title: 'Kadar Hubungan', value: '62.4%', sub: 'Target: 60%', icon: CheckCircle, color: 'emerald', trend: 'up' },
        { title: 'Purata Durasi', value: '4m 32s', sub: '-15s dari minggu lepas', icon: Clock, color: 'amber', trend: 'down' },
        { title: 'Pengundi Belum Pasti', value: '18,240', sub: '39% dari jumlah', icon: AlertCircle, color: 'rose', trend: 'up' }
    ];

    const sentimentData = [
        { name: 'Positif', value: 45, color: '#10b981' },
        { name: 'Neutral', value: 30, color: '#f59e0b' },
        { name: 'Negatif', value: 25, color: '#ef4444' }
    ];

    const issueData = [
        { issue: 'Kos Sara Hidup', percentage: 42 },
        { issue: 'Infrastruktur', percentage: 28 },
        { issue: 'Peluang Kerja', percentage: 15 },
        { issue: 'Pendidikan', percentage: 10 },
        { issue: 'Lain-lain', percentage: 5 }
    ];

    const supportTrend = [
        { date: 'Isnin', sokongan: 42, belum: 38 },
        { date: 'Selasa', sokongan: 45, belum: 35 },
        { date: 'Rabu', sokongan: 48, belum: 32 },
        { date: 'Khamis', sokongan: 46, belum: 34 },
        { date: 'Jumaat', sokongan: 50, belum: 30 },
        { date: 'Sabtu', sokongan: 52, belum: 28 },
        { date: 'Ahad', sokongan: 55, belum: 25 }
    ];

    const regionalData = [
        { region: 'Bayan Lepas', calls: 5200, sentiment: 'Positif', support: 62 },
        { region: 'Batu Maung', calls: 3800, sentiment: 'Neutral', support: 48 },
        { region: 'Pulau Betong', calls: 2900, sentiment: 'Positif', support: 58 },
        { region: 'Teluk Bahang', calls: 2100, sentiment: 'Negatif', support: 35 }
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Analitik Politik Call Center" />

            <div className="space-y-6">
                {/* Header & Filters */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-black text-slate-900 tracking-tight">ANALITIK STRATEGIK POLITIK</h1>
                        <p className="text-sm text-slate-500 font-medium italic">
                            {locality?.is_restricted
                                ? `Kawasan: ${locality.bandar} | ${locality.kadun}`
                                : 'Pusat Panggilan: Rumusan Data & Sentimen Pengundi Keseluruhan'}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-2 bg-white border border-slate-200 rounded-lg px-3 py-2 shadow-sm">
                            <Calendar className="h-4 w-4 text-slate-400" />
                            <select className="text-xs font-bold text-slate-600 bg-transparent border-none p-0 focus:ring-0">
                                <option>Minggu Ini</option>
                                <option>Bulan Ini</option>
                                <option>Sepanjang Kempen</option>
                            </select>
                        </div>
                        <div className="flex items-center gap-2 bg-white border border-slate-200 rounded-lg px-3 py-2 shadow-sm">
                            <MapPin className="h-4 w-4 text-slate-400" />
                            <select
                                disabled={locality?.is_restricted}
                                className="text-xs font-bold text-slate-600 bg-transparent border-none p-0 focus:ring-0 disabled:opacity-50"
                            >
                                {locality?.is_restricted ? (
                                    <option>{locality.bandar}</option>
                                ) : (
                                    <>
                                        <option>Semua Kawasan</option>
                                        <option>DUN Bayan Lepas</option>
                                        <option>DUN Batu Maung</option>
                                    </>
                                )}
                            </select>
                        </div>
                        <button className="bg-slate-900 text-white p-2 rounded-lg hover:bg-blue-600 transition-colors">
                            <Filter className="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {stats.map((s, i) => (
                        <div key={i} className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden group">
                            <div className="flex items-center justify-between mb-4">
                                <div className={`p-3 rounded-xl bg-${s.color}-50 text-${s.color}-600`}>
                                    <s.icon className="h-5 w-5" />
                                </div>
                                {s.trend === 'up' ? (
                                    <span className="flex items-center text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                                        <ArrowUpRight className="h-3 w-3 mr-1" /> GROWTH
                                    </span>
                                ) : (
                                    <span className="flex items-center text-[10px] font-bold text-rose-600 bg-rose-50 px-2 py-1 rounded-full">
                                        <ArrowDownRight className="h-3 w-3 mr-1" /> DROP
                                    </span>
                                )}
                            </div>
                            <h4 className="text-xs font-bold text-slate-400 uppercase tracking-widest">{s.title}</h4>
                            <p className="text-3xl font-black text-slate-900 mt-1 tabular-nums">{s.value}</p>
                            <p className="text-[10px] text-slate-400 font-medium mt-2">{s.sub}</p>
                        </div>
                    ))}
                </div>

                {/* Charts Area */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Sentiment Distribution */}
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 className="text-sm font-black text-slate-900 mb-6 uppercase tracking-wider flex items-center gap-2">
                            <TrendingUp className="h-4 w-4 text-blue-500" />
                            Agihan Sentimen
                        </h3>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={sentimentData}
                                        innerRadius={60}
                                        outerRadius={80}
                                        paddingAngle={5}
                                        dataKey="value"
                                    >
                                        {sentimentData.map((entry, index) => (
                                            <Cell key={`cell-${index}`} fill={entry.color} />
                                        ))}
                                    </Pie>
                                    <Tooltip />
                                    <Legend verticalAlign="bottom" height={36} />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    {/* Top Issues */}
                    <div className="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 className="text-sm font-black text-slate-900 mb-6 uppercase tracking-wider flex items-center gap-2">
                            <AlertCircle className="h-4 w-4 text-rose-500" />
                            Isu Utama Pengundi (%)
                        </h3>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={issueData} layout="vertical">
                                    <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                    <XAxis type="number" hide />
                                    <YAxis dataKey="issue" type="category" width={100} style={{ fontSize: '10px', fontWeight: 'bold' }} />
                                    <Tooltip />
                                    <Bar dataKey="percentage" fill="#64748b" radius={[0, 4, 4, 0]} barSize={20} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    {/* Support Trend */}
                    <div className="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 className="text-sm font-black text-slate-900 mb-6 uppercase tracking-wider flex items-center gap-2">
                            <TrendingUp className="h-4 w-4 text-emerald-500" />
                            Trend Sokongan & Atas Pagar
                        </h3>
                        <div className="h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart data={supportTrend}>
                                    <defs>
                                        <linearGradient id="colorSok" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor="#10b981" stopOpacity={0.1} />
                                            <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                                    <XAxis dataKey="date" style={{ fontSize: '10px' }} />
                                    <YAxis style={{ fontSize: '10px' }} />
                                    <Tooltip />
                                    <Legend />
                                    <Area type="monotone" dataKey="sokongan" stroke="#10b981" fillOpacity={1} fill="url(#colorSok)" name="Sokongan" strokeWidth={3} />
                                    <Area type="monotone" dataKey="belum" stroke="#94a3b8" fillOpacity={0} name="Belum Pasti" strokeDasharray="5 5" />
                                </AreaChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    {/* Regional Performance Table */}
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <h3 className="text-sm font-black text-slate-900 mb-6 uppercase tracking-wider flex items-center gap-2">
                            <Map className="h-4 w-4 text-purple-500" />
                            Prestasi Mengikut Kawasan
                        </h3>
                        <div className="space-y-4">
                            {regionalData.map((r, i) => (
                                <div key={i} className="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 transition-colors border border-transparent hover:border-slate-100">
                                    <div>
                                        <p className="text-xs font-black text-slate-900">{r.region}</p>
                                        <p className="text-[10px] text-slate-400 font-bold">{r.calls.toLocaleString()} Panggilan</p>
                                    </div>
                                    <div className="text-right">
                                        <p className={`text-[10px] font-black ${r.support > 50 ? 'text-emerald-600' : 'text-rose-600'}`}>{r.support}% Sokongan</p>
                                        <p className="text-[10px] text-slate-400 uppercase font-medium">{r.sentiment}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <button className="w-full mt-6 py-3 border border-slate-200 rounded-xl text-[10px] font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 transition-all">
                            Lihat Laporan Penuh Peta
                        </button>
                    </div>
                </div>

                {/* Footer Insight */}
                <div className="bg-slate-900 rounded-2xl p-8 text-white relative overflow-hidden">
                    <div className="absolute top-0 right-0 w-64 h-64 bg-blue-600/20 blur-[100px] rounded-full -mr-20 -mt-20"></div>
                    <div className="relative">
                        <h2 className="text-lg font-black tracking-tight mb-2 uppercase">Rumusan Strategik (AI Insight)</h2>
                        <p className="text-slate-400 text-sm leading-relaxed max-w-3xl">
                            Trend semasa menunjukkan <span className="text-blue-400 font-bold tracking-wide">peningkatan sokongan sebanyak 13%</span> di kawasan Bayan Lepas,
                            namun isu <span className="text-rose-400 font-bold tracking-wide">Kos Sara Hidup</span> kekal sebagai kebimbangan utama bagi 42% pengundi.
                            Strategi komunikasi perlu difokuskan kepada penyelesaian ekonomi tempatan untuk menarik 18,240 pengundi yang masih belum pasti.
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
