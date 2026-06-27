import { useEffect, useReducer, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Bar, BarChart, CartesianGrid, Cell, LabelList, Legend, Pie, PieChart,
    ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import {
    Activity, AlertTriangle, Crosshair, Landmark, LayoutDashboard,
    Loader2, Map, PieChart as PieChartIcon, RefreshCw, Scale, Users, UserRound, Vote,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EMPTY_FILTERS, cleanParams } from './filters';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import TabBar from './components/TabBar';
import FilterBar from './components/FilterBar';
import KpiCard from './components/KpiCard';
import SentimentDonut from './components/SentimentDonut';
import TrendChart from './components/TrendChart';
import PopulationPyramid from './components/PopulationPyramid';
import HeatTable from './components/HeatTable';
import SeatHealthGrid from './components/SeatHealthGrid';
import BattlefieldTable from './components/BattlefieldTable';
import AlertList from './components/AlertList';
import { CHART_COLORS } from './theme';

const TABS = [
    { key: 'gambaran', label: 'Gambaran', icon: LayoutDashboard, route: 'pilihanraya.api.overview' },
    { key: 'komposisi', label: 'Komposisi', icon: PieChartIcon, route: 'pilihanraya.api.composition' },
    { key: 'sentimen', label: 'Sentimen', icon: Activity, route: 'pilihanraya.api.sentiment' },
    { key: 'skor', label: 'Skor Kerusi', icon: Scale, route: 'pilihanraya.api.seat-scores' },
    { key: 'medan', label: 'Medan Tempur', icon: Crosshair, route: 'pilihanraya.api.battlefield' },
    { key: 'amaran', label: 'Amaran Awal', icon: AlertTriangle, route: 'pilihanraya.api.alerts' },
];

/**
 * Lazy per-tab data layer: each tab fetches on first activation and is
 * memoised until the filters change. Switching to an already-cached
 * tab clears the loading flag (an in-flight fetch for another tab must
 * not strand the spinner), and responses from a superseded filter set
 * are discarded instead of polluting the fresh cache.
 */
function useTabData(activeTab, filters, seed) {
    const cacheRef = useRef({ gambaran: seed });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [reloadNonce, setReloadNonce] = useState(0);
    const [, force] = useReducer((x) => x + 1, 0);
    const filterKey = JSON.stringify(filters);
    const currentKeyRef = useRef(filterKey);
    const pendingRef = useRef(null);

    useEffect(() => {
        if (currentKeyRef.current !== filterKey) {
            currentKeyRef.current = filterKey;
            cacheRef.current = {};
            force();
        }
    }, [filterKey]);

    useEffect(() => {
        setError(null);
        if (cacheRef.current[activeTab] !== undefined) {
            pendingRef.current = null;
            setLoading(false);
            return;
        }
        const tabDef = TABS.find((tab) => tab.key === activeTab);
        if (!tabDef) return;

        const requestKey = `${activeTab}|${filterKey}`;
        pendingRef.current = requestKey;
        setLoading(true);
        axios.get(route(tabDef.route), { params: cleanParams(filters) })
            .then((res) => {
                if (filterKey === currentKeyRef.current) {
                    cacheRef.current[activeTab] = res.data;
                    force();
                }
            })
            .catch(() => {
                if (pendingRef.current === requestKey) {
                    setError('Gagal memuatkan data. Sila cuba semula.');
                }
            })
            .finally(() => {
                if (pendingRef.current === requestKey) {
                    pendingRef.current = null;
                    setLoading(false);
                }
            });
    }, [activeTab, filterKey, reloadNonce]);

    const retry = () => setReloadNonce((n) => n + 1);

    return { data: cacheRef.current[activeTab], loading, error, retry };
}

function Spinner() {
    const { t } = usePilihanrayaTheme();

    return (
        <div className={`${t.card} flex items-center justify-center py-20`}>
            <Loader2 className="h-8 w-8 animate-spin text-emerald-500" />
        </div>
    );
}

function LoadError({ message, onRetry }) {
    const { t } = usePilihanrayaTheme();

    return (
        <div className={`${t.card} flex flex-col items-center justify-center gap-4 py-16`}>
            <p className={`${t.subtext} text-sm`}>{message}</p>
            <button type="button" onClick={onRetry} className={t.buttonSecondary}>
                <RefreshCw className="h-4 w-4" /> Cuba Semula
            </button>
        </div>
    );
}

function GambaranTab({ data }) {
    const { t } = usePilihanrayaTheme();
    const growthTrend = data.growth_prior_30d > 0
        ? Math.round(((data.growth_recent_30d - data.growth_prior_30d) / data.growth_prior_30d) * 100)
        : null;

    return (
        <div className="space-y-6">
            {data.empty_roll && (
                <div className={t.banner}>
                    Tiada pangkalan data pengundi aktif — jumlah pengundi berdaftar dan liputan tidak dapat dikira.
                    Muat naik dan aktifkan batch di Upload Database.
                </div>
            )}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <KpiCard label="Jumlah Pengundi Berdaftar" value={data.roll_total.toLocaleString()} icon={Users} />
                <KpiCard
                    label="Telah Dicula"
                    value={data.canvassed.toLocaleString()}
                    sub={`Liputan ${data.coverage_pct}% daripada daftar pemilih`}
                    icon={Crosshair}
                    iconBg="bg-blue-500/15"
                    iconColor="text-blue-500"
                    trend={growthTrend}
                />
                <KpiCard
                    label="Pengundi Putih"
                    value={`${data.putih_pct}%`}
                    sub={`${data.putih.toLocaleString()} pengundi`}
                    icon={Activity}
                />
                <KpiCard
                    label="Pengundi Kelabu"
                    value={`${data.kelabu_pct}%`}
                    sub={`${data.kelabu.toLocaleString()} pengundi — sasaran pemujukan`}
                    icon={Scale}
                    iconBg="bg-slate-500/15"
                    iconColor="text-slate-400"
                />
                <KpiCard
                    label="Pengundi Hitam"
                    value={`${data.hitam_pct}%`}
                    sub={`${data.hitam.toLocaleString()} pengundi`}
                    icon={AlertTriangle}
                    iconBg="bg-red-500/15"
                    iconColor="text-red-500"
                />
                <KpiCard label="Kerusi Parlimen" value={data.seats.parlimen} icon={Landmark} iconBg="bg-violet-500/15" iconColor="text-violet-500" />
                <KpiCard label="KADUN" value={data.seats.kadun} icon={Vote} iconBg="bg-violet-500/15" iconColor="text-violet-500" />
                <KpiCard
                    label="Daerah Mengundi / Lokaliti"
                    value={`${data.seats.daerah_mengundi} / ${data.seats.lokaliti}`}
                    icon={Map}
                    iconBg="bg-amber-500/15"
                    iconColor="text-amber-500"
                />
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className={`${t.card} !bg-red-100`}>
                    <h3 className={t.cardTitle}>Pecahan Sentimen Semasa</h3>
                    <div className="grid grid-cols-3 gap-3 py-2">
                        {[
                            { label: 'Pengundi Putih', pct: data.putih_pct, count: data.putih },
                            { label: 'Pengundi Kelabu', pct: data.kelabu_pct, count: data.kelabu },
                            { label: 'Pengundi Hitam', pct: data.hitam_pct, count: data.hitam },
                        ].map((s) => (
                            <div key={s.label} className="flex flex-col items-center text-center">
                                {/* Light-red card; circle outlined in white with a white icon. */}
                                <div className="h-16 w-16 sm:h-20 sm:w-20 rounded-full flex items-center justify-center bg-red-200 border-2 border-white shadow-sm">
                                    <UserRound className="h-9 w-9 sm:h-10 sm:w-10 text-white" strokeWidth={2} />
                                </div>
                                <div className={`mt-3 text-xl font-bold ${t.text}`}>{s.pct}%</div>
                                <div className={`text-xs font-medium ${t.text}`}>{s.label}</div>
                                <div className={`text-xs ${t.subtext}`}>{(s.count ?? 0).toLocaleString()} pengundi</div>
                            </div>
                        ))}
                    </div>
                </div>
                <div className={`${t.card} lg:col-span-2`}>
                    <h3 className={t.cardTitle}>Status Operasi Culaan</h3>
                    <dl className="grid grid-cols-2 gap-4">
                        <div>
                            <dt className={t.kpiLabel}>Rekod 30 hari terkini</dt>
                            <dd className={t.kpiValue}>{data.growth_recent_30d.toLocaleString()}</dd>
                        </div>
                        <div>
                            <dt className={t.kpiLabel}>30 hari sebelumnya</dt>
                            <dd className={t.kpiValue}>{data.growth_prior_30d.toLocaleString()}</dd>
                        </div>
                        <div>
                            <dt className={t.kpiLabel}>Pengundi berdaftar yang telah dicula</dt>
                            <dd className={t.kpiValue}>{data.covered.toLocaleString()}</dd>
                        </div>
                        <div>
                            <dt className={t.kpiLabel}>Culaan terakhir</dt>
                            <dd className={`text-lg font-semibold ${t.text} mt-2`}>
                                {/* MySQL datetimes need the T separator — Safari rejects the space form */}
                                {data.last_canvass_at ? new Date(String(data.last_canvass_at).replace(' ', 'T')).toLocaleDateString('ms-MY') : '-'}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    );
}

const RACE_COLORS = ['#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#ec4899', '#14b8a6', '#ef4444', '#6366f1'];
const UMUR_COLORS = ['#f97316', '#eab308', '#22c55e', '#06b6d4', '#8b5cf6', '#ec4899', '#ef4444', '#0ea5e9'];

function KomposisiTab({ data }) {
    const { t } = usePilihanrayaTheme();

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div className={t.card}>
                    <h3 className={t.cardTitle}>Taburan Umur (Daftar Pemilih)</h3>
                    {data.ageBands.length === 0 ? (
                        <p className={`${t.subtext} text-sm py-12 text-center`}>Tiada pangkalan data pengundi aktif.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={data.ageBands} margin={{ top: 24, left: 10 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke={t.chartGrid} vertical={false} />
                                <XAxis dataKey="band" stroke={t.chartTick} style={{ fontSize: '11px' }} />
                                <YAxis stroke={t.chartTick} style={{ fontSize: '11px' }} width={65} tickFormatter={(v) => v.toLocaleString()} />
                                <Tooltip contentStyle={t.tooltip} formatter={(v) => v.toLocaleString()} />
                                <Bar dataKey="jumlah" name="Pengundi" radius={[8, 8, 0, 0]}>
                                    {data.ageBands.map((_, i) => <Cell key={i} fill={UMUR_COLORS[i % UMUR_COLORS.length]} />)}
                                    <LabelList dataKey="jumlah" position="top" style={{ fontSize: '10px', fill: t.chartTick }} formatter={(v) => v.toLocaleString()} />
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>
                <div className={t.card}>
                    <h3 className={t.cardTitle}>Komposisi Bangsa (Daftar Pemilih)</h3>
                    <ResponsiveContainer width="100%" height={300}>
                        <PieChart>
                            <Pie data={data.race} cx="50%" cy="50%" innerRadius={60} outerRadius={100} paddingAngle={2} dataKey="jumlah" nameKey="bangsa">
                                {data.race.map((entry, i) => (
                                    <Cell key={entry.bangsa} fill={RACE_COLORS[i % RACE_COLORS.length]} />
                                ))}
                            </Pie>
                            <Tooltip contentStyle={t.tooltip} formatter={(v) => v.toLocaleString()} />
                            <Legend wrapperStyle={{ fontSize: '12px' }} />
                        </PieChart>
                    </ResponsiveContainer>
                </div>
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <PopulationPyramid data={data.genderPyramid} />
                <div className={t.card}>
                    <h3 className={t.cardTitle}>Sentimen Mengikut Generasi (Culaan)</h3>
                    <ResponsiveContainer width="100%" height={320}>
                        <BarChart data={data.canvassAgeColor}>
                            <CartesianGrid strokeDasharray="3 3" stroke={t.chartGrid} vertical={false} />
                            <XAxis dataKey="band" stroke={t.chartTick} style={{ fontSize: '11px' }} />
                            <YAxis stroke={t.chartTick} style={{ fontSize: '11px' }} />
                            <Tooltip contentStyle={t.tooltip} />
                            <Legend wrapperStyle={{ fontSize: '12px' }} />
                            <Bar dataKey="putih" name="Putih" stackId="a" fill={CHART_COLORS.putih} />
                            <Bar dataKey="kelabu" name="Kelabu" stackId="a" fill={CHART_COLORS.kelabu} />
                            <Bar dataKey="hitam" name="Hitam" stackId="a" fill={CHART_COLORS.hitam} radius={[8, 8, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </div>
        </div>
    );
}

function SentimenTab({ data }) {
    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <SentimentDonut data={data.donut} />
                <div className="lg:col-span-2">
                    <TrendChart data={data.weeklyTrend} />
                </div>
            </div>
            <HeatTable rows={data.kadunHeatRows} title="Ranking & Peta Haba KADUN" />
        </div>
    );
}

function WarRoomContent({ filters, setFilters, seedOverview, lists }) {
    const [activeTab, setActiveTab] = useState('gambaran');
    const { data, loading, error, retry } = useTabData(activeTab, filters, seedOverview);

    return (
        <>
            <FilterBar filters={filters} onChange={setFilters} {...lists} />
            <div className="mb-6">
                <TabBar tabs={TABS} active={activeTab} onChange={setActiveTab} />
            </div>
            {error && data === undefined ? (
                <LoadError message={error} onRetry={retry} />
            ) : loading || data === undefined ? (
                <Spinner />
            ) : (
                <>
                    {activeTab === 'gambaran' && <GambaranTab data={data} />}
                    {activeTab === 'komposisi' && <KomposisiTab data={data} />}
                    {activeTab === 'sentimen' && <SentimenTab data={data} />}
                    {activeTab === 'skor' && <SeatHealthGrid parlimenRows={data.parlimen} kadunRows={data.kadun} />}
                    {activeTab === 'medan' && <BattlefieldTable data={data} />}
                    {activeTab === 'amaran' && <AlertList alerts={data} />}
                </>
            )}
        </>
    );
}

export default function WarRoom({ overview, negeriList, parlimenList, kadunList }) {
    const [filters, setFilters] = useState(EMPTY_FILTERS);

    return (
        <AuthenticatedLayout>
            <Head title="Pilihanraya — War Room" />
            <PilihanrayaShell
                title="Digital War Room"
                subtitle="Pusat perisikan pilihanraya — sentimen, skor kerusi dan amaran awal daripada data culaan SISDA"
            >
                <WarRoomContent
                    filters={filters}
                    setFilters={setFilters}
                    seedOverview={overview}
                    lists={{ negeriList, parlimenList, kadunList }}
                />
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
