import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Plus,
    Edit,
    Trash2,
    Copy,
    Eye,
    FileText,
    GripVertical,
    ChevronDown,
    ChevronUp,
    Save,
    X,
    AlertCircle,
    CheckCircle2,
    Archive,
    Play
} from 'lucide-react';

export default function Index() {
    const [scripts, setScripts] = useState([
        {
            id: 1,
            name: 'Skrip Tinjauan Kepuasan Pengundi 2026',
            campaign: 'Kempen Jangkauan Komuniti',
            region: 'Pulau Pinang - Bayan Lepas',
            status: 'active',
            questions: 5,
            lastModified: '2026-01-15',
            createdBy: 'Admin'
        },
        {
            id: 2,
            name: 'Skrip Pengenalpastian Isu Utama',
            campaign: 'Analisis Sentimen Pengundi',
            region: 'Selangor - Petaling Jaya',
            status: 'draft',
            questions: 8,
            lastModified: '2026-01-14',
            createdBy: 'Ahmad Ali'
        },
        {
            id: 3,
            name: 'Skrip Kempen Kesedaran Mengundi',
            campaign: 'Mobilisasi Pengundi Muda',
            region: 'Kuala Lumpur - Lembah Pantai',
            status: 'active',
            questions: 6,
            lastModified: '2026-01-10',
            createdBy: 'Siti Nurhaliza'
        }
    ]);

    const [showCreateModal, setShowCreateModal] = useState(false);
    const [viewingScript, setViewingScript] = useState(null);

    const sampleQuestions = [
        {
            id: 1,
            text: 'Adakah anda berpuas hati dengan wakil rakyat tempatan semasa?',
            type: 'scale',
            options: ['1 - Sangat Tidak Puas', '2 - Tidak Puas', '3 - Neutral', '4 - Puas', '5 - Sangat Puas'],
            required: true,
            sentiment: 'high'
        },
        {
            id: 2,
            text: 'Isu manakah yang paling membimbangkan anda pada masa ini?',
            type: 'multiple',
            options: ['Kos Sara Hidup', 'Pekerjaan', 'Infrastruktur', 'Pendidikan', 'Kesihatan'],
            required: true,
            sentiment: 'high'
        },
        {
            id: 3,
            text: 'Adakah anda merancang untuk mengundi pada pilihan raya akan datang?',
            type: 'yesno',
            options: ['Ya', 'Tidak', 'Tidak Pasti'],
            required: true,
            sentiment: 'medium'
        },
        {
            id: 4,
            text: 'Parti manakah yang anda rasa paling hampir dengan anda pada masa ini?',
            type: 'multiple',
            options: ['Pakatan Harapan', 'Barisan Nasional', 'Perikatan Nasional', 'Parti Lain', 'Tidak Pasti'],
            required: false,
            sentiment: 'high'
        },
        {
            id: 5,
            text: 'Adakah terdapat sebarang kebimbangan tambahan yang anda ingin kami ketahui?',
            type: 'text',
            options: [],
            required: false,
            sentiment: 'low'
        }
    ];

    const getStatusBadge = (status) => {
        const badges = {
            active: { label: 'Aktif', color: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
            draft: { label: 'Draf', color: 'bg-amber-100 text-amber-700 border-amber-200' },
            archived: { label: 'Arkib', color: 'bg-slate-100 text-slate-700 border-slate-200' }
        };
        return badges[status] || badges.draft;
    };

    const getTypeLabel = (type) => {
        const types = {
            yesno: 'Ya/Tidak',
            scale: 'Skala 1-5',
            multiple: 'Pilihan Berganda',
            text: 'Teks Terbuka'
        };
        return types[type] || type;
    };

    const getSentimentBadge = (sentiment) => {
        const badges = {
            high: { label: 'Tinggi', color: 'bg-rose-100 text-rose-700' },
            medium: { label: 'Sederhana', color: 'bg-amber-100 text-amber-700' },
            low: { label: 'Rendah', color: 'bg-blue-100 text-blue-700' }
        };
        return badges[sentiment] || badges.low;
    };

    return (
        <AuthenticatedLayout>
            <Head title="Pengurusan Skrip Panggilan" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Pengurusan Skrip Panggilan</h1>
                        <p className="text-sm text-slate-600 mt-1">
                            Urus dan cipta skrip panggilan untuk kempen komunikasi politik
                        </p>
                    </div>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Cipta Skrip Baru</span>
                    </button>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm text-slate-600">Jumlah Skrip</span>
                            <FileText className="h-5 w-5 text-blue-600" />
                        </div>
                        <p className="text-2xl font-bold text-slate-900">12</p>
                    </div>
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm text-slate-600">Skrip Aktif</span>
                            <Play className="h-5 w-5 text-emerald-600" />
                        </div>
                        <p className="text-2xl font-bold text-slate-900">5</p>
                    </div>
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm text-slate-600">Draf</span>
                            <Edit className="h-5 w-5 text-amber-600" />
                        </div>
                        <p className="text-2xl font-bold text-slate-900">4</p>
                    </div>
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm text-slate-600">Arkib</span>
                            <Archive className="h-5 w-5 text-slate-600" />
                        </div>
                        <p className="text-2xl font-bold text-slate-900">3</p>
                    </div>
                </div>

                {/* Scripts Table */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Nama Skrip</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Kempen</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Kawasan</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Status</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Soalan</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Dikemaskini</th>
                                    <th className="text-right py-3 px-4 text-xs font-semibold text-slate-600 uppercase">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {scripts.map((script) => (
                                    <tr key={script.id} className="hover:bg-slate-50 transition-colors">
                                        <td className="py-3 px-4">
                                            <div className="font-medium text-slate-900">{script.name}</div>
                                            <div className="text-xs text-slate-500">Oleh: {script.createdBy}</div>
                                        </td>
                                        <td className="py-3 px-4 text-sm text-slate-600">{script.campaign}</td>
                                        <td className="py-3 px-4 text-sm text-slate-600">{script.region}</td>
                                        <td className="py-3 px-4">
                                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${getStatusBadge(script.status).color}`}>
                                                {getStatusBadge(script.status).label}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4 text-sm text-slate-600">{script.questions} soalan</td>
                                        <td className="py-3 px-4 text-sm text-slate-600">{script.lastModified}</td>
                                        <td className="py-3 px-4">
                                            <div className="flex items-center justify-end space-x-2">
                                                <button
                                                    onClick={() => setViewingScript(script)}
                                                    className="p-2 text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                    title="Lihat"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </button>
                                                <button
                                                    className="p-2 text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                                    title="Edit"
                                                >
                                                    <Edit className="h-4 w-4" />
                                                </button>
                                                <button
                                                    className="p-2 text-slate-600 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
                                                    title="Salin"
                                                >
                                                    <Copy className="h-4 w-4" />
                                                </button>
                                                <button
                                                    className="p-2 text-slate-600 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                                    title="Padam"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* View Script Modal */}
                {viewingScript && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                            <div className="p-6 border-b border-slate-200 flex items-center justify-between">
                                <div>
                                    <h2 className="text-xl font-bold text-slate-900">{viewingScript.name}</h2>
                                    <p className="text-sm text-slate-600 mt-1">{viewingScript.campaign}</p>
                                </div>
                                <button
                                    onClick={() => setViewingScript(null)}
                                    className="p-2 hover:bg-slate-100 rounded-lg transition-colors"
                                >
                                    <X className="h-5 w-5 text-slate-600" />
                                </button>
                            </div>

                            <div className="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
                                {/* Script Info */}
                                <div className="grid grid-cols-2 gap-4 mb-6 p-4 bg-slate-50 rounded-lg">
                                    <div>
                                        <label className="text-xs font-medium text-slate-600">Kawasan</label>
                                        <p className="text-sm text-slate-900 mt-1">{viewingScript.region}</p>
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-slate-600">Status</label>
                                        <p className="mt-1">
                                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${getStatusBadge(viewingScript.status).color}`}>
                                                {getStatusBadge(viewingScript.status).label}
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                {/* Questions List */}
                                <div className="space-y-4">
                                    <h3 className="font-semibold text-slate-900 mb-4">Senarai Soalan</h3>
                                    {sampleQuestions.map((question, index) => (
                                        <div key={question.id} className="border border-slate-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
                                            <div className="flex items-start space-x-3">
                                                <div className="flex-shrink-0 mt-1">
                                                    <GripVertical className="h-5 w-5 text-slate-400" />
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-start justify-between mb-2">
                                                        <div className="flex-1">
                                                            <div className="flex items-center space-x-2 mb-2">
                                                                <span className="text-sm font-medium text-slate-900">Soalan {index + 1}</span>
                                                                {question.required && (
                                                                    <span className="text-xs px-2 py-0.5 bg-rose-100 text-rose-700 rounded">Wajib</span>
                                                                )}
                                                                <span className={`text-xs px-2 py-0.5 rounded ${getSentimentBadge(question.sentiment).color}`}>
                                                                    Sentimen: {getSentimentBadge(question.sentiment).label}
                                                                </span>
                                                            </div>
                                                            <p className="text-slate-900 mb-3">{question.text}</p>
                                                            <div className="flex items-center space-x-4 text-sm">
                                                                <span className="text-slate-600">
                                                                    <span className="font-medium">Jenis:</span> {getTypeLabel(question.type)}
                                                                </span>
                                                            </div>
                                                            {question.options.length > 0 && (
                                                                <div className="mt-3">
                                                                    <p className="text-xs font-medium text-slate-600 mb-2">Pilihan Jawapan:</p>
                                                                    <div className="space-y-1">
                                                                        {question.options.map((option, idx) => (
                                                                            <div key={idx} className="flex items-center space-x-2 text-sm text-slate-700">
                                                                                <div className="h-1.5 w-1.5 bg-blue-600 rounded-full"></div>
                                                                                <span>{option}</span>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="p-6 border-t border-slate-200 flex items-center justify-end space-x-3">
                                <button
                                    onClick={() => setViewingScript(null)}
                                    className="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                                >
                                    Tutup
                                </button>
                                <button className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    Edit Skrip
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Create Script Modal */}
                {showCreateModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-xl max-w-2xl w-full">
                            <div className="p-6 border-b border-slate-200 flex items-center justify-between">
                                <h2 className="text-xl font-bold text-slate-900">Cipta Skrip Panggilan Baru</h2>
                                <button
                                    onClick={() => setShowCreateModal(false)}
                                    className="p-2 hover:bg-slate-100 rounded-lg transition-colors"
                                >
                                    <X className="h-5 w-5 text-slate-600" />
                                </button>
                            </div>

                            <div className="p-6 space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">Nama Skrip</label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: Skrip Tinjauan Kepuasan Pengundi 2026"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">Nama Kempen</label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: Kempen Jangkauan Komuniti"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-slate-700 mb-2">Negeri</label>
                                        <select className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option>Pilih Negeri</option>
                                            <option>Pulau Pinang</option>
                                            <option>Selangor</option>
                                            <option>Kuala Lumpur</option>
                                            <option>Johor</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-slate-700 mb-2">Kawasan</label>
                                        <select className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option>Pilih Kawasan</option>
                                            <option>Bayan Lepas</option>
                                            <option>Petaling Jaya</option>
                                            <option>Lembah Pantai</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">Status</label>
                                    <select className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="draft">Draf</option>
                                        <option value="active">Aktif</option>
                                        <option value="archived">Arkib</option>
                                    </select>
                                </div>
                            </div>

                            <div className="p-6 border-t border-slate-200 flex items-center justify-end space-x-3">
                                <button
                                    onClick={() => setShowCreateModal(false)}
                                    className="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                                >
                                    Batal
                                </button>
                                <button className="flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <Save className="h-4 w-4" />
                                    <span>Simpan & Tambah Soalan</span>
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
