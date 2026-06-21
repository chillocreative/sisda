import { Link } from '@inertiajs/react';
import { Upload, List, BarChart3, Settings } from 'lucide-react';

const TABS = [
    { name: 'Muat Naik', route: 'keanggotaan.index', match: 'keanggotaan.index', icon: Upload },
    { name: 'Senarai Ahli', route: 'keanggotaan.senarai', match: 'keanggotaan.senarai', icon: List },
    { name: 'Analisa', route: 'keanggotaan.analisa', match: 'keanggotaan.analisa', icon: BarChart3 },
    { name: 'Tetapan', route: 'keanggotaan.tetapan', match: 'keanggotaan.tetapan', icon: Settings },
];

export default function KeanggotaanNav() {
    return (
        <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
            {TABS.map((tab) => {
                const active = route().current(tab.match);
                const Icon = tab.icon;
                return (
                    <Link
                        key={tab.name}
                        href={route(tab.route)}
                        className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium ${
                            active ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'
                        }`}
                    >
                        <Icon className="h-4 w-4" />
                        {tab.name}
                    </Link>
                );
            })}
        </div>
    );
}
