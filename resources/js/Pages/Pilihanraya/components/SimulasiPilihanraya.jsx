import { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import {
    Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip,
} from 'recharts';
import { Download, Loader2, RotateCcw, Trophy } from 'lucide-react';
import { usePilihanrayaTheme } from './PilihanrayaShell';
import KpiCard from './KpiCard';
import EditableCell from './EditableCell';
import { cleanParams } from '../filters';
import { fmt, pct, safeDiv, partyColor, KAUM_LABEL } from '../analisa/shared';
import { KAUM_KEYS, simulate } from '../simulation/nCornerModel';

// Preferred default line-up so 1 lawan 1 seeds PH vs BN (the workbook), and each
// larger contest adds the next natural contender.
const DEFAULT_ORDER = ['PH', 'BN', 'PN', 'PEJUANG', 'MUDA', 'BEBAS'];

// Kalibrasi PRN 2022 (workbook SIMULASI-2026). Lain-lain is a sensible seed —
// the workbook has no Lain-lain row.
// Kiraan pengundi ini hanya nilai permulaan — ia digantikan oleh autoisi DPPR
// sebaik sahaja satu kerusi dipilih (lihat useEffect di bawah).
const DEFAULT_PENGUNDI = { melayu: 16668, cina: 9443, india: 2862, lain: 0 };

// Turnout dan sokongan ialah ANDAIAN, bukan fakta. Turnout diselaraskan kepada
// keluar-mengundi SEBENAR kerusi terpilih apabila garis dasar rasmi telah
// disegerakkan; peratus sokongan tiada sumber rasmi dan kekal andaian.
const DEFAULT_ANDAIAN = {
    melayu: { turnout: 0.674, sokongan: { PH: 0.235 } },
    cina: { turnout: 0.749, sokongan: { PH: 0.830 } },
    india: { turnout: 0.749, sokongan: { PH: 0.800 } },
    lain: { turnout: 0.700, sokongan: { PH: 0.500 } },
};

const clone = (o) => JSON.parse(JSON.stringify(o));

const RAD = Math.PI / 180;

// Draws the party code + vote figure + % just outside each pie slice. Zero
// slices (parti with no votes) get no label so the chart stays clean.
function renderPieLabel({ cx, cy, midAngle, outerRadius, percent, value, payload }) {
    if (!percent || !value) return null;
    const r = outerRadius + 24;
    const x = cx + r * Math.cos(-midAngle * RAD);
    const y = cy + r * Math.sin(-midAngle * RAD);
    return (
        <text x={x} y={y} textAnchor={x >= cx ? 'start' : 'end'} dominantBaseline="central" fontSize={12} fontWeight={600} fill="#0f172a">
            <tspan fill={payload.color} fontWeight={700}>{payload.kod}</tspan>
            {` ${fmt(value)} (${(percent * 100).toFixed(1)}%)`}
        </text>
    );
}

function defaultSlots(n, parties) {
    const known = new Set(parties.map((p) => p.kod));
    const order = [...DEFAULT_ORDER.filter((k) => known.has(k)), ...parties.map((p) => p.kod)];
    const seen = [];
    for (const k of order) {
        if (!seen.includes(k)) seen.push(k);
        if (seen.length === n) break;
    }
    return seen;
}

export default function SimulasiPilihanraya({ filters, simulasiParties = [], penjuruOptions = [] }) {
    const { t } = usePilihanrayaTheme();

    const partyByKod = useMemo(
        () => Object.fromEntries(simulasiParties.map((p) => [p.kod, p])),
        [simulasiParties]
    );
    const namaOf = (kod) => partyByKod[kod]?.nama || kod;

    const [penjuru, setPenjuru] = useState(2);
    const [slots, setSlots] = useState(() => defaultSlots(2, simulasiParties));
    const [pengundi, setPengundi] = useState(clone(DEFAULT_PENGUNDI));
    const [andaian, setAndaian] = useState(clone(DEFAULT_ANDAIAN));
    const [source, setSource] = useState(null);
    const [pdfLoading, setPdfLoading] = useState(false);

    // Parties in contest order (resolved objects).
    const parties = useMemo(
        () => slots.map((kod) => partyByKod[kod] || { kod, nama: kod }),
        [slots, partyByKod]
    );
    const lastParty = parties[parties.length - 1];

    /* ---- DPPR autofill: pull latest roll when Parlimen/KADUN changes ---- */
    const lastFetchKey = useRef(null);
    useEffect(() => {
        if (!filters?.parlimen_id) return; // keep manual/default values until a seat is picked
        const key = `${filters.parlimen_id}|${filters.kadun_id || ''}`;
        if (lastFetchKey.current === key) return;
        lastFetchKey.current = key;

        let cancelled = false;
        axios.get(route('pilihanraya.api.simulasi.pengundi'), { params: cleanParams(filters) })
            .then(({ data }) => {
                if (cancelled) return;
                setPengundi({
                    melayu: data.melayu || 0,
                    cina: data.cina || 0,
                    india: data.india || 0,
                    lain: data.lain || 0,
                });
                setSource(data.source);
            })
            .catch(() => !cancelled && setSource(null));

        return () => { cancelled = true; };
    }, [filters?.parlimen_id, filters?.kadun_id]); // eslint-disable-line react-hooks/exhaustive-deps

    /* ---- Kalibrasi turnout daripada keputusan rasmi kerusi terpilih ---- */
    const [garisDasar, setGarisDasar] = useState(null);
    useEffect(() => {
        if (!filters?.parlimen_id && !filters?.kadun_id) return undefined;

        let cancelled = false;
        axios.get(route('pilihanraya.analisa.seat-baseline'), {
            params: filters.kadun_id ? { kadun_id: filters.kadun_id } : { bandar_id: filters.parlimen_id },
        })
            .then(({ data }) => {
                if (cancelled) return;
                const b = data.baseline;
                setGarisDasar(b?.tersedia ? b : null);

                // HANYA apabila peratus sebenar diketahui. null bermakna tidak
                // diketahui — mengekalkan andaian generik adalah lebih jujur
                // daripada menganggap 0% keluar mengundi.
                const perc = b?.keluar_mengundi_perc;
                if (b?.tersedia && typeof perc === 'number' && perc > 0) {
                    const t = Math.min(1, perc / 100);
                    setAndaian((a) => Object.fromEntries(
                        Object.entries(a).map(([kaum, v]) => [kaum, { ...v, turnout: t }]),
                    ));
                }
            })
            .catch(() => !cancelled && setGarisDasar(null));

        return () => { cancelled = true; };
    }, [filters?.parlimen_id, filters?.kadun_id]); // eslint-disable-line react-hooks/exhaustive-deps

    /* ------------------------------ mutations ------------------------------ */
    const changePenjuru = (n) => {
        setPenjuru(n);
        setSlots((prev) => {
            const next = prev.slice(0, n);
            const used = new Set(next);
            const pool = [...DEFAULT_ORDER, ...simulasiParties.map((p) => p.kod)];
            for (const k of pool) {
                if (next.length >= n) break;
                if (!used.has(k) && partyByKod[k]) { next.push(k); used.add(k); }
            }
            return next;
        });
    };

    const setSlot = (idx, kod) => setSlots((prev) => {
        const next = [...prev];
        // If the kod is already used elsewhere, swap the two slots.
        const dup = next.indexOf(kod);
        if (dup !== -1 && dup !== idx) next[dup] = next[idx];
        next[idx] = kod;
        return next;
    });

    const setPengundiVal = (k, v) => setPengundi((p) => ({ ...p, [k]: v }));
    const setTurnout = (k, v) => setAndaian((a) => ({ ...a, [k]: { ...a[k], turnout: v } }));
    const setSokongan = (k, kod, v) => setAndaian((a) => ({
        ...a, [k]: { ...a[k], sokongan: { ...a[k].sokongan, [kod]: v } },
    }));

    const reset = () => {
        setPengundi(clone(DEFAULT_PENGUNDI));
        setAndaian(clone(DEFAULT_ANDAIAN));
        lastFetchKey.current = null;
        setSource(null);
    };

    /* ------------------------------ compute ------------------------------ */
    const modelAndaian = useMemo(() => {
        const out = {};
        for (const k of KAUM_KEYS) {
            const row = andaian[k] || { turnout: 0, sokongan: {} };
            out[k] = {
                turnout: row.turnout,
                sokongan: slots.slice(0, slots.length - 1).map((kod) => row.sokongan?.[kod] ?? 0),
            };
        }
        return out;
    }, [andaian, slots]);

    const result = useMemo(() => simulate(pengundi, modelAndaian, parties), [pengundi, modelAndaian, parties]);

    const title = `SIMULASI PRN KE-16 (2026) — PERTANDINGAN ${
        penjuruOptions.find((o) => Number(o.value) === penjuru)?.label || `${penjuru} Penjuru`
    } (${parties.map((p) => p.kod).join(' vs ')})`;

    // Overall vote-share pie: total votes per contesting party.
    const pieData = parties.map((p, i) => ({
        kod: p.kod,
        nama: p.nama,
        value: Math.round(result.undiTotals[i]),
        color: partyColor(p.kod, i),
    }));

    const winnerColor = result.winner ? partyColor(result.winner.kod, result.winner.index) : '#334155';
    const cellNum = 'px-3 py-2 text-sm text-right tabular-nums';

    /* -------------------------------- PDF -------------------------------- */
    const downloadPdf = async () => {
        setPdfLoading(true);
        try {
            const payload = {
                title,
                penjuru,
                penjuru_label: penjuruOptions.find((o) => Number(o.value) === penjuru)?.label || `${penjuru} Penjuru`,
                kawasan: source || 'Data manual',
                parties: parties.map((p, i) => ({ kod: p.kod, nama: p.nama, color: partyColor(p.kod, i) })),
                pengundi,
                andaian: KAUM_KEYS.map((k) => ({
                    kaum: KAUM_LABEL[k],
                    turnout: andaian[k].turnout,
                    sokongan: slots.slice(0, slots.length - 1).map((kod) => andaian[k].sokongan?.[kod] ?? 0),
                    baki_kod: lastParty?.kod,
                })),
                keputusan: result.perKaum.map((r) => ({
                    kaum: KAUM_LABEL[r.key],
                    pengundi: r.voters,
                    keluar: r.keluar,
                    undi: r.undi,
                })),
                totals: {
                    undi: result.undiTotals,
                    keluar: result.keluar,
                    pengundi: result.pengundiTotal,
                    perlu: result.perlu,
                    turnout_all: result.turnoutAll,
                    majoriti: result.majoriti,
                    status: result.status,
                    winner: result.winner ? { kod: result.winner.kod, nama: namaOf(result.winner.kod) } : null,
                },
            };
            const res = await axios.post(route('pilihanraya.simulasi.pdf'), payload, { responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
            const a = document.createElement('a');
            a.href = url;
            a.download = `simulasi-pilihanraya-${new Date().toISOString().slice(0, 10)}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } catch {
            // swallow — the button is retryable
        } finally {
            setPdfLoading(false);
        }
    };

    const explicitSlots = slots.slice(0, slots.length - 1);

    return (
        <div className="space-y-6">
            {/* Winner banner */}
            <div className="border rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-4"
                style={{ backgroundColor: `${winnerColor}14`, borderColor: `${winnerColor}66` }}>
                <div className="flex items-center gap-3">
                    <Trophy className="h-6 w-6" style={{ color: winnerColor }} />
                    <div>
                        <p className="text-lg font-bold" style={{ color: winnerColor }}>
                            {result.status}
                        </p>
                        <p className={`${t.subtext} text-xs`}>
                            {parties.map((p) => p.nama).join(' vs ')} — kalibrasi PRN 2022
                        </p>
                    </div>
                </div>
                <div className="text-right">
                    <p className={`${t.subtext} text-xs`}>Majoriti</p>
                    <p className="text-xl font-bold tabular-nums" style={{ color: winnerColor }}>
                        {result.majoriti >= 0 ? '+' : ''}{fmt(result.majoriti)}
                    </p>
                </div>
            </div>

            {/* Contest controls */}
            <div className={t.card}>
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h3 className={`${t.cardTitle} mb-0`}>{title}</h3>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={reset} className={t.buttonSecondary}>
                            <RotateCcw className="h-4 w-4" /> Set Semula
                        </button>
                        <button type="button" onClick={downloadPdf} disabled={pdfLoading} className={t.buttonPrimary}>
                            {pdfLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
                            Muat Turun PDF
                        </button>
                    </div>
                </div>
                <p className={`${t.subtext} text-sm mb-4`}>
                    Dikalibrasi kpd 2022. Sel <span className="font-semibold text-sky-600">biru</span> = input —
                    ubah bilangan pengundi, turnout dan sokongan setiap parti. Baki peratus (parti terakhir) dikira automatik.
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label className={t.label}>Bilangan Penjuru</label>
                        <select value={penjuru} onChange={(e) => changePenjuru(Number(e.target.value))} className={t.input}>
                            {penjuruOptions.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                    </div>
                    {slots.map((kod, i) => (
                        <div key={i}>
                            <label className={t.label}>
                                Parti {i + 1}{i === slots.length - 1 ? ' (baki)' : ''}
                            </label>
                            <select value={kod} onChange={(e) => setSlot(i, e.target.value)} className={t.input}>
                                {simulasiParties.map((p) => (
                                    <option key={p.kod} value={p.kod}>{p.nama}</option>
                                ))}
                            </select>
                        </div>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                {/* PENGUNDI */}
                <div className={t.card}>
                    <div className="flex items-center justify-between mb-1">
                        <h3 className={`${t.cardTitle} mb-0`}>Pengundi</h3>
                        {source && <span className={`${t.badge} bg-emerald-500/15 text-emerald-600`}>{source}</span>}
                    </div>
                    <p className={`${t.subtext} text-xs mb-4`}>
                        {filters?.parlimen_id ? 'Diisi automatik dari DPPR terkini — boleh diubah.' : 'Pilih Parlimen di atas untuk isi automatik dari DPPR, atau masukkan manual.'}
                    </p>
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead>
                                <tr>
                                    <th className={t.tableHead}>Kaum</th>
                                    <th className={`${t.tableHead} text-right`}>Bilangan Pengundi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {KAUM_KEYS.map((k) => (
                                    <tr key={k} className={t.tableRow}>
                                        <td className={`${t.tableCell} font-medium`}>{KAUM_LABEL[k]}</td>
                                        <td className={`${cellNum}`}>
                                            <EditableCell value={pengundi[k]} onCommit={(v) => setPengundiVal(k, v)} mode="int" />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className={`border-t-2 ${t.border} font-bold ${t.text}`}>
                                    <td className="px-3 py-3">JUMLAH</td>
                                    <td className={cellNum}>{fmt(result.pengundiTotal)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {/* ANDAIAN SENARIO */}
                <div className={t.card}>
                    <h3 className={`${t.cardTitle} mb-1`}>Andaian Senario</h3>
                    <p className={`${t.subtext} text-xs mb-4`}>
                        Baki % = {lastParty?.kod}. Sokongan parti terakhir dikira automatik dari baki turnout.
                        {garisDasar && (
                            <>
                                {' '}Turnout dikalibrasi kepada keluar mengundi sebenar kerusi ini
                                ({garisDasar.pilihanraya}: {garisDasar.keluar_mengundi_perc?.toFixed(2)}%).
                                Peratus sokongan kekal andaian — tiada sumber rasmi menyediakannya.
                            </>
                        )}
                    </p>
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead>
                                <tr>
                                    <th className={t.tableHead}>Kaum</th>
                                    <th className={`${t.tableHead} text-right`}>% Turnout</th>
                                    {explicitSlots.map((kod) => (
                                        <th key={kod} className={`${t.tableHead} text-right`}>% Sokongan {kod}</th>
                                    ))}
                                    <th className={`${t.tableHead} text-right`}>{lastParty?.kod} (baki)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {KAUM_KEYS.map((k, ki) => {
                                    const kaumRes = result.perKaum[ki];
                                    return (
                                        <tr key={k} className={`${t.tableRow} ${kaumRes?.overflow ? 'bg-rose-50' : 'bg-amber-50/40'}`}>
                                            <td className={`${t.tableCell} font-medium`}>{KAUM_LABEL[k]}</td>
                                            <td className={cellNum}>
                                                <EditableCell value={andaian[k].turnout} onCommit={(v) => setTurnout(k, v)} mode="percent" />
                                            </td>
                                            {explicitSlots.map((kod) => (
                                                <td key={kod} className={cellNum}>
                                                    <EditableCell
                                                        value={andaian[k].sokongan?.[kod] ?? 0}
                                                        onCommit={(v) => setSokongan(k, kod, v)}
                                                        mode="percent"
                                                        invalid={kaumRes?.overflow}
                                                    />
                                                </td>
                                            ))}
                                            <td className={`${cellNum} ${t.subtext} font-semibold`}>
                                                {pct(kaumRes?.shares[kaumRes.shares.length - 1] ?? 0)}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {result.hasOverflow && (
                        <p className="text-rose-600 text-xs mt-3">
                            Amaran: jumlah sokongan melebihi 100% pada baris bertanda merah — nilai dilaraskan supaya baki tidak negatif.
                        </p>
                    )}
                    <p className={`${t.subtext} text-xs mt-3`}>
                        Nota: dalam pertandingan 1 lawan 1, pengundi PN 2022 (majoriti Melayu) perlu memilih BN, PH, atau
                        tidak keluar — laraskan turnout / sokongan Melayu untuk menguji senario.
                    </p>
                </div>
            </div>

            {/* Summary KPIs */}
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <KpiCard label="Undi Keluar" value={fmt(result.keluar)} sub={`${pct(result.turnoutAll)} turnout keseluruhan`} />
                <KpiCard label="Undi Diperlukan (50%+1)" value={fmt(result.perlu)} sub={`Jumlah pengundi ${fmt(result.pengundiTotal)}`} />
                <KpiCard label={`Undi ${result.winner?.kod || '—'}`} value={fmt(result.undiTotals[result.winner?.index ?? 0])} sub="Parti mendahului" />
                <KpiCard label="Majoriti" value={`${result.majoriti >= 0 ? '+' : ''}${fmt(result.majoriti)}`} sub={result.status} />
            </div>

            {/* KEPUTUSAN SIMULASI */}
            <div className={t.card}>
                <h3 className={t.cardTitle}>Keputusan Simulasi</h3>
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead>
                            <tr>
                                <th className={t.tableHead}>Kaum</th>
                                <th className={`${t.tableHead} text-right`}>Undi Keluar</th>
                                {parties.map((p, i) => (
                                    <th key={p.kod} className={`${t.tableHead} text-right`} style={{ color: partyColor(p.kod, i) }}>
                                        Undi {p.kod}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {result.perKaum.map((r) => (
                                <tr key={r.key} className={t.tableRow}>
                                    <td className={`${t.tableCell} font-medium`}>{KAUM_LABEL[r.key]}</td>
                                    <td className={`${cellNum} ${t.text}`}>{fmt(r.keluar)}</td>
                                    {parties.map((p, i) => (
                                        <td key={p.kod} className={cellNum} style={{ color: partyColor(p.kod, i) }}>{fmt(r.undi[i])}</td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className={`border-t-2 ${t.border} font-bold ${t.text}`}>
                                <td className="px-3 py-3">JUMLAH</td>
                                <td className={cellNum}>{fmt(result.keluar)}</td>
                                {parties.map((p, i) => (
                                    <td key={p.kod} className={cellNum} style={{ color: partyColor(p.kod, i) }}>{fmt(result.undiTotals[i])}</td>
                                ))}
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {/* Vote-share pie */}
            <div className={t.card}>
                <h3 className={t.cardTitle}>Perkongsian Undi Mengikut Parti</h3>
                <ResponsiveContainer width="100%" height={380}>
                    <PieChart>
                        <Pie
                            data={pieData}
                            dataKey="value"
                            nameKey="kod"
                            cx="50%"
                            cy="50%"
                            outerRadius={120}
                            labelLine={{ stroke: '#cbd5e1', strokeWidth: 1 }}
                            label={renderPieLabel}
                            isAnimationActive={false}
                        >
                            {pieData.map((d) => (
                                <Cell key={d.kod} fill={d.color} stroke="#ffffff" strokeWidth={2} />
                            ))}
                        </Pie>
                        <Tooltip
                            contentStyle={t.tooltip}
                            formatter={(v, _n, item) => [`${fmt(v)} undi (${pct(safeDiv(v, result.keluar))})`, item?.payload?.nama || item?.payload?.kod]}
                        />
                        <Legend wrapperStyle={{ fontSize: '12px' }} formatter={(value) => partyByKod[value]?.nama || value} />
                    </PieChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
