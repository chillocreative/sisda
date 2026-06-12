import { useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    BrainCircuit, Crosshair, FileText, Loader2, Megaphone, Scale, Sparkles, SlidersHorizontal, Swords,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EMPTY_FILTERS, cleanParams } from './filters';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import TabBar from './components/TabBar';
import FilterBar from './components/FilterBar';
import KpiCard from './components/KpiCard';
import ForecastGauge from './components/ForecastGauge';
import SliderPanel from './components/SliderPanel';
import ScenarioChat from './components/ScenarioChat';
import ResourcePanel from './components/ResourcePanel';
import BriefingViewer from './components/BriefingViewer';
import { DEFAULT_SLIDERS, projectAll } from './simulation/whatIfModel';
import { CHART_COLORS } from './theme';

const TABS = [
    { key: 'ramalan', label: 'Ramalan', icon: BrainCircuit },
    { key: 'whatif', label: 'What-If', icon: SlidersHorizontal },
    { key: 'wargame', label: 'War Gaming', icon: Swords },
    { key: 'sumber', label: 'Sumber', icon: Megaphone },
    { key: 'taklimat', label: 'Taklimat', icon: FileText },
];

function FallbackBanner({ show }) {
    const { t } = usePilihanrayaTheme();
    if (!show) return null;

    return (
        <div className={`${t.banner} mb-4`}>
            AI tidak tersedia — unjuran deterministik dipaparkan. Aktifkan Tetapan Claude AI untuk analisis penuh.
        </div>
    );
}

