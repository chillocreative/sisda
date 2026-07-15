import {
    AlertTriangle, ArrowRight, Download, ExternalLink, Sparkles, TrendingDown, TrendingUp,
} from 'lucide-react';
import { useMemo } from 'react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import { partyColor } from './shared';

const fmt = (n) => (n === null || n === undefined || Number.isNaN(Number(n)) ? '—'
    : Number(n).toLocaleString('en-MY'));
const signed = (n) => `${n >= 0 ? '+' : ''}${fmt(n)}`;

function KpiPill({ label, value, sub, color }) {
    const { t } = usePilihanrayaTheme();
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div className="text-xs uppercase tracking-wide text-slate-500">{label}</div>
            <div className="mt-1 text-2xl font-bold" style={{ color: color || '#0f172a' }}>{value}</div>
            {sub && <div className={`mt-0.5 text-xs ${t.subtext}`}>{sub}</div>}
        </div>
    );
}

function Section({ title, section }) {
    const { t } = usePilihanrayaTheme();
    if (!section || (!section.analisis && !(section.bullet_points || []).length)) return null;
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5">
            <h4 className={`${t.text} font-semibold mb-2`}>{title}</h4>
            {section.analisis && <p className="text-sm leading-relaxed text-slate-700">{section.analisis}</p>}
            {(section.bullet_points || []).length > 0 && (
                <ul className="mt-3 space-y-1.5">
                    {section.bullet_points.map((bp, i) => (
                        <li key={i} className="flex gap-2 text-sm text-slate-600">
                            <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" />
                            <span>{bp}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function ComparisonResult({ comparison }) {
    const { t } = usePilihanrayaTheme();
    const facts = comparison.fact_payload || {};
    const res = comparison.ai_result || {};
    const senario = facts.senario || [];
    const perubahan = facts.perubahan || [];
    const roll = facts.roll_semasa || {};
    const saluran = facts.saluran_semasa || {};
    const isFallback = comparison.ai_status === 'fallback';
    const isEstimate = saluran.sumber === 'dpt_estimate';

    // Party set = union across scenarios, in first-seen order (detected from
    // the uploaded sheets, so any line-up is supported).
    const allParties = useMemo(() => {
        const seen = [];
        senario.forEach((s) => (s.parti || Object.keys(s.undi || {})).forEach((p) => {
            if (!seen.includes(p)) seen.push(p);
        }));
        return seen;
    }, [senario]);

    const metricRow = (label, render) => (
        <tr className={t.tableRow}>
            <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{label}</td>
            {senario.map((s, i) => (
                <td key={i} className="px-3 py-2 text-sm text-right tabular-nums text-slate-700">{render(s)}</td>
            ))}
        </tr>
    );

    return (
        <div className="space-y-6">
            {/* Header + actions */}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Sparkles className="h-5 w-5 text-emerald-500" />
                    <h3 className="text-lg font-semibold text-slate-900">{res.tajuk || 'Hasil Analisis'}</h3>
                    {isFallback && (
                        <span className="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                            <AlertTriangle className="h-3 w-3" /> Deterministik
                        </span>
                    )}
                </div>
                <button
                    type="button"
                    onClick={() => window.open(route('pilihanraya.analisa.comparisons.pdf', comparison.id), '_blank')}
                    className={t.buttonPrimary}
                >
                    <Download className="h-4 w-4" /> Muat Turun PDF
                </button>
            </div>

            {/* Fact KPI strip */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <KpiPill label="Bilangan Senario" value={senario.length} />
                <KpiPill label="% Pengundi Baru" value={`${roll.pct_baru ?? 0}%`} sub={`${fmt(roll.baru || 0)} pengundi`} color="#8b5cf6" />
                <KpiPill label="% Pengundi Muda (18–29)" value={`${roll.pct_muda ?? 0}%`} sub={`${fmt(roll.muda_18_29 || 0)} pengundi`} color="#3b82f6" />
                <KpiPill label="Jumlah Saluran" value={fmt(saluran.jumlah_saluran || 0)} sub={saluran.tersedia ? `${fmt(saluran.jumlah_berdaftar || 0)} berdaftar` : 'Tiada data'} color="#0d9488" />
            </div>

            {/* Scenario comparison table (numbers from DB, never AI) */}
            {senario.length > 0 && (
                <div className={t.card}>
                    <h4 className={t.cardTitle}>Perbandingan Keputusan Mengikut Senario</h4>
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead>
                                <tr>
                                    <th className={t.tableHead}>Metrik</th>
                                    {senario.map((s, i) => (
                                        <th key={i} className={`${t.tableHead} text-right`}>
                                            {s.label}<span className="block text-[10px] font-normal text-slate-400">{s.tahun}</span>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {metricRow('Pemilih Berdaftar', (s) => fmt(s.pemilih_berdaftar))}
                                {metricRow('Undi Keluar', (s) => fmt(s.undi_keluar))}
                                {metricRow('% Keluar', (s) => (s.peratus_keluar !== null ? `${s.peratus_keluar}%` : '—'))}
                                {allParties.map((party, pi) => (
                                    <tr key={party} className={t.tableRow}>
                                        <td className={`${t.tableCell} font-medium`}>
                                            <span className="inline-flex items-center gap-1.5">
                                                <span className="h-2.5 w-2.5 rounded-sm" style={{ background: partyColor(party, pi) }} />
                                                {party}
                                            </span>
                                        </td>
                                        {senario.map((s, i) => (
                                            <td key={i} className="px-3 py-2 text-sm text-right tabular-nums font-semibold" style={{ color: partyColor(party, pi) }}>
                                                {s.undi && s.undi[party] !== undefined
                                                    ? `${fmt(s.undi[party])} (${s.peratus_undi?.[party] ?? 0}%)`
                                                    : '—'}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                                <tr className={t.tableRow}>
                                    <td className={`${t.tableCell} font-medium`}>Pemenang</td>
                                    {senario.map((s, i) => <td key={i} className="px-3 py-2 text-sm text-right font-bold text-slate-900">{s.pemenang || '—'}</td>)}
                                </tr>
                                {metricRow('Majoriti', (s) => fmt(s.majoriti))}
                            </tbody>
                        </table>
                    </div>

                    {/* Deltas */}
                    {perubahan.length > 0 && (
                        <div className="mt-4 flex flex-wrap gap-2">
                            {perubahan.map((d, i) => {
                                const up = d.perubahan_pemilih >= 0;
                                return (
                                    <div key={i} className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                                        <span>{d.dari}</span>
                                        <ArrowRight className="h-3 w-3 text-slate-400" />
                                        <span>{d.ke}</span>
                                        <span className={`inline-flex items-center gap-1 font-semibold ${up ? 'text-emerald-600' : 'text-red-600'}`}>
                                            {up ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
                                            {signed(d.perubahan_pemilih)} pemilih
                                            {d.perubahan_pemilih_pct !== null && ` (${d.perubahan_pemilih_pct >= 0 ? '+' : ''}${d.perubahan_pemilih_pct}%)`}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}

            {/* Executive summary */}
            {res.ringkasan_eksekutif && (
                <div className="rounded-xl border border-slate-200 bg-white p-5">
                    <h4 className={`${t.text} font-semibold mb-2`}>Ringkasan Eksekutif</h4>
                    <p className="text-sm leading-relaxed text-slate-700">{res.ringkasan_eksekutif}</p>
                </div>
            )}

            {/* Three mandated metric sections */}
            <div className="grid gap-4 lg:grid-cols-3">
                <Section title="Pengundi Baru vs Lama" section={res.pengundi_baru_lama} />
                <Section title="Pengundi Muda" section={res.pengundi_muda} />
                <Section title="Pecahan Saluran" section={res.saluran} />
            </div>

            {/* Why the change */}
            {(res.faktor_perubahan || []).length > 0 && (
                <div className={t.card}>
                    <h4 className={t.cardTitle}>Faktor Perubahan — Analisis &amp; Hujah</h4>
                    <div className="space-y-4">
                        {res.faktor_perubahan.map((f, i) => (
                            <div key={i} className="border-l-4 border-emerald-500 pl-4">
                                <div className="font-semibold text-slate-900">{i + 1}. {f.tajuk}</div>
                                <p className="mt-1 text-sm leading-relaxed text-slate-600">{f.hujah}</p>
                                {f.sumber && <p className="mt-1 text-xs italic text-slate-400">Sumber: {f.sumber}</p>}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Conclusion */}
            {res.kesimpulan && (
                <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                    <h4 className="mb-2 font-semibold text-emerald-900">Kesimpulan</h4>
                    <p className="text-sm leading-relaxed text-emerald-800">{res.kesimpulan}</p>
                </div>
            )}

            {/* References */}
            {(res.rujukan || []).length > 0 && (
                <div className="rounded-xl border border-slate-200 bg-white p-5">
                    <h4 className={`${t.text} font-semibold mb-2`}>Rujukan (Carian Web)</h4>
                    <ul className="space-y-1.5">
                        {res.rujukan.map((ref, i) => (
                            <li key={i}>
                                <a href={ref.url} target="_blank" rel="noreferrer" className="inline-flex items-start gap-1.5 text-sm text-blue-600 hover:underline">
                                    <ExternalLink className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                    <span>{ref.tajuk}</span>
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {isEstimate && (
                <div className={t.banner}>
                    Nota: Pecahan saluran adalah anggaran daripada pangkalan data DPT (setiap lokaliti dikira sebagai
                    satu saluran) kerana pecahan saluran rasmi SPR belum tersedia untuk kawasan ini.
                </div>
            )}

            <p className="text-xs text-slate-400">
                Disimpan automatik · {comparison.web_search_count > 0 ? `${comparison.web_search_count} carian web · ` : ''}
                {comparison.ai_model || 'AI'}
            </p>
        </div>
    );
}
