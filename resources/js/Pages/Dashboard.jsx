import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { TrendingUp, Users, Database, Activity } from 'lucide-react';

export default function Dashboard() {
    const metrics = [
        {
            name: 'Jumlah Pengguna',
            value: '1,234',
            change: '+12.5%',
            changeType: 'increase',
            icon: Users,
            color: 'emerald'
        },
        {
            name: 'Data Aktif',
            value: '856',
            change: '+8.2%',
            changeType: 'increase',
            icon: Database,
            color: 'sky'
        },
        {
            name: 'Aktiviti Bulanan',
            value: '3,456',
            change: '+23.1%',
            changeType: 'increase',
            icon: Activity,
            color: 'amber'
        },
        {
            name: 'Pertumbuhan',
            value: '94%',
            change: '+4.3%',
            changeType: 'increase',
            icon: TrendingUp,
            color: 'rose'
        },
    ];

    const recentData = [
        { id: 1, name: 'Ahmad bin Ali', status: 'Aktif', date: '2025-11-19', value: 'RM 1,234' },
        { id: 2, name: 'Siti Nurhaliza', status: 'Aktif', date: '2025-11-18', value: 'RM 2,456' },
        { id: 3, name: 'Muhammad Hafiz', status: 'Pending', date: '2025-11-17', value: 'RM 890' },
        { id: 4, name: 'Nurul Ain', status: 'Aktif', date: '2025-11-16', value: 'RM 3,210' },
        { id: 5, name: 'Khairul Anuar', status: 'Tidak Aktif', date: '2025-11-15', value: 'RM 567' },
    ];

    const chartData = [
        { month: 'Jan', debit: 45, credit: 32 },
        { month: 'Feb', debit: 52, credit: 41 },
        { month: 'Mac', debit: 48, credit: 38 },
        { month: 'Apr', debit: 61, credit: 45 },
        { month: 'Mei', debit: 55, credit: 52 },
        { month: 'Jun', debit: 67, credit: 48 },
    ];

    const maxValue = Math.max(...chartData.flatMap(d => [d.debit, d.credit]));

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Page Header */}
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Dashboard</h1>
                    <p className="text-sm text-slate-600 mt-1">Selamat datang ke panel kawalan SISDA</p>
                </div>

                {/* Metrics Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {metrics.map((metric) => (
                        <div
                            key={metric.name}
                            className="bg-white rounded-xl border border-slate-200 p-6 hover:shadow-md transition-shadow duration-200"
                        >
                            <div className="flex items-center justify-between">
                                <div className={`p-3 rounded-lg bg-${metric.color}-50`}>
                                    <metric.icon className={`h-6 w-6 text-${metric.color}-600`} />
                                </div>
                                <span className={`text-sm font-medium ${metric.changeType === 'increase' ? 'text-emerald-600' : 'text-rose-600'
                                    }`}>
                                    {metric.change}
                                </span>
                            </div>
                            <div className="mt-4">
                                <p className="text-sm font-medium text-slate-600">{metric.name}</p>
                                <p className="text-2xl font-bold text-slate-900 mt-1">{metric.value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Charts and Table Row */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Bar Chart */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="text-lg font-semibold text-slate-900">Prestasi Bulanan</h2>
                            <div className="flex items-center space-x-4 text-sm">
                                <div className="flex items-center space-x-2">
                                    <div className="w-3 h-3 rounded-full bg-amber-500"></div>
                                    <span className="text-slate-600">Debit</span>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <div className="w-3 h-3 rounded-full bg-emerald-500"></div>
                                    <span className="text-slate-600">Kredit</span>
                                </div>
                            </div>
                        </div>
                        <div className="h-64 flex items-end justify-between space-x-2">
                            {chartData.map((data) => (
                                <div key={data.month} className="flex-1 flex flex-col items-center space-y-2">
                                    <div className="w-full flex items-end justify-center space-x-1 h-48">
                                        <div
                                            className="w-full bg-amber-500 rounded-t transition-all duration-300 hover:opacity-80"
                                            style={{ height: `${(data.debit / maxValue) * 100}%` }}
                                        ></div>
                                        <div
                                            className="w-full bg-emerald-500 rounded-t transition-all duration-300 hover:opacity-80"
                                            style={{ height: `${(data.credit / maxValue) * 100}%` }}
                                        ></div>
                                    </div>
                                    <span className="text-xs text-slate-600 font-medium">{data.month}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Recent Activity Table */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="text-lg font-semibold text-slate-900">Aktiviti Terkini</h2>
                            <button className="text-sm text-slate-600 hover:text-slate-900 font-medium">
                                Lihat Semua
                            </button>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-200">
                                        <th className="text-left py-3 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                            Nama
                                        </th>
                                        <th className="text-left py-3 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th className="text-left py-3 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                            Tarikh
                                        </th>
                                        <th className="text-right py-3 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                            Nilai
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recentData.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="py-3 px-2 text-sm text-slate-900">{item.name}</td>
                                            <td className="py-3 px-2">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${item.status === 'Aktif'
                                                        ? 'bg-emerald-100 text-emerald-800'
                                                        : item.status === 'Pending'
                                                            ? 'bg-amber-100 text-amber-800'
                                                            : 'bg-slate-100 text-slate-800'
                                                    }`}>
                                                    {item.status}
                                                </span>
                                            </td>
                                            <td className="py-3 px-2 text-sm text-slate-600">{item.date}</td>
                                            <td className="py-3 px-2 text-sm text-slate-900 text-right font-medium">{item.value}</td>
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
