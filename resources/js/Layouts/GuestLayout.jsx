import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-slate-50 pt-6 sm:justify-center sm:pt-0">
            <div className="mb-6">
                <Link href="/">
                    <img src="/images/logo.png" alt="Logo" className="h-24 w-auto" />
                </Link>
            </div>

            <div className="w-full overflow-hidden bg-white px-6 py-8 shadow-sm border border-slate-200 sm:max-w-md sm:rounded-xl">
                {children}
            </div>
        </div>
    );
}
