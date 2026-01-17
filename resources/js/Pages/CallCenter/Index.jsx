import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import {
    Phone,
    PhoneCall,
    PhoneIncoming,
    PhoneOutgoing,
    Users,
    BarChart3,
    MessageSquare,
    TrendingUp,
    Clock,
    CheckCircle2,
    AlertCircle,
    Database,
    FileText,
    Headphones,
    Activity,
    Zap
} from 'lucide-react';

export default function Index() {
    const features = [
        {
            icon: PhoneCall,
            title: 'Pengurusan Panggilan Automatik',
            description: 'Sistem penghalaan panggilan pintar dan pendailan automatik untuk jangkauan pengundi yang cekap',
            color: 'blue'
        },
        {
            icon: MessageSquare,
            title: 'Rekod Interaksi Pengundi',
            description: 'Rakaman dan penjejakan menyeluruh semua komunikasi dan respons pengundi',
            color: 'emerald'
        },
        {
            icon: TrendingUp,
            title: 'Penjejakan Sentimen & Isu',
            description: 'Analisis masa nyata sentimen pengundi dan pengenalpastian isu politik utama',
            color: 'amber'
        },
        {
            icon: BarChart3,
            title: 'Analitik & Pelaporan Panggilan',
            description: 'Papan pemuka analitik terperinci dengan metrik prestasi dan pandangan mendalam',
            color: 'purple'
        },
        {
            icon: Database,
            title: 'Integrasi Pangkalan Data Pengundi',
            description: 'Integrasi lancar dengan pendaftaran pengundi dan data demografi sedia ada',
            color: 'sky'
        },
        {
            icon: Headphones,
            title: 'Pengurusan Ejen',
            description: 'Pantau prestasi ejen, kualiti panggilan, dan metrik produktiviti',
            color: 'rose'
        }
    ];

    const stats = [
        { label: 'Jumlah Panggilan', value: '---', icon: Phone, color: 'blue' },
        { label: 'Ejen Aktif', value: '---', icon: Users, color: 'emerald' },
        { label: 'Purata Tempoh Panggilan', value: '---', icon: Clock, color: 'amber' },
        { label: 'Kadar Kejayaan', value: '---', icon: CheckCircle2, color: 'purple' }
    ];

    const getColorClasses = (color) => {
        const colors = {
            blue: 'bg-blue-50 text-blue-600 border-blue-100',
            emerald: 'bg-emerald-50 text-emerald-600 border-emerald-100',
            amber: 'bg-amber-50 text-amber-600 border-amber-100',
            purple: 'bg-purple-50 text-purple-600 border-purple-100',
            sky: 'bg-sky-50 text-sky-600 border-sky-100',
            rose: 'bg-rose-50 text-rose-600 border-rose-100'
        };
        return colors[color] || colors.blue;
    };

    return (
        <AuthenticatedLayout>
            <Head title="Call Center" />

            <div className="space-y-6">
                {/* Header Section */}
                <div className="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl p-8 text-white shadow-lg">
                    <div className="flex items-center justify-between">
                        <div className="space-y-2">
                            <div className="flex items-center space-x-3">
                                <div className="p-3 bg-white/10 backdrop-blur-sm rounded-lg">
                                    <Phone className="h-8 w-8" />
                                </div>
                                <div>
                                    <h1 className="text-3xl font-bold">Pusat Panggilan</h1>
                                    <p className="text-blue-100 text-sm">Modul Komunikasi & Analitik Politik</p>
                                </div>
                            </div>
                        </div>
                        <div className="hidden md:flex items-center space-x-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-lg border border-white/20">
                            <div className="h-2 w-2 bg-amber-400 rounded-full animate-pulse"></div>
                            <span className="text-sm font-medium">Dalam Pembangunan</span>
                        </div>
                    </div>
                </div>

                {/* Status Card */}
                <div className="bg-gradient-to-br from-amber-50 to-orange-50 border-2 border-amber-200 rounded-xl p-6">
                    <div className="flex items-start space-x-4">
                        <div className="flex-shrink-0">
                            <div className="p-3 bg-amber-100 rounded-lg">
                                <AlertCircle className="h-6 w-6 text-amber-600" />
                            </div>
                        </div>
                        <div className="flex-1">
                            <h3 className="text-lg font-semibold text-amber-900 mb-2">
                                Modul Dalam Pembangunan
                            </h3>
                            <p className="text-amber-800 leading-relaxed">
                                Kami sedang membangunkan sistem Pusat Panggilan yang komprehensif yang direka khusus untuk organisasi politik.
                                Modul ini akan membolehkan jangkauan pengundi yang cekap, analisis sentimen, dan strategi kempen berasaskan data.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {stats.map((stat, index) => (
                        <div key={index} className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between mb-4">
                                <div className={`p-3 rounded-lg ${getColorClasses(stat.color)}`}>
                                    <stat.icon className="h-5 w-5" />
                                </div>
                            </div>
                            <div>
                                <p className="text-sm text-slate-600 mb-1">{stat.label}</p>
                                <p className="text-2xl font-bold text-slate-900">{stat.value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Features Grid */}
                <div>
                    <div className="mb-6">
                        <h2 className="text-xl font-bold text-slate-900 mb-2">Ciri-ciri Dirancang</h2>
                        <p className="text-slate-600">Alat komprehensif untuk komunikasi politik dan penglibatan pengundi</p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {features.map((feature, index) => (
                            <div
                                key={index}
                                className="bg-white rounded-xl border border-slate-200 p-6 hover:border-blue-300 hover:shadow-lg transition-all duration-300 group"
                            >
                                <div className={`inline-flex p-3 rounded-lg mb-4 ${getColorClasses(feature.color)} group-hover:scale-110 transition-transform`}>
                                    <feature.icon className="h-6 w-6" />
                                </div>
                                <h3 className="text-lg font-semibold text-slate-900 mb-2">
                                    {feature.title}
                                </h3>
                                <p className="text-slate-600 text-sm leading-relaxed">
                                    {feature.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>

                {/* System Capabilities */}
                <div className="bg-white rounded-xl border border-slate-200 p-8 shadow-sm">
                    <div className="flex items-start space-x-4 mb-6">
                        <div className="p-3 bg-blue-50 rounded-lg">
                            <Activity className="h-6 w-6 text-blue-600" />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold text-slate-900 mb-2">Keupayaan Sistem</h2>
                            <p className="text-slate-600">Ciri-ciri canggih untuk pengurusan kempen politik</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-4">
                            <div className="flex items-start space-x-3">
                                <PhoneOutgoing className="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Kempen Keluar</h4>
                                    <p className="text-sm text-slate-600">Pendailan automatik untuk tinjauan pengundi dan pemesejan kempen</p>
                                </div>
                            </div>
                            <div className="flex items-start space-x-3">
                                <PhoneIncoming className="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Sokongan Masuk</h4>
                                    <p className="text-sm text-slate-600">Kendalikan pertanyaan dan maklum balas pengundi dengan cekap</p>
                                </div>
                            </div>
                            <div className="flex items-start space-x-3">
                                <FileText className="h-5 w-5 text-purple-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Skrip Panggilan</h4>
                                    <p className="text-sm text-slate-600">Skrip dinamik berdasarkan demografi pengundi dan isu</p>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div className="flex items-start space-x-3">
                                <BarChart3 className="h-5 w-5 text-amber-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Analitik Masa Nyata</h4>
                                    <p className="text-sm text-slate-600">Papan pemuka langsung dengan metrik panggilan dan penunjuk prestasi</p>
                                </div>
                            </div>
                            <div className="flex items-start space-x-3">
                                <Database className="h-5 w-5 text-sky-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Integrasi Data</h4>
                                    <p className="text-sm text-slate-600">Penyegerakan lancar dengan pendaftaran pengundi dan pangkalan data demografi</p>
                                </div>
                            </div>
                            <div className="flex items-start space-x-3">
                                <Zap className="h-5 w-5 text-rose-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Pandangan Berkuasa AI</h4>
                                    <p className="text-sm text-slate-600">Analisis sentimen dan pemodelan tingkah laku pengundi ramalan</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Coming Soon Footer */}
                <div className="bg-gradient-to-r from-slate-50 to-slate-100 rounded-xl border border-slate-200 p-6 text-center">
                    <div className="inline-flex items-center justify-center space-x-2 mb-3">
                        <Clock className="h-5 w-5 text-slate-600" />
                        <span className="text-lg font-semibold text-slate-900">Akan Datang Tidak Lama Lagi</span>
                    </div>
                    <p className="text-slate-600 max-w-2xl mx-auto">
                        Pasukan pembangunan kami sedang bekerja keras untuk membawa anda penyelesaian Pusat Panggilan yang terkini.
                        Nantikan kemas kini mengenai garis masa pelancaran.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