function RamalanTab({ filters, latestForecast }) {
    const { t } = usePilihanrayaTheme();
    const [forecast, setForecast] = useState(latestForecast);
    const [loading, setLoading] = useState(false);
    const [runError, setRunError] = useState(null);

    const run = async () => {
        setLoading(true);
        setRunError(null);
        try {
            const res = await axios.post(route('pilihanraya.api.forecast'), cleanParams(filters), { timeout: 130000 });
            setForecast(res.data);
        } catch {
            // keep any previous forecast visible; the button is retryable
            setRunError('Ramalan gagal dijana — pelayan tidak memberi respons. Sila cuba semula.');
        } finally {
            setLoading(false);
        }
    };

    const result = forecast?.result;

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className={`${t.subtext} text-sm`}>
                    {forecast?.generated_at
                        ? `Ramalan terakhir: ${new Date(forecast.generated_at).toLocaleString('ms-MY')}`
                        : 'Belum ada ramalan dijana.'}
                </p>
                <button type="button" onClick={run} disabled={loading} className={t.buttonPrimary}>
                    {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
                    {loading ? 'Menganalisis…' : 'Jana Ramalan AI'}
                </button>
            </div>

            {runError && <div className={t.banner}>{runError}</div>}

            {!result && !loading && (
                <div className={t.card}>
                    <p className={`${t.subtext} text-sm`}>
                        Tekan "Jana Ramalan AI" — Claude menganalisis agregat sentimen, demografi, liputan dan tren
                        setiap kerusi untuk menghasilkan kebarangkalian kemenangan dan unjuran majoriti.
                    </p>
                </div>
            )}

            {result && (
                <>
                    <FallbackBanner show={forecast.status === 'fallback'} />
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <ForecastGauge label="Kebarangkalian PH Menang" value={result.ph_win_probability} color={CHART_COLORS.putih} />
                        <ForecastGauge label="Kebarangkalian Berayun" value={result.swing_probability} color={CHART_COLORS.amber} />
                        <ForecastGauge label="Skor Risiko" value={result.risk_score} color={CHART_COLORS.hitam} />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <KpiCard label="Kebarangkalian Pembangkang" value={`${result.opposition_win_probability}%`} icon={Swords} iconBg="bg-red-500/15" iconColor="text-red-500" />
                        <KpiCard label="Unjuran Majoriti Kerusi" value={result.expected_majority > 0 ? `+${result.expected_majority}` : result.expected_majority} icon={Scale} iconBg="bg-blue-500/15" iconColor="text-blue-500" />
                        <KpiCard label="Tahap Keyakinan" value={result.confidence.toUpperCase()} icon={Crosshair} iconBg="bg-violet-500/15" iconColor="text-violet-500" />
                    </div>

                    {result.narrative && (
                        <div className={t.card}>
                            <h3 className={t.cardTitle}>Analisis Strategik</h3>
                            <p className={`${t.subtext} text-sm whitespace-pre-line`}>{result.narrative}</p>
                        </div>
                    )}

                    {result.seat_projections?.length > 0 && (
                        <div className={t.card}>
                            <h3 className={t.cardTitle}>Unjuran Kerusi Utama</h3>
                            <div className="overflow-x-auto">
                                <table className="min-w-full">
                                    <thead>
                                        <tr>
                                            <th className={t.tableHead}>Kerusi</th>
                                            <th className={t.tableHead}>Kebarangkalian PH</th>
                                            <th className={t.tableHead}>Kategori</th>
                                            <th className={t.tableHead}>Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {result.seat_projections.map((seat) => (
                                            <tr key={seat.kerusi} className={t.tableRow}>
                                                <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{seat.kerusi}</td>
                                                <td className={t.tableCell}>
                                                    <div className="flex items-center gap-2">
                                                        <div className="h-2 w-24 rounded-full overflow-hidden" style={{ backgroundColor: t.chartGrid }}>
                                                            <div
                                                                className="h-full rounded-full"
                                                                style={{
                                                                    width: `${seat.ph_probability}%`,
                                                                    backgroundColor: seat.ph_probability > 50 ? CHART_COLORS.putih : CHART_COLORS.hitam,
                                                                }}
                                                            />
                                                        </div>
                                                        <span className="font-semibold">{seat.ph_probability}%</span>
                                                    </div>
                                                </td>
                                                <td className={t.tableCell}>{seat.kategori}</td>
                                                <td className={`${t.tableCell} max-w-md`}>{seat.catatan}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

function WhatIfTab({ filters, sliders, setSliders }) {
    const { t } = usePilihanrayaTheme();
    const [baseline, setBaseline] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [reloadNonce, setReloadNonce] = useState(0);
    const filterKey = JSON.stringify(cleanParams(filters));
    const loadedKeyRef = useRef(null);

    useEffect(() => {
        if (loadedKeyRef.current === filterKey) return;
        let cancelled = false;
        setLoading(true);
        setError(null);
        axios.get(route('pilihanraya.api.baseline'), { params: cleanParams(filters) })
            .then((res) => {
                if (!cancelled) {
                    setBaseline(res.data);
                    loadedKeyRef.current = filterKey;
                }
            })
            .catch(() => {
                if (!cancelled) setError('Gagal memuatkan data asas simulasi.');
            })
            .finally(() => !cancelled && setLoading(false));

        return () => {
            cancelled = true;
        };
    }, [filterKey, reloadNonce]);

    const projection = useMemo(
        () => (baseline ? projectAll(baseline, sliders) : null),
        [baseline, sliders]
    );

    if (error && !projection) {
        return (
            <div className={`${t.card} flex flex-col items-center justify-center gap-4 py-16`}>
                <p className={`${t.subtext} text-sm`}>{error}</p>
                <button type="button" onClick={() => setReloadNonce((n) => n + 1)} className={t.buttonSecondary}>
                    Cuba Semula
                </button>
            </div>
        );
    }

    if (loading || !projection) {
        return (
            <div className={`${t.card} flex items-center justify-center py-20`}>
                <Loader2 className="h-8 w-8 animate-spin text-emerald-500" />
            </div>
        );
    }

    if (projection.total === 0) {
        return (
            <div className={t.card}>
                <p className={`${t.subtext} text-sm`}>Tiada kerusi dengan data culaan untuk penapis semasa — simulasi memerlukan rekod culaan.</p>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <SliderPanel sliders={sliders} onChange={setSliders} />
            <div className="xl:col-span-2 space-y-4">
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <KpiCard label="Kerusi Dimenangi" value={`${projection.wins}/${projection.total}`} icon={Scale} />
                    <KpiCard label="Jangkaan Kerusi" value={projection.expectedSeats} icon={BrainCircuit} iconBg="bg-blue-500/15" iconColor="text-blue-500" />
                    <KpiCard
                        label="Majoriti"
                        value={projection.majority > 0 ? `+${projection.majority}` : projection.majority}
                        icon={Crosshair}
                        iconBg={projection.majority > 0 ? 'bg-emerald-500/15' : 'bg-red-500/15'}
                        iconColor={projection.majority > 0 ? 'text-emerald-500' : 'text-red-500'}
                    />
                    <KpiCard label="Undi Popular PH" value={`${projection.overallPh}%`} sub={`Pembangkang ${projection.overallHitam}%`} icon={Megaphone} iconBg="bg-violet-500/15" iconColor="text-violet-500" />
                </div>

                <div className={t.card}>
                    <h3 className={t.cardTitle}>Unjuran Mengikut Kerusi (disusun mengikut saing)</h3>
                    <div className="overflow-x-auto max-h-[28rem] overflow-y-auto">
                        <table className="min-w-full">
                            <thead>
                                <tr>
                                    <th className={t.tableHead}>KADUN</th>
                                    <th className={t.tableHead}>Asas P / K / H</th>
                                    <th className={t.tableHead}>Senario P / K / H</th>
                                    <th className={t.tableHead}>Margin</th>
                                    <th className={t.tableHead}>P(Menang)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {projection.seats.map((seat) => (
                                    <tr key={seat.name} className={t.tableRow}>
                                        <td className={`${t.tableCell} font-medium whitespace-nowrap`}>
                                            {seat.name}{seat.lowData ? ' *' : ''}
                                        </td>
                                        <td className={`${t.tableCell} whitespace-nowrap`}>
                                            {seat.baselineShares.putih}% / {seat.baselineShares.kelabu}% / {seat.baselineShares.hitam}%
                                        </td>
                                        <td className={`${t.tableCell} whitespace-nowrap font-medium`}>
                                            <span style={{ color: CHART_COLORS.putih }}>{seat.projectedShares.putih}%</span>{' / '}
                                            <span style={{ color: CHART_COLORS.kelabu }}>{seat.projectedShares.kelabu}%</span>{' / '}
                                            <span style={{ color: CHART_COLORS.hitam }}>{seat.projectedShares.hitam}%</span>
                                        </td>
                                        <td className={t.tableCell} style={{ color: seat.margin >= 0 ? CHART_COLORS.putih : CHART_COLORS.hitam }}>
                                            {seat.margin >= 0 ? '+' : ''}{seat.margin}
                                        </td>
                                        <td className={t.tableCell}>
                                            <span
                                                className={t.badge}
                                                style={{
                                                    backgroundColor: `${seat.pWin > 50 ? CHART_COLORS.putih : CHART_COLORS.hitam}26`,
                                                    color: seat.pWin > 50 ? CHART_COLORS.putih : CHART_COLORS.hitam,
                                                    border: `1px solid ${seat.pWin > 50 ? CHART_COLORS.putih : CHART_COLORS.hitam}66`,
                                                }}
                                            >
                                                {seat.pWin}%
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className={`${t.subtext} text-xs mt-3`}>
                        * data culaan nipis — kebarangkalian dikecilkan ke arah 50%. Model deterministik logistik;
                        gunakan tab Ramalan untuk analisis AI penuh.
                    </p>
                </div>
            </div>
        </div>
    );
}

function SumberTab({ filters }) {
    const { t } = usePilihanrayaTheme();
    const [data, setData] = useState(null);
    const [status, setStatus] = useState(null);
    const [loading, setLoading] = useState(false);
    const [runError, setRunError] = useState(null);

    const run = async () => {
        setLoading(true);
        setRunError(null);
        try {
            const res = await axios.post(route('pilihanraya.api.resources'), cleanParams(filters), { timeout: 130000 });
            setData(res.data.result);
            setStatus(res.data.status);
        } catch {
            setRunError('Cadangan gagal dijana — pelayan tidak memberi respons. Sila cuba semula.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className={`${t.subtext} text-sm`}>
                    Enjin peruntukan sumber — mengenal pasti kerusi ROI tertinggi untuk jentera, sukarelawan dan program.
                </p>
                <button type="button" onClick={run} disabled={loading} className={t.buttonPrimary}>
                    {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Megaphone className="h-4 w-4" />}
                    {loading ? 'Menganalisis…' : 'Jana Cadangan Peruntukan'}
                </button>
            </div>
            {runError && <div className={t.banner}>{runError}</div>}
            <FallbackBanner show={status === 'fallback'} />
            <ResourcePanel data={data} />
        </div>
    );
}

function TaklimatTab({ filters, lists }) {
    const { t } = usePilihanrayaTheme();
    const [level, setLevel] = useState('national');
    const [scopeId, setScopeId] = useState('');
    const [briefing, setBriefing] = useState(null);
    const [seatScores, setSeatScores] = useState([]);
    const [status, setStatus] = useState(null);
    const [loading, setLoading] = useState(false);
    const [runError, setRunError] = useState(null);

    const scopeOptions = {
        national: [],
        negeri: lists.negeriList,
        parlimen: lists.parlimenList,
        kadun: lists.kadunList,
    }[level];

    const run = async () => {
        setLoading(true);
        setRunError(null);
        try {
            const res = await axios.post(route('pilihanraya.api.briefing'), {
                level,
                scope_id: scopeId || null,
            }, { timeout: 130000 });
            setBriefing(res.data.result);
            setSeatScores(res.data.seatScores || []);
            setStatus(res.data.status);
        } catch {
            setRunError('Taklimat gagal dijana — pelayan tidak memberi respons. Sila cuba semula.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-4">
            <div className={t.cardTight}>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                    <div>
                        <label className={t.label}>Peringkat</label>
                        <select value={level} onChange={(e) => { setLevel(e.target.value); setScopeId(''); }} className={t.input}>
                            <option value="national">Nasional</option>
                            <option value="negeri">Negeri</option>
                            <option value="parlimen">Parlimen</option>
                            <option value="kadun">KADUN</option>
                        </select>
                    </div>
                    <div>
                        <label className={t.label}>Kawasan</label>
                        <select value={scopeId} onChange={(e) => setScopeId(e.target.value)} className={t.input} disabled={level === 'national'}>
                            <option value="">{level === 'national' ? 'Seluruh Negara' : 'Pilih kawasan…'}</option>
                            {scopeOptions.map((opt) => (
                                <option key={opt.id} value={opt.id}>{opt.nama}</option>
                            ))}
                        </select>
                    </div>
                    <button
                        type="button"
                        onClick={run}
                        disabled={loading || (level !== 'national' && !scopeId)}
                        className={`${t.buttonPrimary} justify-center`}
                    >
                        {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileText className="h-4 w-4" />}
                        {loading ? 'Menjana…' : 'Jana Taklimat Eksekutif'}
                    </button>
                </div>
            </div>
            {runError && <div className={t.banner}>{runError}</div>}
            <FallbackBanner show={status === 'fallback'} />
            <BriefingViewer briefing={briefing} seatScores={seatScores} />
        </div>
    );
}

function SimulasiContent({ filters, setFilters, latestForecast, lists }) {
    const [activeTab, setActiveTab] = useState('ramalan');
    const [sliders, setSliders] = useState({ ...DEFAULT_SLIDERS });

    return (
        <>
            <FilterBar filters={filters} onChange={setFilters} {...lists} showDates={false} />
            <div className="mb-6">
                <TabBar tabs={TABS} active={activeTab} onChange={setActiveTab} />
            </div>
            {activeTab === 'ramalan' && <RamalanTab filters={filters} latestForecast={latestForecast} />}
            {activeTab === 'whatif' && <WhatIfTab filters={filters} sliders={sliders} setSliders={setSliders} />}
            {activeTab === 'wargame' && <ScenarioChat filters={cleanParams(filters)} sliders={sliders} />}
            {activeTab === 'sumber' && <SumberTab filters={filters} />}
            {activeTab === 'taklimat' && <TaklimatTab filters={filters} lists={lists} />}
        </>
    );
}

export default function Simulasi({ latestForecast, negeriList, parlimenList, kadunList }) {
    const [filters, setFilters] = useState(EMPTY_FILTERS);

    return (
        <AuthenticatedLayout>
            <Head title="Pilihanraya — Pusat Simulasi" />
            <PilihanrayaShell
                title="Pusat Simulasi Pilihanraya"
                subtitle="Ramalan AI, simulasi what-if, war gaming dan taklimat eksekutif"
            >
                <SimulasiContent
                    filters={filters}
                    setFilters={setFilters}
                    latestForecast={latestForecast}
                    lists={{ negeriList, parlimenList, kadunList }}
                />
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
