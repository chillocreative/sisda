import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Zap, Coins, ArrowDownToLine, ArrowUpFromLine } from 'lucide-react';

const fmtInt = (n) => Number(n || 0).toLocaleString('ms-MY');
const fmtUsd = (n) => `$${Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 })}`;

function Kpi({ label, value, sub, icon: Icon, color = 'text-slate-900' }) {
    return (
        <div className="bg-white rounded-xl border border-slate-200 p-5">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-slate-500">{label}</span>
                <Icon className="h-5 w-5 text-slate-400" />
            </div>
            <div className={`text-2xl font-bold mt-2 ${color}`}>{value}</div>
            {sub && <p className="text-xs text-slate-500 mt-1">{sub}</p>}
        </div>
    );
}

export default function AiUsage({ summary, byModel, logs }) {
    return (
        <AuthenticatedLayout>
            <Head title="Log Aktiviti AI" />
            <div className="max-w-6xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 flex items-center gap-2">
                        <Zap className="h-6 w-6" /> Log Aktiviti AI
                    </h1>
                    <p className="text-sm text-slate-600 mt-1">Penggunaan token dan anggaran kos panggilan API Claude AI. Kos dianggar dalam USD mengikut harga model semasa.</p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Kpi label="Jumlah Panggilan AI" value={fmtInt(summary.calls)} icon={Zap} />
                    <Kpi label="Token Input" value={fmtInt(summary.input_tokens)} icon={ArrowDownToLine} />
                    <Kpi label="Token Output" value={fmtInt(summary.output_tokens)} icon={ArrowUpFromLine} />
                    <Kpi label="Anggaran Kos (USD)" value={fmtUsd(summary.cost_usd)} sub={`${fmtInt(summary.total_tokens)} jumlah token`} icon={Coins} color="text-emerald-600" />
                </div>

                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4">Mengikut Model</h2>
                    {byModel.length === 0 ? (
                        <p className="text-sm text-slate-500 py-6 text-center">Tiada penggunaan AI direkodkan lagi.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 text-left text-slate-600">
                                        <th className="py-2 px-3 font-medium">Model</th>
                                        <th className="py-2 px-3 font-medium text-right">Panggilan</th>
                                        <th className="py-2 px-3 font-medium text-right">Token Input</th>
                                        <th className="py-2 px-3 font-medium text-right">Token Output</th>
                                        <th className="py-2 px-3 font-medium text-right">Kos (USD)</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {byModel.map((m) => (
                                        <tr key={m.model} className="hover:bg-slate-50">
                                            <td className="py-2 px-3 font-medium text-slate-900">{m.model}</td>
                                            <td className="py-2 px-3 text-right text-slate-600">{fmtInt(m.calls)}</td>
                                            <td className="py-2 px-3 text-right text-slate-600">{fmtInt(m.input_tokens)}</td>
                                            <td className="py-2 px-3 text-right text-slate-600">{fmtInt(m.output_tokens)}</td>
                                            <td className="py-2 px-3 text-right font-medium text-emerald-600">{fmtUsd(m.cost_usd)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4">Panggilan Terkini</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-slate-600">
                                    <th className="py-2 px-3 font-medium">Tarikh</th>
                                    <th className="py-2 px-3 font-medium">Model</th>
                                    <th className="py-2 px-3 font-medium">Konteks</th>
                                    <th className="py-2 px-3 font-medium">Pengguna</th>
                                    <th className="py-2 px-3 font-medium text-right">Input</th>
                                    <th className="py-2 px-3 font-medium text-right">Output</th>
                                    <th className="py-2 px-3 font-medium text-right">Kos (USD)</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {logs.data.length === 0 && (
                                    <tr><td colSpan={7} className="py-8 text-center text-slate-500">Tiada rekod.</td></tr>
                                )}
                                {logs.data.map((l) => (
                                    <tr key={l.id} className="hover:bg-slate-50">
                                        <td className="py-2 px-3 text-slate-600 whitespace-nowrap">
                                            {l.created_at ? new Date(l.created_at).toLocaleString('ms-MY', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'}
                                        </td>
                                        <td className="py-2 px-3 text-slate-700 whitespace-nowrap">{l.model}</td>
                                        <td className="py-2 px-3 text-slate-600">{l.context || '-'}</td>
                                        <td className="py-2 px-3 text-slate-600">{l.user || '-'}</td>
                                        <td className="py-2 px-3 text-right text-slate-600">{fmtInt(l.input_tokens)}</td>
                                        <td className="py-2 px-3 text-right text-slate-600">{fmtInt(l.output_tokens)}</td>
                                        <td className="py-2 px-3 text-right font-medium text-emerald-600">{fmtUsd(l.cost_usd)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {logs.last_page > 1 && (
                        <div className="flex items-center justify-between mt-4 pt-4 border-t border-slate-200">
                            <p className="text-sm text-slate-600">Halaman {logs.current_page} / {logs.last_page}</p>
                            <div className="flex gap-2">
                                {logs.prev_page_url && <a href={logs.prev_page_url} className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-100">Sebelum</a>}
                                {logs.next_page_url && <a href={logs.next_page_url} className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-100">Seterusnya</a>}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
