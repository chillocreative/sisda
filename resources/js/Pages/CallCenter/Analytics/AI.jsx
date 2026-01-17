import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
    PieChart, Pie, Cell, RadarChart, PolarGrid, PolarAngleAxis, PolarRadiusAxis, Radar,
    LineChart, Line
} from 'recharts';
import {
    Brain,
    AlertTriangle,
    Zap,
    Users,
    TrendingDown,
    Target,
    MessageSquare,
    Search,
    ShieldAlert,
    Gauge,
    Sparkles,
    ArrowRight
} from 'lucide-react';

export default function Index({ locality }) {
    // Mock AI Data
    const campaignHealth = 78; // Health Score

    const sentimentAnalysis = [
        { name: 'Sangat Positif', value: 15, color: '#059669' },
        { name: 'Positif', value: 35, color: '#10b981' },
        { name: 'Neutral', value: 25, color: '#f59e0b' },
        { name: 'Negatif', value: 15, color: '#ef4444' },
        { name: 'Sangat Negatif', value: 10, color: '#b91c1c' }
    ];

    const keywordExtraction = [
        { word: 'Harga Barang', count: 1240, sentiment: -0.8 },
        { word: 'Jalan Rosak', count: 850, sentiment: -0.6 },
        { word: 'Bantuan Sekolah', count: 620, sentiment: 0.7 },
        { word: 'Lampu Jalan', count: 430, sentiment: -0.4 },
        { word: 'Klinik Kesihatan', count: 310, sentiment: 0.5 }
    ];

    const clusters = [
        { cluster: 'Penyokong Tegar', value: 42, color: '#2563eb' },
        { cluster: 'Atas Pagar (Cenderung Kita)', value: 18, color: '#60a5fa' },
        { cluster: 'Atas Pagar (Cenderung Lawan)', value: 12, color: '#f87171' },
        { cluster: 'Penentang Tegar', value: 28, color: '#dc2626' }
    ];

    const earlyWarnings = [
        { id: 1, title: 'Penurunan Sokongan Belia', region: 'DUN Bayan Lepas', metric: '-4.2%', priority: 'High' },
        { id: 2, title: 'Isu Sampah Meningkat', region: 'Taman Merpati', metric: '+15% aduan', priority: 'Medium' },
        { id: 3, title: 'Sentimen Negatif Ekonomi', region: 'Global', metric: '+12% kebimbangan', priority: 'High' }
    ];

    const performanceMetrics = [
        { subject: 'Jangkauan', A: 85, fullMark: 100 },
        { subject: 'Persuasi', A: 65, fullMark: 100 },
        { subject: 'Retensi', A: 90, fullMark: 100 },
        { subject: 'Sentimen', A: 70, fullMark: 100 },
        { subject: 'Data Integrity', A: 95, fullMark: 100 }
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Analitik AI - Call Center" />

            <div className="space-y-6 pb-12">
                {/* AI Executive Summary Header */}
                <div className="bg-gradient-to-r from-slate-900 via-blue-900 to-slate-900 rounded-3xl p-8 text-white relative overflow-hidden shadow-2xl">
                    <div className="absolute top-0 right-0 p-8 opacity-10">
                        <Brain className="h-40 w-40" />
                    </div>
                    <div className="relative z-10 grid grid-cols-1 lg:grid-cols-4 gap-8 items-center">
                        <div className="lg:col-span-3 space-y-4">
                            <div className="flex items-center gap-2 text-blue-400 font-bold uppercase tracking-[0.2em] text-xs">
                                <Sparkles className="h-4 w-4" />
                                AI-Driven Insights Engine
                            </div>
                            <h1 className="text-3xl font-black tracking-tighter">
                                {locality?.is_restricted ? `RUMUSAN PINTAR: ${locality.bandar}` : 'RUMUSAN EKSEKUTIF PINTAR'}
                            </h1>
                            <p className="text-slate-300 text-sm leading-relaxed max-w-2xl">
                                Enjin AI kami telah menganalisis <span className="text-white font-bold tracking-wide">15,420 nota panggilan</span>
                                {locality?.is_restricted ? ` bagi kawasan ${locality.bandar} dan ${locality.kadun}` : ' bagi seluruh kawasan'} minggu ini.
                                Secara keseluruhan, kestabilan kempen berada pada tahap <span className="text-emerald-400 font-bold">Kukuh (Strong)</span>,
                                namun terdapat indikator amaran awal di sektor <span className="text-rose-400 font-bold underline underline-offset-4">kos sara hidup</span> yang memerlukan tindak balas segera.
                            </p>
                            <div className="flex gap-4 pt-2">
                                <button className="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                                    Muat Turun Laporan AI <ArrowRight className="h-4 w-4" />
                                </button>
                                <button className="bg-white/10 hover:bg-white/20 text-white px-6 py-2 rounded-xl text-xs font-bold transition-all border border-white/10">
                                    Konfigurasi Model
                                </button>
                            </div>
                        </div>
                        <div className="bg-white/5 backdrop-blur-md rounded-2xl p-6 border border-white/10 text-center">
                            <h3 className="text-xs font-bold text-blue-300 uppercase mb-2">Campaign Health Score</h3>
                            <div className="text-5xl font-black text-white tabular-nums mb-2">{campaignHealth}%</div>
                            <div className="w-full bg-white/10 rounded-full h-1.5">
                                <div className="bg-emerald-400 h-1.5 rounded-full" style={{ width: `${campaignHealth}%` }}></div>
                            </div>
                            <p className="text-[10px] text-slate-400 mt-3 italic">Berdasarkan data 48 jam terakhir</p>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Sentiment Analysis (Auto-Detected) */}
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm col-span-1">
                        <h3 className="text-sm font-black text-slate-900 mb-6 uppercase tracking-wider flex items-center gap-2">
                            <Zap className="h-4 w-4 text-blue-600" />
                            Sentimen Automatik (NLP)
                        </h3>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={sentimentAnalysis}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                                    <XAxis dataKey="name" hide />
                                    <YAxis hide />
                                    <Tooltip cursor={{ fill: '#f8fafc' }} />
                                    <Bar dataKey="value" radius={[4, 4, 0, 0]}>
                                        {sentimentAnalysis.map((entry, index) => (
                                            <Cell key={`cell-${index}`} fill={entry.color} />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                        <div className="mt-4 grid grid-cols-2 gap-2">
                            {sentimentAnalysis.map((s, i) => (
                                <div key={i} className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full" style={{ backgroundColor: s.color }}></div>
                                    <span className="text-[10px] font-bold text-slate-500 uppercase">{s.name} ({s.value}%)</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Emerging Issues (Keyword Extraction) */}
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm col-span-1">
                        <h3 className="text-sm font-black text-slate-900 mb-6 uppercase tracking-wider flex items-center gap-2">
                            <Search className="h-4 w-4 text-purple-600" />
                            Isu Baru Dikesan (Keywords)
                        </h3>
                        <div className="space-y-4">
                            {keywordExtraction.map((k, i) => (
                                <div key={i} className="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100">
                                    <div className="flex items-center gap-3">
                                        <div className={`h-8 w-8 rounded-lg flex items-center justify-center font-black text-[10px] ${k.sentiment < 0 ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'}`}>
                                            {k.sentiment > 0 ? '+' : ''}{k.sentiment}
                                        </div>
                                        <span className="text-xs font-black text-slate-900">{k.word}</span>
                                    </div>
                                    <div className="text-right">
                                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{k.count} Sebutan</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <button className="w-full mt-6 py-3 border-t border-slate-100 text-[10px] font-black text-blue-600 uppercase tracking-widest hover:bg-slate-50 transition-all rounded-b-2xl">
                            Lihat Analisis Konteks Penuh
                        </button>
                    </div>

                    {/* Voter Clustering Chart */}
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm col-span-1">
                        <h3 className="text-sm font-black text-slate-900 mb-6 uppercase tracking-wider flex items-center gap-2">
                            <Users className="h-4 w-4 text-orange-500" />
                            Kluster Pengundi (AI Grouping)
                        </h3>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={clusters}
                                        innerRadius={70}
                                        outerRadius={90}
                                        paddingAngle={5}
                                        dataKey="value"
                                    >
                                        {clusters.map((entry, index) => (
                                            <Cell key={`cell-${index}`} fill={entry.color} />
                                        ))}
                                    </Pie>
                                    <Tooltip />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                        <div className="mt-4 space-y-2">
                            {clusters.map((c, i) => (
                                <div key={i} className="flex items-center justify-between text-[10px]">
                                    <span className="font-bold text-slate-500 uppercase">{c.cluster}</span>
                                    <span className="font-black text-slate-900">{c.value}%</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Early Warning System */}
                    <div className="bg-white p-6 rounded-2xl border border-rose-100 shadow-sm lg:col-span-2 border-l-4 border-l-rose-500">
                        <div className="flex items-center justify-between mb-6">
                            <h3 className="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <ShieldAlert className="h-5 w-5 text-rose-500" />
                                Sistem Amaran Awal (Early Warning)
                            </h3>
                            <span className="text-[10px] font-black text-rose-500 bg-rose-50 px-3 py-1 rounded-full animate-pulse">3 AKTIF</span>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {earlyWarnings.map((w) => (
                                <div key={w.id} className="p-4 rounded-2xl bg-slate-50 border border-slate-100 relative group overflow-hidden">
                                    <div className={`absolute top-0 right-0 p-2 text-[8px] font-black uppercase ${w.priority === 'High' ? 'text-rose-500' : 'text-amber-500'}`}>
                                        {w.priority} Risk
                                    </div>
                                    <h4 className="text-xs font-black text-slate-900 mb-1">{w.title}</h4>
                                    <p className="text-[10px] text-slate-400 font-bold uppercase mb-3">{w.region}</p>
                                    <div className={`text-xl font-black tabular-nums ${w.priority === 'High' ? 'text-rose-600' : 'text-amber-600'}`}>
                                        {w.metric}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Campaign Score Card */}
                    <div className="bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl lg:col-span-1 text-white">
                        <h3 className="text-sm font-black mb-6 uppercase tracking-wider flex items-center gap-2 text-blue-400">
                            <Target className="h-4 w-4" />
                            Skor Prestasi Kempen
                        </h3>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <RadarChart cx="50%" cy="50%" outerRadius="80%" data={performanceMetrics}>
                                    <PolarGrid stroke="#334155" />
                                    <PolarAngleAxis dataKey="subject" tick={{ fill: '#94a3b8', fontSize: 10, fontWeight: 'bold' }} />
                                    <Radar
                                        name="Kempen"
                                        dataKey="A"
                                        stroke="#3b82f6"
                                        fill="#3b82f6"
                                        fillOpacity={0.6}
                                    />
                                </RadarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                </div>

                {/* Voter Sentiment Over Time (Auto-Predictive) */}
                <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <div className="flex items-center justify-between mb-8">
                        <div>
                            <h3 className="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <TrendingDown className="h-4 w-4 text-blue-600" />
                                Ramalan Sentimen Masa Hadapan
                            </h3>
                            <p className="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Berdasarkan Enjin Simulasi AI</p>
                        </div>
                        <div className="flex items-center gap-4 text-[10px] font-black uppercase tracking-widest">
                            <div className="flex items-center gap-1"><div className="h-2 w-2 rounded-full bg-blue-500"></div> Sebenar</div>
                            <div className="flex items-center gap-1 text-slate-300"><div className="h-2 w-2 rounded-full bg-slate-200"></div> Ramalan</div>
                        </div>
                    </div>
                    <div className="h-48 overflow-hidden rounded-xl bg-slate-50 flex items-center justify-center border border-dashed border-slate-200">
                        <div className="text-center group cursor-pointer p-8">
                            <Zap className="h-8 w-8 text-slate-300 mx-auto mb-3 group-hover:scale-110 group-hover:text-blue-500 transition-all" />
                            <p className="text-xs font-black text-slate-400 group-hover:text-slate-900 transition-colors uppercase tracking-widest">Klik untuk Menjalankan Simulasi "What-If"</p>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
