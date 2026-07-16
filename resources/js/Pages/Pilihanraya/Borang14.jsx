import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { PencilLine, Table2, Upload } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell from './components/PilihanrayaShell';
import TabBar from './components/TabBar';
import KeyinTab from './borang14/KeyinTab';

const TABS = [
    { key: 'upload', label: 'Upload Scoresheet', icon: Upload },
    { key: 'papar', label: 'Papar', icon: Table2 },
    { key: 'keyin', label: 'Keyin', icon: PencilLine },
];

export default function Borang14({ negeriList, parlimenList, kadunList, partiList, penjuruOptions }) {
    const [tab, setTab] = useState(() =>
        new URLSearchParams(window.location.search).get('tab') || 'keyin');
    const [keyinPrefill, setKeyinPrefill] = useState(null);

    const changeTab = (key) => {
        setTab(key);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', key);
        window.history.replaceState({}, '', url);
    };

    const openKeyin = (prefill) => {
        setKeyinPrefill({ ...prefill, nonce: Date.now() });
        changeTab('keyin');
    };

    return (
        <AuthenticatedLayout>
            <Head title="Borang 14" />
            <PilihanrayaShell title="Borang 14" subtitle="Upload scoresheet SPR, papar sejarah keputusan & keyin undi mengikut saluran">
                <div className="mb-4">
                    <TabBar tabs={TABS} active={tab} onChange={changeTab} />
                </div>

                {tab === 'upload' && (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                        Upload Scoresheet akan tersedia tidak lama lagi.
                    </div>
                )}

                {tab === 'papar' && (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                        Papar akan tersedia tidak lama lagi.
                    </div>
                )}

                {/* Keyin stays mounted so half-filled entry is not lost when peeking at other tabs. */}
                <div className={tab === 'keyin' ? '' : 'hidden'}>
                    <KeyinTab
                        negeriList={negeriList}
                        parlimenList={parlimenList}
                        kadunList={kadunList}
                        partiList={partiList}
                        penjuruOptions={penjuruOptions}
                        prefill={keyinPrefill}
                    />
                </div>
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
