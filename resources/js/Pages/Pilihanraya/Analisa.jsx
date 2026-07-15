import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { MapPinned } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import KawasanPicker from './analisa/KawasanPicker';
import ComparisonBuilder from './analisa/ComparisonBuilder';
import KeanggotaanCard from './analisa/KeanggotaanCard';

function EmptyState() {
    const { t } = usePilihanrayaTheme();
    return (
        <div className={`${t.card} text-center py-16`}>
            <MapPinned className={`h-10 w-10 mx-auto mb-3 ${t.subtext}`} />
            <p className={`${t.text} font-medium`}>Pilih kawasan untuk mula</p>
            <p className={`${t.subtext} text-sm mt-1`}>
                Pilih Negeri → Parlimen (dan DUN jika perlu) di atas untuk memuat naik scoresheet
                dan menjana analisa AI bagi kawasan tersebut.
            </p>
        </div>
    );
}

/**
 * Analisa Keputusan — Malaysia-wide. Pick a kawasan (Parlimen or DUN), then the
 * comparison builder and Keanggotaan card appear, scoped to that kawasan.
 */
export default function Analisa({ geo, savedComparisons = [] }) {
    const [scope, setScope] = useState(null);
    const scopeKey = scope ? `${scope.level}-${scope.bandar_id}-${scope.kadun_id ?? 'all'}` : 'none';

    return (
        <AuthenticatedLayout>
            <Head title="Pilihanraya — Analisa Keputusan" />
            <PilihanrayaShell
                title="Analisa Keputusan Pilihanraya"
                subtitle="Pilih kawasan (Parlimen / DUN) seluruh Malaysia, muat naik scoresheet, dan jana analisa AI"
            >
                <KawasanPicker geo={geo} onChange={setScope} />

                {!scope ? (
                    <EmptyState />
                ) : (
                    <>
                        <div className="mb-6">
                            <ComparisonBuilder key={scopeKey} savedComparisons={savedComparisons} currentScope={scope} />
                        </div>
                        <div className="mb-6">
                            <KeanggotaanCard key={scopeKey} scope={scope} />
                        </div>
                    </>
                )}
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
