import { Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    LayoutDashboard,
    Users,
    Database,
    FileText,
    User,
    Settings,
    LogOut,
    Menu,
    X,
    ChevronDown,
    ChevronRight,
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
    ClipboardList,
    UserCheck,
    UserCircle,
    Map,
    Phone,
    Bell
} from 'lucide-react';

export default function AuthenticatedLayout({ children }) {
    const { user, pendingApprovalsCount } = usePage().props.auth;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [openDropdown, setOpenDropdown] = useState(null); // Track which dropdown is open

    console.log('=== SISDA Layout v2.0 ===');
    console.log('User:', user);
    console.log('Pending Approvals Count:', pendingApprovalsCount);
    console.log('Type of count:', typeof pendingApprovalsCount);
    console.log('Count > 0?', pendingApprovalsCount > 0);


    const masterDataSubmenu = [
        ...(user.role === 'super_admin' ? [{ name: 'Negeri', href: route('master-data.negeri.index'), icon: MapPin }] : []),
        { name: 'Parlimen', href: route('master-data.parlimen.index'), icon: Landmark },
        { name: 'Bandar', href: route('master-data.bandar.index'), icon: Building2 },
        { name: 'KADUN', href: route('master-data.kadun.index'), icon: Vote },
        { name: 'MPKK', href: route('master-data.mpkk.index'), icon: Users2 },
        { name: 'Daerah Mengundi', href: route('master-data.daerah-mengundi.index'), icon: Map },
        { name: 'Tujuan Sumbangan', href: route('master-data.tujuan-sumbangan.index'), icon: Gift },
        { name: 'Jenis Sumbangan', href: route('master-data.jenis-sumbangan.index'), icon: Package },
        { name: 'Bantuan Lain', href: route('master-data.bantuan-lain.index'), icon: HandHeart },
        { name: 'Keahlian Parti', href: route('master-data.keahlian-parti.index'), icon: Flag },
        { name: 'Kecenderungan Politik', href: route('master-data.kecenderungan-politik.index'), icon: TrendingUp },

        { name: 'Bangsa', href: route('master-data.bangsa.index'), icon: UserCircle },
    ];

    const laporanSubmenu = [
        { name: 'Hasil Culaan', href: route('reports.hasil-culaan.index'), icon: ClipboardList },
        { name: 'Data Pengundi', href: route('reports.data-pengundi.index'), icon: UserCheck },
    ];

    // User role Laporan submenu (list views only)
    const userLaporanSubmenu = [
        { name: 'Hasil Culaan', href: route('reports.hasil-culaan.index'), icon: ClipboardList },
        { name: 'Data Pengundi', href: route('reports.data-pengundi.index'), icon: UserCheck },
    ];

    const navigation = [
        { name: 'Dashboard', href: route('dashboard'), icon: LayoutDashboard, current: route().current('dashboard') },
        // User Approval (Super Admin and Admin only)
        ...(user.role === 'super_admin' || user.role === 'admin' ? [
            { name: 'Kelulusan Pengguna', href: route('user-approval.index'), icon: UserCheck, current: route().current('user-approval.*') }
        ] : []),
        // Pengguna menu (Super Admin and Admin only)
        ...(user.role === 'super_admin' || user.role === 'admin' ? [
            { name: 'Pengguna', href: route('users.index'), icon: Users, current: route().current('users.*') }
        ] : []),

        // Data Induk menu (Super Admin and Admin only)
        ...(user.role === 'super_admin' || user.role === 'admin' ? [
            {
                name: 'Data Induk',
                href: route('master-data.index'),
                icon: Database,
                current: route().current('master-data.*'),
                hasSubmenu: true,
                submenu: masterDataSubmenu
            }
        ] : []),
        // For User role: Direct create links
        ...(user.role === 'user' ? [
            { name: 'Mula Culaan', href: route('reports.hasil-culaan.create'), icon: ClipboardList, current: route().current('reports.hasil-culaan.create') },
            { name: 'Data Pengundi', href: route('reports.data-pengundi.create'), icon: UserCheck, current: route().current('reports.data-pengundi.create') }
        ] : []),
        // Laporan menu
        ...(user.role === 'super_admin' || user.role === 'admin' ? [
            {
                name: 'Laporan',
                href: route('reports.index'),
                icon: FileText,
                current: route().current('reports.*'),
                hasSubmenu: true,
                submenu: laporanSubmenu
            }
        ] : []),
        // Laporan menu for User role (list views) - only highlight when on index pages
        ...(user.role === 'user' ? [
            {
                name: 'Laporan',
                href: route('reports.index'),
                icon: FileText,
                current: route().current('reports.hasil-culaan.index') || route().current('reports.data-pengundi.index'),
                hasSubmenu: true,
                submenu: userLaporanSubmenu
            }
        ] : []),
        // Call Center (Super Admin only)
        ...(user.role === 'super_admin' ? [
            { name: 'Call Center', href: route('call-center.index'), icon: Phone, current: route().current('call-center.*') }
        ] : []),
        { name: 'Profil', href: route('profile.edit'), icon: User, current: route().current('profile.edit') },
    ];


    const toggleDropdown = (dropdownName) => (e) => {
        e.preventDefault();
        // If clicking the same dropdown, close it; otherwise, open the new one
        setOpenDropdown(prevOpen => prevOpen === dropdownName ? null : dropdownName);
    };

    // Automatically open the dropdown based on current route
    useEffect(() => {
        if (route().current('master-data.*')) {
            setOpenDropdown('Data Induk');
        } else if (route().current('reports.*')) {
            setOpenDropdown('Laporan');
        }
    }, [route().current()]);


    return (
        <div className="min-h-screen bg-slate-100">
            {/* Mobile sidebar backdrop */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 bg-slate-900/50 z-40 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside className={`
                fixed top-0 left-0 z-50 h-full w-64 bg-white border-r border-slate-200 
                transform transition-transform duration-300 ease-in-out
                ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
                lg:translate-x-0
            `}>
                <div className="flex flex-col h-full">
                    {/* Logo */}
                    <div className="flex items-center justify-between h-16 px-6 border-b border-slate-200">
                        <Link href={route('dashboard')} className="flex items-center space-x-3">
                            <img src="/images/logo-sisda.png" alt="SISDA" className="h-8 w-auto" />
                            <span className="text-xl font-bold text-slate-900">SISDA</span>
                        </Link>
                        <button
                            onClick={() => setSidebarOpen(false)}
                            className="lg:hidden text-slate-500 hover:text-slate-700"
                        >
                            <X className="h-6 w-6" />
                        </button>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                        {navigation.map((item) => (
                            <div key={item.name}>
                                {item.hasSubmenu ? (
                                    <>
                                        <button
                                            onClick={toggleDropdown(item.name)}
                                            className={`
                                                w-full flex items-center justify-between px-4 py-3 rounded-lg text-sm font-medium
                                                transition-colors duration-150
                                                ${item.current
                                                    ? 'bg-slate-900 text-white'
                                                    : 'text-slate-700 hover:bg-slate-100'
                                                }
                                            `}
                                        >
                                            <div className="flex items-center space-x-3">
                                                <item.icon className="h-5 w-5" />
                                                <span>{item.name}</span>
                                            </div>
                                            {openDropdown === item.name ? (
                                                <ChevronDown className="h-4 w-4" />
                                            ) : (
                                                <ChevronRight className="h-4 w-4" />
                                            )}
                                        </button>
                                        {openDropdown === item.name && (
                                            <div className="mt-1 ml-4 space-y-1">
                                                <Link
                                                    href={item.href}
                                                    className="flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100 transition-colors"
                                                >
                                                    {item.name === 'Data Induk' ? (
                                                        <Database className="h-4 w-4" />
                                                    ) : (
                                                        <FileText className="h-4 w-4" />
                                                    )}
                                                    <span>{item.name === 'Data Induk' ? 'Semua Data' : 'Semua Laporan'}</span>
                                                </Link>
                                                {item.submenu.map((subitem) => (
                                                    <Link
                                                        key={subitem.name}
                                                        href={subitem.href}
                                                        className="flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100 transition-colors"
                                                    >
                                                        <subitem.icon className="h-4 w-4" />
                                                        <span>{subitem.name}</span>
                                                    </Link>
                                                ))}
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    <Link
                                        href={item.href}
                                        className={`
                                            flex items-center justify-between px-4 py-3 rounded-lg text-sm font-medium
                                            transition-colors duration-150
                                            ${item.current
                                                ? 'bg-slate-900 text-white'
                                                : 'text-slate-700 hover:bg-slate-100'
                                            }
                                        `}
                                    >
                                        <div className="flex items-center space-x-3">
                                            <item.icon className="h-5 w-5" />
                                            <span>{item.name}</span>
                                        </div>
                                        {item.name === 'Kelulusan Pengguna' && pendingApprovalsCount > 0 && (
                                            <div className="flex items-center justify-center bg-red-100 text-red-600 rounded-full px-2 py-0.5 ml-auto">
                                                <Bell className="h-4 w-4 mr-1" />
                                                <span className="text-xs font-bold">{pendingApprovalsCount}</span>
                                            </div>
                                        )}
                                    </Link>
                                )}
                            </div>
                        ))}
                    </nav>

                    {/* User section */}
                    <div className="border-t border-slate-200 p-4">
                        <div className="flex items-center space-x-3 px-4 py-3">
                            <div className="flex-shrink-0 h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center">
                                <User className="h-6 w-6 text-slate-600" />
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-slate-900 truncate">
                                    {user.name}
                                </p>
                                <p className="text-xs text-slate-500 truncate">
                                    {user.telephone}
                                </p>
                            </div>
                        </div>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-100 transition-colors duration-150"
                        >
                            <LogOut className="h-5 w-5" />
                            <span>Log Keluar</span>
                        </Link>
                    </div>
                </div>
            </aside>

            {/* Main content */}
            <div className="lg:pl-64">
                {/* Top bar */}
                <header className="sticky top-0 z-30 bg-white border-b border-slate-200">
                    <div className="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="lg:hidden text-slate-500 hover:text-slate-700"
                        >
                            <Menu className="h-6 w-6" />
                        </button>
                        <div className="flex-1" />
                        <div className="flex items-center space-x-4">
                            <span className="text-sm text-slate-600">
                                {new Date().toLocaleDateString('ms-MY', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                })}
                            </span>
                        </div>
                    </div>
                </header>

                {/* Page content */}
                <main className="p-4 sm:p-6 lg:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
