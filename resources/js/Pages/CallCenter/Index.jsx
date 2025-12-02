import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Phone, Clock, Sparkles } from 'lucide-react';

export default function Index() {
    return (
        <AuthenticatedLayout>
            <Head title="Call Center" />

            <div className="min-h-[70vh] flex items-center justify-center">
                <div className="max-w-2xl mx-auto text-center space-y-8 px-6">
                    {/* Icon */}
                    <div className="relative inline-block">
                        <div className="absolute inset-0 bg-gradient-to-r from-sky-400 to-blue-500 rounded-full blur-2xl opacity-20 animate-pulse"></div>
                        <div className="relative bg-gradient-to-br from-sky-50 to-blue-50 p-8 rounded-full inline-block">
                            <Phone className="h-20 w-20 text-sky-600" />
                        </div>
                    </div>

                    {/* Title */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-center space-x-2">
                            <Sparkles className="h-6 w-6 text-amber-500 animate-pulse" />
                            <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-900 via-sky-800 to-blue-900 bg-clip-text text-transparent">
                                Call Center
                            </h1>
                            <Sparkles className="h-6 w-6 text-amber-500 animate-pulse" />
                        </div>
                        <p className="text-xl text-slate-600 font-medium">
                            Modul Dalam Pembangunan
                        </p>
                    </div>

                    {/* Description */}
                    <div className="bg-white rounded-2xl border border-slate-200 p-8 shadow-sm">
                        <div className="flex items-start space-x-4">
                            <div className="flex-shrink-0">
                                <Clock className="h-6 w-6 text-sky-600 mt-1" />
                            </div>
                            <div className="text-left space-y-3">
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Akan Datang Tidak Lama Lagi
                                </h3>
                                <p className="text-slate-600 leading-relaxed">
                                    Kami sedang membangunkan sistem Call Center yang komprehensif untuk memudahkan
                                    pengurusan panggilan dan komunikasi dengan pengundi. Modul ini akan menyediakan:
                                </p>
                                <ul className="space-y-2 text-slate-600">
                                    <li className="flex items-start space-x-2">
                                        <span className="text-sky-600 font-bold mt-1">•</span>
                                        <span>Sistem pengurusan panggilan automatik</span>
                                    </li>
                                    <li className="flex items-start space-x-2">
                                        <span className="text-sky-600 font-bold mt-1">•</span>
                                        <span>Rekod komunikasi dengan pengundi</span>
                                    </li>
                                    <li className="flex items-start space-x-2">
                                        <span className="text-sky-600 font-bold mt-1">•</span>
                                        <span>Laporan dan analitik panggilan</span>
                                    </li>
                                    <li className="flex items-start space-x-2">
                                        <span className="text-sky-600 font-bold mt-1">•</span>
                                        <span>Integrasi dengan data pengundi</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {/* Status Badge */}
                    <div className="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-sky-50 to-blue-50 border border-sky-200 rounded-full">
                        <div className="h-2 w-2 bg-sky-500 rounded-full animate-pulse"></div>
                        <span className="text-sm font-medium text-sky-700">
                            Status: Dalam Pembangunan
                        </span>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
