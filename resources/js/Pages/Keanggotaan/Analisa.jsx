import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Cell, LabelList, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Users, MapPin, UserX, Crosshair } from 'lucide-react';
import KeanggotaanNav from './Nav';

const COLORS = { putih: '#dc2626', hitam: '#0f172a', kelabu: '#94a3b8', belum_dicula: '#cbd5e1' };
const UMUR_COLORS = ['#f97316', '#eab308', '#22c55e', '#06b6d4', '#8b5cf6', '#ec4899', '#ef4444', '#0ea5e9'];
const RADIAN = Math.PI / 180;
function makePieLabel(total) {
    return ({ cx, cy, midAngle, outerRadius, value }) => {
        if (!value || total === 0) return null;
        const sin = Math.sin(-RADIAN * midAngle);
        const cos = Math.cos(-RADIAN * midAngle);
        const x = cx + (outerRadius + 48) * cos;
        const y = cy + (outerRadius + 48) * sin;
        return (
            <text x={x} y={y} textAnchor={cos >= 0 ? 'start' : 'end'} dominantBaseline="central" fontSize={11} fill="#475569">
                {((value / total) * 100).toFixed(1)}%
            </text>
        );
    };
}

function Kpi({ label, value, sub, icon: Icon, color = 'text-slate-900' }) {
    return (
        <div className="bg-white rounded-xl border border-slate-200 p-5">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-slate-500">{label}</span>
                <Icon className="h-5 w-5 text-slate-400" />
            </div>
            <div className={`text-3xl font-bold mt-2 ${color}`}>{value}</div>
            {sub && <p className="text-xs text-slate-500 mt-1">{sub}</p>}
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="bg-white rounded-xl border border-slate-200 p-6">
            <h3 className="text-lg font-semibold text-slate-900 mb-4">{title}</h3>
            {children}
        </div>
    );
}

const WING_COLORS = { AMK: '#2563eb', Srikandi: '#db2777', Wanita: '#9333ea' };

function WingKpi({ label, value, grace, note, color }) {
    return (
        <div className="bg-white rounded-xl border border-slate-200 p-5">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-slate-500">{label}</span>
                <span className="h-3 w-3 rounded-full" style={{ backgroundColor: color }} />
            </div>
            <div className="text-3xl font-bold mt-2 text-slate-900">{value.toLocaleString()}</div>
            {grace > 0
                ? <p className="text-xs text-red-600 mt-1">{grace.toLocaleString()} melepasi 35 — sah sehingga tamat penggal</p>
                : <p className="text-xs text-slate-400 mt-1">{note}</p>}
        </div>
    );
}

const JANTINA_COLORS = { Lelaki: '#3b82f6', Perempuan: '#ec4899', 'Tidak Diketahui': '#cbd5e1' };

// Restroom-style male / female silhouettes (currentColor-filled, scale by height).
function MaleIcon({ className, style }) {
    return (
        <svg viewBox="0 0 192 512" className={className} style={style} fill="currentColor" aria-label="Lelaki" role="img">
            <path d="M96 0c35.346 0 64 28.654 64 64s-28.654 64-64 64-64-28.654-64-64S60.654 0 96 0m48 144h-11.36c-22.711 10.443-49.59 10.894-73.28 0H48c-26.51 0-48 21.49-48 48v136c0 13.255 10.745 24 24 24h16v152c0 13.255 10.745 24 24 24h64c13.255 0 24-10.745 24-24V352h16c13.255 0 24-10.745 24-24V192c0-26.51-21.49-48-48-48z" />
        </svg>
    );
}
function FemaleIcon({ className, style }) {
    return (
        <svg viewBox="0 0 256 512" className={className} style={style} fill="currentColor" aria-label="Perempuan" role="img">
            <path d="M128 0c35.346 0 64 28.654 64 64s-28.654 64-64 64-64-28.654-64-64S92.654 0 128 0m119.283 354.179l-48-192A24 24 0 0 0 176 144h-11.36c-22.711 10.443-49.59 10.894-73.28 0H80a24 24 0 0 0-23.283 18.179l-48 192C4.935 369.305 16.383 384 32 384h56v104c0 13.255 10.745 24 24 24h32c13.255 0 24-10.745 24-24V384h56c15.591 0 27.071-14.671 23.283-29.821z" />
        </svg>
    );
}

