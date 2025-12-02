import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import {
    MapPin,
    Building2,
    Landmark,
    Vote,
    Users2,
    Gift,
    Package,
    HandHeart,
    Flag,
    TrendingUp,
    Heart,
    ArrowRight
} from 'lucide-react';

const iconMap = {
    MapPin,
    Building2,
    Landmark,
    Vote,
    Users2,
    Gift,
    Package,
    HandHeart,
    Flag,
    TrendingUp,
    Heart
};

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
    },
    violet: {
        bg: 'bg-violet-50',
        text: 'text-violet-600',
        border: 'border-violet-200',
        hover: 'hover:border-violet-300'
    },
    amber: {
        bg: 'bg-amber-50',
        text: 'text-amber-600',
        border: 'border-amber-200',
        hover: 'hover:border-amber-300'
    },
    rose: {
        bg: 'bg-rose-50',
        text: 'text-rose-600',
        border: 'border-rose-200',
        hover: 'hover:border-rose-300'
    },
    cyan: {
        bg: 'bg-cyan-50',
        text: 'text-cyan-600',
        border: 'border-cyan-200',
        hover: 'hover:border-cyan-300'
    },
    indigo: {
        bg: 'bg-indigo-50',
        text: 'text-indigo-600',
        border: 'border-indigo-200',
        hover: 'hover:border-indigo-300'
    },
    pink: {
        bg: 'bg-pink-50',
        text: 'text-pink-600',
        border: 'border-pink-200',
        hover: 'hover:border-pink-300'
    },
    orange: {
        bg: 'bg-orange-50',
        text: 'text-orange-600',
        border: 'border-orange-200',
        hover: 'hover:border-orange-300'
    },
    teal: {
        bg: 'bg-teal-50',
        text: 'text-teal-600',
        border: 'border-teal-200',
        hover: 'hover:border-teal-300'
    },
    red: {
        bg: 'bg-red-50',
        text: 'text-red-600',
        border: 'border-red-200',
        hover: 'hover:border-red-300'
    },
    slate: {
        bg: 'bg-slate-50',
        text: 'text-slate-600',
        border: 'border-slate-200',
        hover: 'hover:border-slate-300'
    },
    blue: {
        bg: 'bg-blue-50',
        text: 'text-blue-600',
        border: 'border-blue-200',
        hover: 'hover:border-blue-300'
    }
};

export default function Index({ categories }) {
    const totalRecords = categories.reduce((sum, cat) => sum + cat.count, 0);

    return (
        <AuthenticatedLayout>
            <Head title="Data Induk" />

            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Data Induk</h1>
                        <p className="text-sm text-slate-600 mt-1">Urus data utama sistem</p>
                    </div>
                    <div className="bg-white rounded-xl border border-slate-200 px-6 py-4">
                        <p className="text-sm font-medium text-slate-600">Jumlah Rekod</p>
                        <p className="text-3xl font-bold text-slate-900 mt-1">{totalRecords.toLocaleString()}</p>
                    </div>
                </div>

                {/* Categories Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    {categories.map((category) => {
                        const Icon = iconMap[category.icon];
                        const colors = colorClasses[category.color] || colorClasses.slate; // Fallback to slate

                        return (
                            <button
                                key={category.name}
                                onClick={() => category.route && router.visit(route(category.route))}
                                className={`
                                    group bg-white rounded-xl border-2 ${colors.border} ${colors.hover}
                                    p-6 text-left transition-all duration-200
                                    hover:shadow-lg hover:-translate-y-1
                                `}
                            >
                                <div className="flex items-start justify-between mb-4">
                                    <div className={`p-3 rounded-lg ${colors.bg}`}>
                                        <Icon className={`h-6 w-6 ${colors.text}`} />
                                    </div>
                                    <ArrowRight className="h-5 w-5 text-slate-400 group-hover:text-slate-600 transition-colors" />
                                </div>

                                <h3 className="text-lg font-semibold text-slate-900 mb-1">
                                    {category.name}
                                </h3>
                                <p className="text-sm text-slate-600 mb-3">
                                    {category.description}
                                </p>

                                <div className="flex items-baseline space-x-2">
                                    <span className="text-2xl font-bold text-slate-900">
                                        {category.count}
                                    </span>
                                    <span className="text-sm text-slate-500">
                                        rekod
                                    </span>
                                </div>
                            </button>
                        );
                    })}
                </div>


            </div>
        </AuthenticatedLayout>
    );
}
