import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EMPTY_FILTERS } from './filters';
import PilihanrayaShell from './components/PilihanrayaShell';
import FilterBar from './components/FilterBar';
import SimulasiPilihanraya from './components/SimulasiPilihanraya';

export default function Simulasi({ negeriList, parlimenList, kadunList, simulasiParties = [], penjuruOptions = [] }) {
    const [filters, setFilters] = useState(EMPTY_FILTERS);

    return (
        <AuthenticatedLayout>
            <Head title="Pilihanraya — Simulasi Pilihanraya" />
            <PilihanrayaShell
                title="Simulasi Pilihanraya"
                subtitle="Simulasi kerusi mengikut kaum — pertandingan 1 lawan 1 hingga 6 penjuru"
            >
                <FilterBar
                    filters={filters}
                    onChange={setFilters}
                    negeriList={negeriList}
                    parlimenList={parlimenList}
                    kadunList={kadunList}
                    showDates={false}
                />
                <SimulasiPilihanraya
                    filters={filters}
                    simulasiParties={simulasiParties}
                    penjuruOptions={penjuruOptions}
                />
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
