import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';
import { Clock, CheckCircle, Mail, Phone } from 'lucide-react';

export default function PendingApproval() {
    return (
        <GuestLayout>
            <Head title="Menunggu Kelulusan" />

            <div className="text-center">
                {/* Icon */}
                <div className="flex justify-center mb-6">
                    <div className="p-4 bg-amber-100 rounded-full">
                        <Clock className="h-12 w-12 text-amber-600" />
                    </div>
                </div>

                {/* Title */}
                <h2 className="text-2xl font-bold text-slate-900 mb-2">
                    Pendaftaran Berjaya!
                </h2>

                {/* Message */}
                <p className="text-slate-600 mb-6">
                    Akaun anda sedang menunggu kelulusan daripada pentadbir.
                </p>

                {/* Info Box */}
                <div className="bg-slate-50 border border-slate-200 rounded-lg p-6 text-left mb-6">
                    <h3 className="font-semibold text-slate-900 mb-3 flex items-center">
                        <CheckCircle className="h-5 w-5 text-emerald-600 mr-2" />
                        Langkah Seterusnya
                    </h3>
                    <ul className="space-y-2 text-sm text-slate-600">
                        <li className="flex items-start">
                            <span className="inline-block w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">1</span>
                            <span>Pentadbir akan menyemak pendaftaran anda</span>
                        </li>
                        <li className="flex items-start">
                            <span className="inline-block w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">2</span>
                            <span>Anda akan menerima notifikasi setelah akaun diluluskan</span>
                        </li>
                        <li className="flex items-start">
                            <span className="inline-block w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">3</span>
                            <span>Log masuk menggunakan nombor telefon dan kata laluan anda</span>
                        </li>
                    </ul>
                </div>

                {/* Contact Info */}
                <div className="bg-sky-50 border border-sky-200 rounded-lg p-4 mb-6">
                    <p className="text-sm text-sky-900 font-medium mb-2">
                        Memerlukan bantuan?
                    </p>
                    <div className="flex flex-col space-y-2 text-sm text-sky-800">
                        <div className="flex items-center justify-center">
                            <Mail className="h-4 w-4 mr-2" />
                            <span>admin@sisda.com</span>
                        </div>
                        <div className="flex items-center justify-center">
                            <Phone className="h-4 w-4 mr-2" />
                            <span>0123456789</span>
                        </div>
                    </div>
                </div>

                {/* Back to Login */}
                <Link
                    href={route('login')}
                    className="inline-block px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                >
                    Kembali ke Log Masuk
                </Link>
            </div>
        </GuestLayout>
    );
}
