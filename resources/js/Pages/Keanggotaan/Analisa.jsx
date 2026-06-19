import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Users, MapPin, UserX, Crosshair } from 'lucide-react';
import KeanggotaanNav from './Nav';

const COLORS = { putih: '#10b981', hitam: '#ef4444', kelabu: '#94a3b8', belum_dicula: '#cbd5e1' };

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

export default function Analisa({ summary, ageBands, byNegeri, byDun, byColor }) {
    const pct = (n) => (summary.total > 0 ? Math.round((n / summary.total) * 100) : 0);
    const kawasanPie = [
        { name: 'Dalam Kawasan', value: summary.dalam_kawasan, fill: '#10b981' },
        { name: 'Luar Kawasan', value: summary.luar_kawasan, fill: '#f59e0b' },
    ];
    const colorPie = byColor.map((c) => ({ name: c.voter_color, value: c.jumlah, fill: COLORS[c.voter_color] || '#cbd5e1' }));

    return (
        <AuthenticatedLayout>
            <Head title="Keanggotaan — Analisa" />
            <div className="max-w-6xl mx-auto space-y-6">
                <h1 className="text-2xl font-bold text-slate-900">Analisa Keanggotaan</h1>
                <KeanggotaanNav />

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Kpi label="Jumlah Ahli" value={summary.total.toLocaleString()} icon={Users} />
                    <Kpi label="Dalam Kawasan" value={summary.dalam_kawasan.toLocaleString()} sub={`${pct(summary.dalam_kawasan)}% daripada ahli`} icon={MapPin} color="text-emerald-600" />
                    <Kpi label="Luar Kawasan" value={summary.luar_kawasan.toLocaleString()} sub={`${pct(summary.luar_kawasan)}% — tiada dalam DPT aktif`} icon={UserX} color="text-amber-600" />
                    <Kpi label="Dicula (Hitam)" value={summary.dicula.toLocaleString()} sub={`${pct(summary.dicula)}% disokong pembangkang`} icon={Crosshair} color="text-red-600" />
                </div>

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
