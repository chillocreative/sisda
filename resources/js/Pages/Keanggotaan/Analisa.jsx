import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Users, MapPin, UserX, Crosshair } from 'lucide-react';
import KeanggotaanNav from './Nav';

const COLORS = { putih: '#10b981', hitam: '#0f172a', kelabu: '#94a3b8', belum_dicula: '#cbd5e1' };

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

function WingKpi({ label, value, grace, color }) {
    return (
        <div className="bg-white rounded-xl border border-slate-200 p-5">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-slate-500">{label}</span>
                <span className="h-3 w-3 rounded-full" style={{ backgroundColor: color }} />
            </div>
            <div className="text-3xl font-bold mt-2 text-slate-900">{value.toLocaleString()}</div>
            {grace > 0
                ? <p className="text-xs text-red-600 mt-1">{grace.toLocaleString()} melepasi 35 — sah sehingga tamat penggal</p>
                : <p className="text-xs text-slate-400 mt-1">≤ 35 tahun</p>}
        </div>
    );
}

const JANTINA_COLORS = { Lelaki: '#3b82f6', Perempuan: '#ec4899', 'Tidak Diketahui': '#cbd5e1' };

export default function Analisa({ summary, ageBands, byParlimen, byNegeri, byDun, byColor, byJantina, wings, parlimenList = [], filters = {} }) {
    const pct = (n) => (summary.total > 0 ? Math.round((n / summary.total) * 100) : 0);
    const kawasanPie = [
        { name: 'Dalam Kawasan', value: summary.dalam_kawasan, fill: '#10b981' },
        { name: 'Luar Kawasan', value: summary.luar_kawasan, fill: '#f59e0b' },
    ];
    const colorPie = byColor.map((c) => ({ name: c.voter_color, value: c.jumlah, fill: COLORS[c.voter_color] || '#cbd5e1' }));
    const jantinaData = [
        { name: 'Lelaki', value: byJantina?.lelaki || 0 },
        { name: 'Perempuan', value: byJantina?.perempuan || 0 },
        ...(byJantina?.tidak_diketahui ? [{ name: 'Tidak Diketahui', value: byJantina.tidak_diketahui }] : []),
    ].map((d) => ({ ...d, fill: JANTINA_COLORS[d.name] }));

    const setParlimen = (parlimen) => router.get(route('keanggotaan.analisa'), { parlimen }, { preserveState: true, replace: true });

    return (
        <AuthenticatedLayout>
            <Head title="Keanggotaan — Analisa" />
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-slate-900">Analisa Keanggotaan</h1>
                    <div>
                        <select value={filters.parlimen || ''} onChange={(e) => setParlimen(e.target.value)} className="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">Semua Parlimen / Cabang</option>
                            {parlimenList.map((p) => <option key={p} value={p}>{p}</option>)}
                        </select>
                    </div>
                </div>
                <KeanggotaanNav />

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Kpi label="Jumlah Ahli" value={summary.total.toLocaleString()} icon={Users} />
                    <Kpi label="Dalam Kawasan" value={summary.dalam_kawasan.toLocaleString()} sub={`${pct(summary.dalam_kawasan)}% daripada ahli`} icon={MapPin} color="text-emerald-600" />
                    <Kpi label="Luar Kawasan" value={summary.luar_kawasan.toLocaleString()} sub={`${pct(summary.luar_kawasan)}% — tiada dalam DPT/DPPR`} icon={UserX} color="text-amber-600" />
                    <Kpi label="Dicula (Hitam)" value={summary.dicula.toLocaleString()} sub={`${pct(summary.dicula)}% disokong pembangkang`} icon={Crosshair} color="text-red-600" />
                </div>

                <Card title="Jantina Ahli">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                        <ResponsiveContainer width="100%" height={240}>
                            <PieChart>
                                <Pie data={jantinaData} cx="50%" cy="50%" innerRadius={55} outerRadius={95} paddingAngle={2} dataKey="value" nameKey="name">
                                    {jantinaData.map((e) => <Cell key={e.name} fill={e.fill} />)}
                                </Pie>
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Legend wrapperStyle={{ fontSize: '12px' }} />
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="space-y-3">
                            {jantinaData.map((j) => (
                                <div key={j.name} className="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <span className="flex items-center gap-2 text-sm text-slate-600">
                                        <span className="h-3 w-3 rounded-full" style={{ backgroundColor: j.fill }} />{j.name}
                                    </span>
                                    <span className="text-lg font-bold text-slate-900">{j.value.toLocaleString()}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </Card>

                {wings && (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 className="text-lg font-semibold text-slate-900">Sayap Parti (≤ 35 tahun)</h2>
                            <span className="text-xs text-slate-500">
                                {wings.term.tahun_mula && wings.term.tahun_tamat
                                    ? `Penggal Pemilihan Parti: ${wings.term.tahun_mula}–${wings.term.tahun_tamat}${wings.within_term ? '' : ' (tamat)'}`
                                    : 'Penggal Pemilihan Parti belum ditetapkan — tetapkan di tab Tetapan.'}
                            </span>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <WingKpi label="AMK" value={wings.totals.AMK} grace={wings.grace.AMK} color={WING_COLORS.AMK} />
                            <WingKpi label="Srikandi" value={wings.totals.Srikandi} grace={wings.grace.Srikandi} color={WING_COLORS.Srikandi} />
                            <WingKpi label="Wanita" value={wings.totals.Wanita} grace={wings.grace.Wanita} color={WING_COLORS.Wanita} />
                        </div>
                        <Card title="Sayap Mengikut Cabang">
                            {wings.byCabang.length === 0 ? <p className="text-sm text-slate-500 py-12 text-center">Tiada ahli sayap.</p> : (
                                <ResponsiveContainer width="100%" height={Math.max(260, wings.byCabang.length * 46)}>
                                    <BarChart data={wings.byCabang} layout="vertical" margin={{ left: 40 }}>
                                        <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                        <XAxis type="number" style={{ fontSize: '11px' }} />
                                        <YAxis type="category" dataKey="nama" width={140} style={{ fontSize: '11px' }} />
                                        <Tooltip formatter={(v) => v.toLocaleString()} />
                                        <Legend wrapperStyle={{ fontSize: '12px' }} />
                                        <Bar dataKey="AMK" name="AMK" fill={WING_COLORS.AMK} radius={[0, 4, 4, 0]} />
                                        <Bar dataKey="Srikandi" name="Srikandi" fill={WING_COLORS.Srikandi} radius={[0, 4, 4, 0]} />
                                        <Bar dataKey="Wanita" name="Wanita" fill={WING_COLORS.Wanita} radius={[0, 4, 4, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </Card>
                    </div>
                )}

                <Card title="Ahli & Culaan Mengikut Parlimen / Cabang">
                    {byParlimen.length === 0 ? <p className="text-sm text-slate-500 py-12 text-center">Tiada data padanan parlimen.</p> : (
                        <ResponsiveContainer width="100%" height={Math.max(240, byParlimen.length * 38)}>
                            <BarChart data={byParlimen} layout="vertical" margin={{ left: 40 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" style={{ fontSize: '11px' }} />
                                <YAxis type="category" dataKey="nama" width={140} style={{ fontSize: '11px' }} />
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Legend wrapperStyle={{ fontSize: '12px' }} />
                                <Bar dataKey="jumlah" name="Jumlah Ahli" fill="#3b82f6" radius={[0, 6, 6, 0]} />
                                <Bar dataKey="dicula" name="Dicula" fill="#ef4444" radius={[0, 6, 6, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </Card>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <Card title="Taburan Umur Ahli">
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={ageBands}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                <XAxis dataKey="band" style={{ fontSize: '11px' }} />
                                <YAxis style={{ fontSize: '11px' }} />
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Bar dataKey="jumlah" name="Ahli" fill="#3b82f6" radius={[8, 8, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </Card>
                    <Card title="Status Kawasan">
                        <ResponsiveContainer width="100%" height={300}>
                            <PieChart>
                                <Pie data={kawasanPie} cx="50%" cy="50%" innerRadius={60} outerRadius={100} paddingAngle={2} dataKey="value" nameKey="name">
                                    {kawasanPie.map((e) => <Cell key={e.name} fill={e.fill} />)}
                                </Pie>
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Legend wrapperStyle={{ fontSize: '12px' }} />
                            </PieChart>
                        </ResponsiveContainer>
                    </Card>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <Card title="Ahli Mengikut Negeri">
                        {byNegeri.length === 0 ? <p className="text-sm text-slate-500 py-12 text-center">Tiada data padanan.</p> : (
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
                        <ResponsiveContainer width="100%" height={300}>
                            <PieChart>
                                <Pie data={colorPie} cx="50%" cy="50%" outerRadius={100} dataKey="value" nameKey="name">
                                    {colorPie.map((e) => <Cell key={e.name} fill={e.fill} />)}
                                </Pie>
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Legend wrapperStyle={{ fontSize: '12px' }} />
                            </PieChart>
                        </ResponsiveContainer>
                    </Card>
                </div>

                <Card title="Ahli Mengikut DUN (30 teratas)">
                    {byDun.length === 0 ? <p className="text-sm text-slate-500 py-12 text-center">Tiada data padanan DUN.</p> : (
                        <ResponsiveContainer width="100%" height={Math.max(260, byDun.length * 28)}>
                            <BarChart data={byDun} layout="vertical" margin={{ left: 60 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" style={{ fontSize: '11px' }} />
                                <YAxis type="category" dataKey="nama" width={160} style={{ fontSize: '10px' }} />
                                <Tooltip formatter={(v) => v.toLocaleString()} />
                                <Bar dataKey="jumlah" name="Ahli" fill="#10b981" radius={[0, 6, 6, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
