import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    Phone,
    PhoneOff,
    Pause,
    Mic,
    User,
    MapPin,
    History,
    Save,
    AlertTriangle,
    Smile,
    Meh,
    Frown,
    MessageSquare,
    Clock,
    ChevronRight,
    Search,
    FileText
} from 'lucide-react';

export default function Index({ locality }) {
    const [callStatus, setCallStatus] = useState('idle'); // idle, calling, connected
    const [timer, setTimer] = useState(0);
    const [sentiment, setSentiment] = useState(null);
    const [answers, setAnswers] = useState({});
    const [notes, setNotes] = useState('');
    const [autoSaveStatus, setAutoSaveStatus] = useState('tersimpan');

    // Simulate timer
    useEffect(() => {
        let interval;
        if (callStatus === 'connected') {
            interval = setInterval(() => {
                setTimer((prev) => prev + 1);
            }, 1000);
        } else {
            setTimer(0);
            clearInterval(interval);
        }
        return () => clearInterval(interval);
    }, [callStatus]);

    const formatTime = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    };

    const voter = {
        name: 'Abdullah Bin Ibrahim',
        ic: '750101-08-5433',
        age: '45-50',
        phone: '012-3456789',
        location: 'Bayan Lepas, Pulau Pinang',
        district: 'DUN N.38 Bayan Lepas',
        pollingCenter: 'SK Bayan Lepas',
        previousInteractions: [
            { date: '2025-11-20', type: 'Panggilan', result: 'Berminat', notes: 'Mahu maklumat lanjut tentang bantuan pendidikan.' },
            { date: '2025-10-15', type: 'SMS', result: 'Diterima', notes: 'Hebahan program masyarakat.' }
        ]
    };

    const handleAnswerChange = (questionId, value) => {
        setAnswers(prev => ({ ...prev, [questionId]: value }));
        setAutoSaveStatus('menyimpan...');
        setTimeout(() => setAutoSaveStatus('tersimpan'), 1000);
    };

    const questions = [
        { id: 'q1', text: 'Adakah anda bercadang untuk mengundi pada PRU akan datang?', type: 'radio', options: ['Ya', 'Tidak', 'Belum Pasti'], required: true },
        { id: 'q2', text: 'Apakah isu utama yang menghantui penduduk kawasan ini?', type: 'select', options: ['Kos Sara Hidup', 'Keselamatan', 'Infrastruktur', 'Peluang Kerja'], required: true },
        { id: 'q3', text: 'Tahap kepuasan anda terhadap wakil rakyat semasa?', type: 'scale', required: false },
        { id: 'q4', text: 'Adakah anda mahu menerima risalah digital melalui WhatsApp?', type: 'radio', options: ['Sertai', 'Tolak'], required: false }
    ];

    const isQuestionIncomplete = (q) => q.required && !answers[q.id];

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard Ejen - Pusat Panggilan" />

            <div className="flex flex-col lg:flex-row gap-6 min-h-[calc(100vh-160px)]">

                {/* Panel Kiri: Maklumat Pengundi */}
                <div className="w-full lg:w-1/4 space-y-6">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="p-4 bg-slate-50 border-b border-slate-200">
                            <h3 className="font-bold text-slate-900 flex items-center gap-2">
                                <User className="h-4 w-4 text-blue-600" />
                                Maklumat Pengundi
                            </h3>
                        </div>
                        <div className="p-5 space-y-4">
                            <div>
                                <label className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Nama Penuh</label>
                                <p className="text-slate-900 font-semibold">{voter.name}</p>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Julat Umur</label>
                                    <p className="text-sm text-slate-900">{voter.age} Tahun</p>
                                </div>
                                <div>
                                    <label className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">No. Tel</label>
                                    <p className="text-sm text-slate-900">{voter.phone}</p>
                                </div>
                            </div>
                            <div>
                                <label className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Lokasi</label>
                                <p className="text-sm text-slate-900 flex items-center gap-1">
                                    <MapPin className="h-3 w-3 text-slate-400" />
                                    {voter.location}
                                </p>
                            </div>
                            <div>
                                <label className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Kawasan Mengundi</label>
                                <p className="text-sm text-slate-900">
                                    {locality?.is_restricted ? locality.kadun : voter.district}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="p-4 bg-slate-50 border-b border-slate-200">
                            <h3 className="font-bold text-slate-900 flex items-center gap-2">
                                <History className="h-4 w-4 text-emerald-600" />
                                Interaksi Terdahulu
                            </h3>
                        </div>
                        <div className="p-5">
                            <div className="space-y-4">
                                {voter.previousInteractions.map((item, i) => (
                                    <div key={i} className="border-l-2 border-emerald-500 pl-4 py-1">
                                        <p className="text-xs font-bold text-slate-900">{item.date} - {item.type}</p>
                                        <p className="text-xs text-slate-600 italic">"{item.notes}"</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Panel Tengah: Kawalan Panggilan */}
                <div className="flex-1 space-y-6">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center space-y-6 relative overflow-hidden">
                        {callStatus === 'connected' && (
                            <div className="absolute top-0 left-0 w-full h-1 bg-blue-500 animate-pulse"></div>
                        )}

                        <div className="space-y-2">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-bold uppercase tracking-widest">
                                <span className={`h-2 w-2 rounded-full ${callStatus === 'connected' ? 'bg-emerald-500 animate-ping' : 'bg-slate-300'}`}></span>
                                {callStatus === 'idle' ? 'Sedia' : callStatus === 'calling' ? 'Dail...' : 'Dalam Talian'}
                            </div>
                            <h2 className="text-4xl font-black text-slate-900 tracking-tighter tabular-nums">
                                {formatTime(timer)}
                            </h2>
                        </div>

                        <div className="flex items-center justify-center gap-4">
                            {callStatus === 'idle' ? (
                                <button
                                    onClick={() => setCallStatus('connected')}
                                    className="h-16 w-16 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-lg shadow-emerald-200 hover:bg-emerald-600 transition-all hover:scale-105 active:scale-95"
                                >
                                    <Phone className="h-7 w-7" />
                                </button>
                            ) : (
                                <>
                                    <button className="h-12 w-12 rounded-full border border-slate-200 text-slate-600 flex items-center justify-center hover:bg-slate-50 transition-colors">
                                        <Pause className="h-5 w-5" />
                                    </button>
                                    <button
                                        onClick={() => setCallStatus('idle')}
                                        className="h-16 w-16 rounded-full bg-rose-500 text-white flex items-center justify-center shadow-lg shadow-rose-200 hover:bg-rose-600 transition-all hover:scale-105 active:scale-95"
                                    >
                                        <PhoneOff className="h-7 w-7" />
                                    </button>
                                    <button className="h-12 w-12 rounded-full border border-slate-200 text-slate-600 flex items-center justify-center hover:bg-slate-50 transition-colors">
                                        <Mic className="h-5 w-5" />
                                    </button>
                                </>
                            )}
                        </div>
                    </div>

                    {/* Nota & Sentimen */}
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
                        <div className="space-y-3">
                            <label className="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <Smile className="h-4 w-4 text-amber-500" />
                                Sentimen Panggilan
                            </label>
                            <div className="flex gap-2">
                                <button
                                    onClick={() => setSentiment('positive')}
                                    className={`flex-1 py-3 px-4 rounded-xl border flex flex-col items-center gap-2 transition-all ${sentiment === 'positive' ? 'bg-emerald-50 border-emerald-500 text-emerald-700' : 'bg-white border-slate-100 text-slate-400 hover:bg-slate-50'}`}
                                >
                                    <Smile className="h-6 w-6" />
                                    <span className="text-xs font-bold">Positif</span>
                                </button>
                                <button
                                    onClick={() => setSentiment('neutral')}
                                    className={`flex-1 py-3 px-4 rounded-xl border flex flex-col items-center gap-2 transition-all ${sentiment === 'neutral' ? 'bg-amber-50 border-amber-500 text-amber-700' : 'bg-white border-slate-100 text-slate-400 hover:bg-slate-50'}`}
                                >
                                    <Meh className="h-6 w-6" />
                                    <span className="text-xs font-bold">Neutral</span>
                                </button>
                                <button
                                    onClick={() => setSentiment('negative')}
                                    className={`flex-1 py-3 px-4 rounded-xl border flex flex-col items-center gap-2 transition-all ${sentiment === 'negative' ? 'bg-rose-50 border-rose-500 text-rose-700' : 'bg-white border-slate-100 text-slate-400 hover:bg-slate-50'}`}
                                >
                                    <Frown className="h-6 w-6" />
                                    <span className="text-xs font-bold">Negatif</span>
                                </button>
                            </div>
                        </div>

                        <div className="space-y-3">
                            <label className="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <MessageSquare className="h-4 w-4 text-blue-500" />
                                Nota Panggilan
                            </label>
                            <textarea
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="Tulis nota penting di sini..."
                                className="w-full h-32 rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            ></textarea>
                        </div>
                    </div>
                </div>

                {/* Panel Kanan: Skrip & Soalan */}
                <div className="w-full lg:w-1/3 space-y-6">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col h-full overflow-hidden">
                        <div className="p-4 bg-slate-900 text-white flex items-center justify-between">
                            <h3 className="font-bold flex items-center gap-2">
                                <FileText className="h-4 w-4 text-blue-400" />
                                Skrip Panggilan
                            </h3>
                            <div className="flex items-center gap-1 text-[10px] bg-white/10 px-2 py-1 rounded text-white/70">
                                <Save className="h-3 w-3" />
                                {autoSaveStatus}
                            </div>
                        </div>

                        <div className="flex-1 p-6 space-y-8 overflow-y-auto">
                            {questions.map((q, i) => (
                                <div key={q.id} className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <span className="h-6 w-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xs font-bold shrink-0">
                                            {i + 1}
                                        </span>
                                        <div className="space-y-3 flex-1">
                                            <p className="text-sm font-bold text-slate-900 leading-tight">
                                                {q.text}
                                                {q.required && <span className="text-rose-500 ml-1 font-black">*</span>}
                                            </p>

                                            {q.type === 'radio' && (
                                                <div className="flex flex-wrap gap-2">
                                                    {q.options.map(opt => (
                                                        <button
                                                            key={opt}
                                                            onClick={() => handleAnswerChange(q.id, opt)}
                                                            className={`px-4 py-2 rounded-lg border text-xs font-bold transition-all ${answers[q.id] === opt ? 'bg-blue-600 border-blue-600 text-white shadow-md shadow-blue-100' : 'bg-white border-slate-200 text-slate-600 hover:border-blue-300'}`}
                                                        >
                                                            {opt}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}

                                            {q.type === 'select' && (
                                                <select
                                                    value={answers[q.id] || ''}
                                                    onChange={(e) => handleAnswerChange(q.id, e.target.value)}
                                                    className="w-full text-sm rounded-lg border-slate-200 focus:ring-blue-500 focus:border-blue-500"
                                                >
                                                    <option value="">Sila Pilih...</option>
                                                    {q.options.map(opt => <option key={opt} value={opt}>{opt}</option>)}
                                                </select>
                                            )}

                                            {q.type === 'scale' && (
                                                <div className="flex justify-between gap-1">
                                                    {[1, 2, 3, 4, 5].map(num => (
                                                        <button
                                                            key={num}
                                                            onClick={() => handleAnswerChange(q.id, num)}
                                                            className={`h-10 w-full rounded-lg border text-xs font-bold transition-all ${answers[q.id] === num ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-slate-200 text-slate-600'}`}
                                                        >
                                                            {num}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}

                                            {isQuestionIncomplete(q) && (
                                                <div className="flex items-center gap-1 text-[10px] text-rose-500 font-bold uppercase animate-pulse">
                                                    <AlertTriangle className="h-3 w-3" />
                                                    Sila isi soalan ini
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    {i < questions.length - 1 && <div className="border-b border-slate-100"></div>}
                                </div>
                            ))}
                        </div>

                        <div className="p-4 border-t border-slate-100 bg-slate-50">
                            <button className="w-full py-4 bg-slate-900 text-white rounded-xl font-bold flex items-center justify-center gap-2 hover:bg-blue-600 transition-all shadow-lg shadow-blue-100 mb-2 group">
                                Simpan Rekod
                                <ChevronRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                            </button>
                            <p className="text-[10px] text-center text-slate-400">Pastikan semua soalan bertanda * diisi sebelum simpan.</p>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