// A wide, distinct palette so every bangsa gets its own colour (no repeats).
const BANGSA_COLORS = [
    '#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#ec4899', '#14b8a6', '#ef4444', '#6366f1',
    '#84cc16', '#f97316', '#06b6d4', '#a855f7', '#eab308', '#22c55e', '#64748b', '#d946ef',
];

export default function Analisa({ summary, ageBands, byParlimen, byNegeri, byBangsa = [], byDun, byColor, byJantina, wings, parlimenList = [], dunList = [], filters = {} }) {
    const pct = (n) => (summary.total > 0 ? Math.round((n / summary.total) * 100) : 0);
    // Kawasan cards are scoped to the whole Parlimen/Cabang, so their % is
    // relative to the Cabang member total, not the (DUN-filtered) Jumlah Ahli.
    const pctK = (n) => (summary.kawasan_total > 0 ? Math.round((n / summary.kawasan_total) * 100) : 0);
    const kawasanPie = [
        { name: 'Dalam Kawasan', value: summary.dalam_kawasan, fill: '#10b981' },
        { name: 'Tiada DPPR/DPT', value: summary.tiada_dppr || 0, fill: '#ef4444' },
        { name: 'Luar Kawasan', value: summary.luar_kawasan, fill: '#f59e0b' },
    ];
    const kawasanTotal = kawasanPie.reduce((s, d) => s + d.value, 0);
    const colorPie = byColor.map((c) => ({ name: c.voter_color, value: c.jumlah, fill: COLORS[c.voter_color] || '#cbd5e1' }));
    const jantinaData = [
        { name: 'Lelaki', value: byJantina?.lelaki || 0 },
        { name: 'Perempuan', value: byJantina?.perempuan || 0 },
        ...(byJantina?.tidak_diketahui ? [{ name: 'Tidak Diketahui', value: byJantina.tidak_diketahui }] : []),
    ].map((d) => ({ ...d, fill: JANTINA_COLORS[d.name] }));

    const bangsaData = byBangsa.map((b, i) => ({ name: b.nama, value: b.jumlah, fill: BANGSA_COLORS[i % BANGSA_COLORS.length] }));

    const apply = (params) => router.get(route('keanggotaan.analisa'), params, { preserveState: true, replace: true });

    return (
        <AuthenticatedLayout>
            <Head title="Keanggotaan — Analisa" />
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-slate-900">Analisa Keanggotaan</h1>
                    <div className="flex flex-wrap items-center gap-2">
                        <select value={filters.parlimen || ''} onChange={(e) => apply({ parlimen: e.target.value })} className="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua Parlimen / Cabang</option>
                            {parlimenList.map((p) => <option key={p} value={p}>{p}</option>)}
                        </select>
                        <select
                            value={filters.dun || ''}
                            onChange={(e) => apply({ parlimen: filters.parlimen, dun: e.target.value })}
                            disabled={!filters.parlimen}
                            className="px-3 py-2 border border-slate-300 rounded-lg text-sm disabled:bg-slate-100 disabled:text-slate-400"
                        >
                            <option value="">{filters.parlimen ? 'Semua DUN' : 'Pilih Parlimen dahulu'}</option>
                            {dunList.map((d) => <option key={d} value={d}>{d}</option>)}
                        </select>
                    </div>
                </div>
                <KeanggotaanNav />

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Kpi label="Jumlah Ahli" value={summary.total.toLocaleString()} icon={Users} />
                    <Kpi label="Dalam Kawasan" value={summary.dalam_kawasan.toLocaleString()} sub={`${pctK(summary.dalam_kawasan)}% daripada cabang`} icon={MapPin} color="text-emerald-600" />
                    <Kpi label="Tiada DPPR/DPT" value={(summary.tiada_dppr || 0).toLocaleString()} sub={`${pctK(summary.tiada_dppr || 0)}% — tidak ditemui dalam senarai pengundi`} icon={UserX} color="text-red-600" />
                    <Kpi label="Dicula (Hitam)" value={summary.dicula.toLocaleString()} sub={`${pct(summary.dicula)}% disokong pembangkang`} icon={Crosshair} color="text-red-600" />
                </div>

                <Card title={filters.dun ? `Jantina Ahli — DUN ${filters.dun}` : 'Jantina Ahli'}>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                        <ResponsiveContainer width="100%" height={260}>
                            <PieChart>
                                <Pie data={jantinaData} cx="50%" cy="50%" outerRadius={95} dataKey="value" nameKey="name"
                                    label={makePieLabel(jantinaData.reduce((s, d) => s + d.value, 0))} labelLine>
                                    {jantinaData.map((e) => <Cell key={e.name} fill={e.fill} />)}
                                </Pie>
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Legend wrapperStyle={{ fontSize: '12px' }} />
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="space-y-5">
                            {jantinaData.map((j) => {
                                const Icon = j.name === 'Lelaki' ? MaleIcon : j.name === 'Perempuan' ? FemaleIcon : null;
                                return (
                                    <div key={j.name} className="flex items-center gap-4">
                                        {Icon
                                            ? <Icon className="h-12 w-auto shrink-0" style={{ color: j.fill }} />
                                            : <span className="h-4 w-4 rounded-full shrink-0" style={{ backgroundColor: j.fill }} />}
                                        <span className="text-2xl font-bold text-slate-900">{j.value.toLocaleString()}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </Card>

                <Card title="Keanggotaan Mengikut Bangsa">
                    {bangsaData.length === 0 ? <p className="text-sm text-slate-500 py-12 text-center">Tiada data bangsa.</p> : (
                        // Horizontal bars: a pie hid the tiny categories (1–3 members);
                        // every bangsa now gets a labelled, colour-coded row.
                        <ResponsiveContainer width="100%" height={Math.max(300, bangsaData.length * 40)}>
                            <BarChart data={bangsaData} layout="vertical" margin={{ left: 20, right: 56 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" style={{ fontSize: '11px' }} allowDecimals={false} />
                                <YAxis type="category" dataKey="name" width={130} style={{ fontSize: '11px' }} />
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Bar dataKey="value" name="Ahli" radius={[0, 6, 6, 0]}>
                                    {bangsaData.map((e) => <Cell key={e.name} fill={e.fill} />)}
                                    <LabelList dataKey="value" position="right" style={{ fontSize: '11px', fill: '#475569' }} formatter={(v) => v.toLocaleString()} />
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </Card>

                {wings && (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 className="text-lg font-semibold text-slate-900">Sayap Parti</h2>
                            <span className="text-xs text-slate-500">
                                {wings.term.tahun_mula && wings.term.tahun_tamat
                                    ? `Penggal Pemilihan Parti: ${wings.term.tahun_mula}–${wings.term.tahun_tamat}${wings.within_term ? '' : ' (tamat)'}`
                                    : 'Penggal Pemilihan Parti belum ditetapkan — tetapkan di tab Tetapan.'}
                            </span>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <WingKpi label="AMK" value={wings.totals.AMK} grace={wings.grace.AMK} note="Lelaki ≤ 35 tahun" color={WING_COLORS.AMK} />
                            <WingKpi label="Srikandi" value={wings.totals.Srikandi} grace={wings.grace.Srikandi} note="Perempuan ≤ 35 tahun" color={WING_COLORS.Srikandi} />
                            <WingKpi label="Wanita" value={wings.totals.Wanita} grace={wings.grace.Wanita} note="Semua perempuan" color={WING_COLORS.Wanita} />
                        </div>
                        <Card title="Sayap Mengikut Cabang">
                            {wings.byCabang.length === 0 ? <p className="text-sm text-slate-500 py-12 text-center">Tiada ahli sayap.</p> : (
                                <ResponsiveContainer width="100%" height={Math.max(260, wings.byCabang.length * 46)}>
                                    <BarChart data={wings.byCabang} layout="vertical" margin={{ left: 40, right: 56 }}>
                                        <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                        <XAxis type="number" style={{ fontSize: '11px' }} />
                                        <YAxis type="category" dataKey="nama" width={140} style={{ fontSize: '11px' }} />
                                        <Tooltip formatter={(v) => v.toLocaleString()} />
                                        <Legend wrapperStyle={{ fontSize: '12px' }} />
                                        <Bar dataKey="AMK" name="AMK" fill={WING_COLORS.AMK} radius={[0, 4, 4, 0]}>
                                            <LabelList dataKey="AMK" position="right" style={{ fontSize: '11px', fill: '#475569' }} formatter={(v) => v.toLocaleString()} />
                                        </Bar>
                                        <Bar dataKey="Srikandi" name="Srikandi" fill={WING_COLORS.Srikandi} radius={[0, 4, 4, 0]}>
                                            <LabelList dataKey="Srikandi" position="right" style={{ fontSize: '11px', fill: '#475569' }} formatter={(v) => v.toLocaleString()} />
                                        </Bar>
                                        <Bar dataKey="Wanita" name="Wanita" fill={WING_COLORS.Wanita} radius={[0, 4, 4, 0]}>
                                            <LabelList dataKey="Wanita" position="right" style={{ fontSize: '11px', fill: '#475569' }} formatter={(v) => v.toLocaleString()} />
                                        </Bar>
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </Card>
                    </div>
                )}

                <Card title="Ahli Mengikut Cabang">
                    {byParlimen.length === 0 ? <p className="text-sm text-slate-500 py-12 text-center">Tiada data cabang.</p> : (
                        <ResponsiveContainer width="100%" height={Math.max(240, byParlimen.length * 38)}>
                            <BarChart data={byParlimen} layout="vertical" margin={{ left: 40, right: 56 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" style={{ fontSize: '11px' }} />
                                <YAxis type="category" dataKey="nama" width={140} style={{ fontSize: '11px' }} />
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Legend wrapperStyle={{ fontSize: '12px' }} />
                                <Bar dataKey="jumlah" name="Jumlah Ahli" fill="#3b82f6" radius={[0, 6, 6, 0]}>
                                    <LabelList dataKey="jumlah" position="right" style={{ fontSize: '11px', fill: '#475569' }} formatter={(v) => v.toLocaleString()} />
                                </Bar>
                                <Bar dataKey="dicula" name="Dicula" fill="#ef4444" radius={[0, 6, 6, 0]}>
                                    <LabelList dataKey="dicula" position="right" style={{ fontSize: '11px', fill: '#475569' }} formatter={(v) => v.toLocaleString()} />
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </Card>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <Card title="Taburan Umur Ahli">
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={ageBands} margin={{ top: 24, left: 10 }}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                <XAxis dataKey="band" style={{ fontSize: '11px' }} />
                                <YAxis style={{ fontSize: '11px' }} width={60} tickFormatter={(v) => v.toLocaleString()} />
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Bar dataKey="jumlah" name="Ahli" radius={[8, 8, 0, 0]}>
                                    {ageBands.map((_, i) => <Cell key={i} fill={UMUR_COLORS[i % UMUR_COLORS.length]} />)}
                                    <LabelList dataKey="jumlah" position="top" style={{ fontSize: '11px', fill: '#475569' }} formatter={(v) => v.toLocaleString()} />
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </Card>
                    <Card title="Status Kawasan">
                        {kawasanTotal === 0 ? (
                            <p className="text-sm text-slate-500 py-24 text-center">Belum disync dengan DPT / DPPR.<br />Tekan "Sync Semula" di Senarai Ahli untuk padankan.</p>
                        ) : (
                            <ResponsiveContainer width="100%" height={320}>
                                <PieChart>
                                    <Pie data={kawasanPie} cx="50%" cy="50%" outerRadius={95} dataKey="value" nameKey="name"
                                        label={makePieLabel(kawasanTotal)} labelLine>
                                        {kawasanPie.map((e) => <Cell key={e.name} fill={e.fill} />)}
                                    </Pie>
                                    <Tooltip formatter={(v) => v.toLocaleString()} />
                                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                                </PieChart>
                            </ResponsiveContainer>
                        )}
                    </Card>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <Card title="Ahli Mengikut Negeri">
                        {byNegeri.length === 0 ? <p className="text-sm text-slate-500 py-12 text-center">Tiada data negeri.</p> : (
                            <ResponsiveContainer width="100%" height={Math.max(220, byNegeri.length * 32)}>
                                <BarChart data={byNegeri} layout="vertical" margin={{ left: 40 }}>
                                    <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                    <XAxis type="number" style={{ fontSize: '11px' }} />
                                    <YAxis type="category" dataKey="nama" width={120} style={{ fontSize: '11px' }} />
                                    <Tooltip formatter={(v) => v.toLocaleString()} />
                                    <Bar dataKey="jumlah" name="Ahli" fill="#8b5cf6" radius={[0, 6, 6, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </Card>
                    <Card title="Sentimen Ahli (Culaan)">
                        <ResponsiveContainer width="100%" height={360}>
                            <PieChart>
                                <Pie data={colorPie} cx="50%" cy="50%" outerRadius={95} dataKey="value" nameKey="name"
                                    label={makePieLabel(colorPie.reduce((s, d) => s + d.value, 0))} labelLine>
                                    {colorPie.map((e) => <Cell key={e.name} fill={e.fill} />)}
                                </Pie>
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Legend wrapperStyle={{ fontSize: '12px' }} />
                            </PieChart>
                        </ResponsiveContainer>
                    </Card>
                </div>

                <div className={`grid grid-cols-1 gap-4 ${filters.dun ? 'sm:grid-cols-2' : ''}`}>
                    <Kpi
                        label={filters.parlimen ? `Daftar Mengundi di Luar Parlimen ${filters.parlimen}` : 'Daftar Mengundi di Luar Parlimen Cabang'}
                        value={(summary.luar_parlimen || 0).toLocaleString()}
                        sub={`${pct(summary.luar_parlimen || 0)}% daripada ahli — berdaftar mengundi di luar cabang`}
                        icon={MapPin}
                        color="text-amber-600"
                    />
                    {filters.dun && (
                        <Kpi
                            label={`Daftar Mengundi di Luar DUN ${filters.dun}`}
                            value={(summary.luar_dun || 0).toLocaleString()}
                            sub={`${pct(summary.luar_dun || 0)}% daripada ahli — berdaftar mengundi di luar DUN ini`}
                            icon={MapPin}
                            color="text-amber-600"
                        />
                    )}
                </div>

                <Card title="Ahli Mengikut DUN">
                    {byDun.length === 0 ? <p className="text-sm text-slate-500 py-12 text-center">Tiada data padanan DUN.</p> : (
                        <ResponsiveContainer width="100%" height={Math.max(260, byDun.length * 32)}>
                            <BarChart data={byDun} layout="vertical" margin={{ left: 60, right: 60 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" style={{ fontSize: '11px' }} tickFormatter={(v) => v.toLocaleString()} />
                                <YAxis type="category" dataKey="nama" width={160} style={{ fontSize: '10px' }} />
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Bar dataKey="jumlah" name="Ahli" radius={[0, 6, 6, 0]}>
                                    {byDun.map((_, i) => <Cell key={i} fill={UMUR_COLORS[i % UMUR_COLORS.length]} />)}
                                    <LabelList dataKey="jumlah" position="right" style={{ fontSize: '11px', fill: '#475569' }} formatter={(v) => v.toLocaleString()} />
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
