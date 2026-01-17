import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    Search,
    Filter,
    Calendar,
    Phone,
    Download,
    Clock,
    User,
    ChevronRight,
    Smile,
    Meh,
    Frown
} from 'lucide-react';

export default function History({ locality }) {
    const [searchTerm, setSearchTerm] = useState('');

    const callLogs = [
        {
            id: 1,
            voterName: 'Abdullah Bin Ibrahim',
            phone: '012-3456789',
            timestamp: '2026-01-17 10:30 AM',
            duration: '05:42',
            sentiment: 'positive',
            status: 'connected',
            location: 'Bayan Lepas'
        },
        {
            id: 2,
            voterName: 'Siti Aminah Binti Ali',
            phone: '017-9876543',
            timestamp: '2026-01-17 09:15 AM',
            duration: '02:15',
            sentiment: 'neutral',
            status: 'connected',
            location: 'Gelugor'
        },
        {
            id: 3,
            voterName: 'Tan Ah Kow',
            phone: '011-22334455',
            timestamp: '2026-01-16 04:45 PM',
            duration: '00:00',
            sentiment: null,
            status: 'no_answer',
            location: 'Air Itam'
        },
        {
            id: 4,
            voterName: 'Muthu A/L Subramaniam',
            phone: '016-55443322',
            timestamp: '2026-01-16 02:20 PM',
            duration: '08:10',
            sentiment: 'negative',
            status: 'connected',
            location: 'Balik Pulau'
        }
    ];

    const getSentimentIcon = (sentiment) => {
        switch (sentiment) {
            case 'positive': return <Smile className="h-4 w-4 text-emerald-500" />;
            case 'neutral': return <Meh className="h-4 w-4 text-amber-500" />;
            case 'negative': return <Frown className="h-4 w-4 text-rose-500" />;
            default: return null;
        }
    };

    const getStatusBadge = (status) => {
        switch (status) {
            case 'connected': return <span className="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-[10px] font-bold uppercase">Berjaya</span>;
            case 'no_answer': return <span className="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full text-[10px] font-bold uppercase">Tiada Jawapan</span>;
            default: return null;
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Indeks Panggilan - SISDA" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-black text-slate-900 tracking-tight uppercase">Indeks Panggilan</h1>
                        <p className="text-sm text-slate-600">
                            {locality?.is_restricted
                                ? `Senarai rekod panggilan anda di ${locality.bandar} | ${locality.kadun}`
                                : 'Senarai panggilan yang telah anda lakukan di seluruh kawasan'}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button className="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg hover:bg-slate-50 transition-all font-semibold text-sm">
                            <Download className="h-4 w-4" />
                            Eksport Log
                        </button>
                    </div>
                </div>

                {/* Filters & Search */}
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row gap-4">
                    <div className="flex-1 relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Cari nama atau nombor telefon..."
                            className="w-full pl-10 pr-4 py-2 bg-slate-50 border-transparent focus:bg-white focus:ring-blue-500 rounded-lg text-sm"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                    <div className="flex gap-2">
                        <button className="flex items-center gap-2 px-4 py-2 bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-100 transition-all text-sm font-medium">
                            <Calendar className="h-4 w-4" />
                            Tarikh
                        </button>
                        <button className="flex items-center gap-2 px-4 py-2 bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-100 transition-all text-sm font-medium">
                            <Filter className="h-4 w-4" />
                            Sentimen
                        </button>
                    </div>
                </div>

                {/* Table */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-slate-50 border-b border-slate-200">
                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Pengundi</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Masa & Tarikh</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Durasi</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Sentimen</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {callLogs.map((log) => (
                                    <tr key={log.id} className="hover:bg-slate-50 transition-colors group">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="h-8 w-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">
                                                    {log.voterName.charAt(0)}
                                                </div>
                                                <div>
                                                    <p className="text-sm font-bold text-slate-900 leading-none mb-1">{log.voterName}</p>
                                                    <p className="text-xs text-slate-500">{log.phone} • {log.location}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2 text-sm text-slate-600 font-medium">
                                                <Clock className="h-3 w-3 text-slate-400" />
                                                {log.timestamp}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            {getStatusBadge(log.status)}
                                        </td>
                                        <td className="px-6 py-4">
                                            <p className="text-sm font-bold text-slate-700 tabular-nums">{log.duration}</p>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2">
                                                {getSentimentIcon(log.sentiment)}
                                                <span className="text-xs font-bold text-slate-600 capitalize">{log.sentiment || '-'}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <button className="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all">
                                                <ChevronRight className="h-5 w-5" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-500 uppercase tracking-tight">
                        <span>Menunjukkan 4 panggilan terakhir</span>
                        <div className="flex gap-2">
                            <button className="px-3 py-1 border border-slate-200 rounded disabled:opacity-50" disabled>Sebelum</button>
                            <button className="px-3 py-1 border border-slate-200 rounded disabled:opacity-50" disabled>Seterusnya</button>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
