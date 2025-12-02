import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ClipboardList, UserCheck, FileSpreadsheet, Calendar } from 'lucide-react';

export default function Index({ stats }) {
    const reports = [
        {
            name: 'Hasil Culaan',
            description: 'Laporan data hasil culaan lengkap',
            count: stats.hasil_culaan,
            icon: ClipboardList,
            color: 'emerald',
            href: route('reports.hasil-culaan.index')
        },
        {
            name: 'Data Pengundi',
            description: 'Laporan data pengundi',
            count: stats.data_pengundi,
            icon: UserCheck,
            color: 'sky',
            href: route('reports.data-pengundi.index')
        },
    ];

    const colorClasses = {
        emerald: {
            bg: 'bg-emerald-50',
            text: 'text-emerald-600',
            border: 'border-emerald-200',
            hover: 'hover:border-emerald-300'
        },
        sky: {
            bg: 'bg-sky-50',
            text: 'text-sky-600',
            border: 'border-sky-200',
            hover: 'hover:border-sky-300'
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Laporan" />

            <div className="space-y-6">
                {/* Page Header */}
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Laporan</h1>
                    <p className="text-sm text-slate-600 mt-1">Akses dan urus laporan sistem</p>
                </div>

                {/* Report Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {reports.map((report) => {
                        const Icon = report.icon;
                        const colors = colorClasses[report.color];

                        return (
                            <Link
                                key={report.name}
                                href={report.href}
                                className={`
                                    group bg-white rounded-xl border-2 ${colors.border} ${colors.hover}
                                    p-8 transition-all duration-200
                                    hover:shadow-lg hover:-translate-y-1
                                `}
                            >
                                <div className="flex items-start justify-between mb-6">
                                    <div className={`p-4 rounded-xl ${colors.bg}`}>
                                        <Icon className={`h-8 w-8 ${colors.text}`} />
                                    </div>
                                    <FileSpreadsheet className="h-6 w-6 text-slate-400 group-hover:text-slate-600 transition-colors" />
                                </div>

                                <h3 className="text-xl font-semibold text-slate-900 mb-2">
                                    {report.name}
                                </h3>
                                <p className="text-sm text-slate-600 mb-4">
                                    {report.description}
                                </p>

                                <div className="flex items-baseline space-x-2">
                                    <span className="text-3xl font-bold text-slate-900">
                                        {report.count.toLocaleString()}
                                    </span>
                                    <span className="text-sm text-slate-500">
                                        rekod
                                    </span>
                                </div>
                            </Link>
                        );
                    })}
                </div>


            </div>
        </AuthenticatedLayout>
    );
}
