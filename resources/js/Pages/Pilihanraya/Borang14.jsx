import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Download, Info, Landmark, MapPin, Vote, Loader2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import DragScroll from './analisa/DragScroll';

/* ------------------------------- helpers ------------------------------- */

const fmt = (n) => (n == null || Number.isNaN(n) ? '0' : Number(n).toLocaleString('en-MY'));
const pct = (num, den) => (den > 0 ? `${((num / den) * 100).toFixed(1)}%` : '—');
const cellKey = (pusat, saluran, slot) => `${pusat ?? ''}|${saluran}|${slot}`;

// Flatten reference into one block per Pusat Mengundi (a DM may have several).
function toBlocks(reference) {
    if (!reference) return [];
    return reference.daerah_mengundi.flatMap((dm) =>
        dm.pusat_mengundi.map((p) => ({
            dm: dm.nama,
            pusat: p.nama,
            berdaftar: p.jumlah_berdaftar ?? p.saluran.reduce((s, x) => s + (x.berdaftar || 0), 0),
            saluran: p.saluran,
        })),
    );
}

/* ---------------------------- editable cell ---------------------------- */

function EditableCell({ value, onCommit }) {
    const [local, setLocal] = useState(value ?? '');
    useEffect(() => { setLocal(value ?? ''); }, [value]);

    return (
        <input
            type="number"
            min="0"
            inputMode="numeric"
            value={local}
            onChange={(e) => setLocal(e.target.value)}
            onBlur={() => {
                const num = local === '' ? 0 : Math.max(0, parseInt(local, 10) || 0);
                if (num !== (value ?? 0)) onCommit(num);
            }}
            className="w-20 px-2 py-1 text-right text-sm rounded-md bg-sky-100 text-slate-900 border border-sky-300 focus:ring-2 focus:ring-sky-400 focus:outline-none"
            placeholder="0"
        />
    );
}

/* --------------------------- per-pusat table --------------------------- */

function VoteTable({ block, partyNames, votes, onSave }) {
    const { t } = usePilihanrayaTheme();
    const nParties = partyNames.length;

    const rows = block.saluran.map((s) => {
        const slots = Array.from({ length: nParties }, (_, i) =>
            votes[cellKey(block.pusat, String(s.no), i + 1)] ?? 0);
        const keluar = slots.reduce((a, b) => a + b, 0);
        return { no: s.no, berdaftar: s.berdaftar, slots, keluar };
    });

    const totals = {
        slots: Array.from({ length: nParties }, (_, i) => rows.reduce((a, r) => a + r.slots[i], 0)),
        keluar: rows.reduce((a, r) => a + r.keluar, 0),
        berdaftar: rows.reduce((a, r) => a + (r.berdaftar || 0), 0),
    };

    return (
        <div className={`${t.card} p-4`}>
            <div className="mb-3">
                <div className={`text-xs font-semibold uppercase tracking-wider ${t.subtext}`}>DM: {block.dm}</div>
                <div className={`text-sm font-bold ${t.text}`}>Pusat Mengundi: {block.pusat}</div>
            </div>
            <DragScroll>
                <table className="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th className={`${t.tableHead} whitespace-nowrap`}>Saluran</th>
                            {partyNames.map((p, i) => (
                                <th key={i} className={`${t.tableHead} whitespace-nowrap text-right`}>{p}</th>
                            ))}
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Jumlah Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Berdaftar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Turnout</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Tak Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Tak Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.no} className={t.tableRow}>
                                <td className={`${t.tableCell} font-medium whitespace-nowrap`}>Saluran {r.no}</td>
                                {r.slots.map((v, i) => (
                                    <td key={i} className="px-2 py-1 text-right">
                                        <EditableCell
                                            value={v}
                                            onCommit={(undi) => onSave(block.pusat, String(r.no), i + 1, undi)}
                                        />
                                    </td>
                                ))}
                                <td className={`${t.tableCell} text-right font-semibold`}>{fmt(r.keluar)}</td>
                                <td className={`${t.tableCell} text-right`}>{fmt(r.berdaftar)}</td>
                                <td className={`${t.tableCell} text-right`}>{pct(r.keluar, r.berdaftar)}</td>
                                <td className={`${t.tableCell} text-right`}>{fmt((r.berdaftar || 0) - r.keluar)}</td>
                                <td className={`${t.tableCell} text-right`}>{pct((r.berdaftar || 0) - r.keluar, r.berdaftar)}</td>
                            </tr>
                        ))}
                        <tr className={`border-t-2 ${t.border} font-bold`}>
                            <td className={`${t.tableCell} font-bold whitespace-nowrap`}>Jumlah Undi</td>
                            {totals.slots.map((v, i) => (
                                <td key={i} className={`${t.tableCell} text-right font-bold`}>{fmt(v)}</td>
                            ))}
                            <td className={`${t.tableCell} text-right font-bold`}>{fmt(totals.keluar)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{fmt(totals.berdaftar)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{pct(totals.keluar, totals.berdaftar)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{fmt(totals.berdaftar - totals.keluar)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{pct(totals.berdaftar - totals.keluar, totals.berdaftar)}</td>
                        </tr>
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

/* ----------------------- undi awal / undi pos -------------------------- */

const SPECIAL_ROWS = ['UNDI AWAL', 'UNDI POS'];

function UndiAwalPosTable({ partyNames, votes, onSave, berdaftarByRow }) {
    const { t } = usePilihanrayaTheme();
    const nParties = partyNames.length;

    return (
        <div className={`${t.card} p-4`}>
            <div className={`text-sm font-bold ${t.text} mb-3`}>Undi Awal & Undi Pos</div>
            <DragScroll>
                <table className="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th className={`${t.tableHead} whitespace-nowrap`}>Saluran</th>
                            {partyNames.map((p, i) => (
                                <th key={i} className={`${t.tableHead} whitespace-nowrap text-right`}>{p}</th>
                            ))}
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Jumlah Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Berdaftar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Turnout</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Tak Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Tak Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {SPECIAL_ROWS.map((label) => {
                            const slots = Array.from({ length: nParties }, (_, i) =>
                                votes[cellKey('', label, i + 1)] ?? 0);
                            const keluar = slots.reduce((a, b) => a + b, 0);
                            const berdaftar = berdaftarByRow?.[label] ?? 0; // registered voters from SPR reference
                            return (
                                <tr key={label} className={t.tableRow}>
                                    <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{label}</td>
                                    {slots.map((v, i) => (
                                        <td key={i} className="px-2 py-1 text-right">
                                            <EditableCell
                                                value={v}
                                                onCommit={(undi) => onSave('', label, i + 1, undi)}
                                            />
                                        </td>
                                    ))}
                                    <td className={`${t.tableCell} text-right font-semibold`}>{fmt(keluar)}</td>
                                    <td className={`${t.tableCell} text-right`}>{fmt(berdaftar)}</td>
                                    <td className={`${t.tableCell} text-right`}>{pct(keluar, berdaftar)}</td>
                                    <td className={`${t.tableCell} text-right`}>{fmt(Math.max(0, berdaftar - keluar))}</td>
                                    <td className={`${t.tableCell} text-right`}>{pct(Math.max(0, berdaftar - keluar), berdaftar)}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

/* ------------------------------- page ---------------------------------- */

export default function Borang14({ negeriList, parlimenList, kadunList, partiList, penjuruOptions }) {
    return (
        <AuthenticatedLayout>
            <Head title="Borang 14" />
            <PilihanrayaShell title="Borang 14" subtitle="Tally undi mengikut saluran, pusat mengundi & daerah mengundi">
                <Borang14Body
                    negeriList={negeriList}
                    parlimenList={parlimenList}
                    kadunList={kadunList}
                    partiList={partiList}
                    penjuruOptions={penjuruOptions}
                />
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}

function Borang14Body({ negeriList, parlimenList, kadunList, partiList, penjuruOptions }) {
    const { t } = usePilihanrayaTheme();

    const [negeriId, setNegeriId] = useState('');
    const [parlimenId, setParlimenId] = useState('');
    const [kadunId, setKadunId] = useState('');
    const [penjuru, setPenjuru] = useState('');
    const [parties, setParties] = useState([]); // [{slot, keahlian_parti_id, nama}]
    const [reference, setReference] = useState(null);
    const [hasData, setHasData] = useState(true);
    const [votes, setVotes] = useState({});
    const [loading, setLoading] = useState(false);

    const parlimenOptions = negeriId
        ? parlimenList.filter((p) => String(p.negeri_id) === String(negeriId))
        : [];
    const kadunOptions = parlimenId
        ? kadunList.filter((k) => String(k.bandar_id) === String(parlimenId))
        : [];

    const geographyComplete = negeriId && parlimenId && kadunId;

    // Fetch reference + saved data whenever the DUN or penjuru changes.
    useEffect(() => {
        if (!kadunId) { setReference(null); setHasData(true); setVotes({}); return; }
        let cancelled = false;
        setLoading(true);
        axios.get(route('pilihanraya.borang-14.data'), { params: { kadun_id: kadunId, penjuru: penjuru || undefined } })
            .then(({ data }) => {
                if (cancelled) return;
                setReference(data.reference);
                setHasData(data.hasData);
                setVotes(data.votes || {});
                if (data.parties && data.parties.length) setParties(data.parties);
            })
            .finally(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
    }, [kadunId, penjuru]);

    // Keep the party-slot array sized to the chosen penjuru.
    useEffect(() => {
        if (!penjuru) { setParties([]); return; }
        setParties((prev) => {
            const n = Number(penjuru);
            return Array.from({ length: n }, (_, i) => prev[i] || { slot: i + 1, keahlian_parti_id: '', nama: '' });
        });
    }, [penjuru]);

    const partyNames = useMemo(
        () => parties.map((p, i) => (p?.nama ? p.nama : `Parti ${i + 1}`)),
        [parties],
    );

    const blocks = useMemo(() => toBlocks(reference), [reference]);

    const persistParties = useCallback((next) => {
        if (!kadunId || !penjuru) return;
        axios.post(route('pilihanraya.borang-14.parties'), {
            kadun_id: kadunId, penjuru: Number(penjuru), parties: next,
        }).catch(() => {});
    }, [kadunId, penjuru]);

    const onPickParty = (index, partiId) => {
        const parti = partiList.find((p) => String(p.id) === String(partiId));
        const next = parties.map((p, i) => (i === index
            ? { slot: i + 1, keahlian_parti_id: parti ? parti.id : '', nama: parti ? parti.nama : '' }
            : p));
        setParties(next);
        persistParties(next);
    };

    const saveVote = useCallback((pusat, saluran, slot, undi) => {
        setVotes((prev) => ({ ...prev, [cellKey(pusat, saluran, slot)]: undi }));
        axios.post(route('pilihanraya.borang-14.vote'), {
            kadun_id: kadunId, penjuru: Number(penjuru), pusat, saluran, slot, undi,
        }).catch(() => {});
    }, [kadunId, penjuru]);

    const downloadPdf = () => {
        const url = route('pilihanraya.borang-14.pdf', {
            kadun_id: kadunId,
            penjuru: Number(penjuru),
            parti: partyNames, // headers follow the on-screen dropdown selection
        });
        window.open(url, '_blank');
    };

    const canShowTables = geographyComplete && hasData && penjuru && blocks.length > 0;

    return (
        <>
            {/* Filters */}
            <div className={`${t.cardTight} mb-4`}>
                {/* Row 1 — geography */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" /> Negeri</span></label>
                        <select
                            value={negeriId}
                            onChange={(e) => { setNegeriId(e.target.value); setParlimenId(''); setKadunId(''); }}
                            className={t.input}
                        >
                            <option value="">Pilih Negeri</option>
                            {negeriList.map((n) => <option key={n.id} value={n.id}>{n.nama}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><Landmark className="h-3.5 w-3.5" /> Parlimen</span></label>
                        <select
                            value={parlimenId}
                            onChange={(e) => { setParlimenId(e.target.value); setKadunId(''); }}
                            className={t.input}
                            disabled={!negeriId}
                        >
                            <option value="">Pilih Parlimen</option>
                            {parlimenOptions.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><Vote className="h-3.5 w-3.5" /> DUN</span></label>
                        <select
                            value={kadunId}
                            onChange={(e) => setKadunId(e.target.value)}
                            className={t.input}
                            disabled={!parlimenId}
                        >
                            <option value="">Pilih DUN</option>
                            {kadunOptions.map((k) => <option key={k.id} value={k.id}>{k.nama}</option>)}
                        </select>
                    </div>
                </div>

                {/* Row 2 — penjuru + party pickers (only once geography chosen & data exists) */}
                {geographyComplete && hasData && (
                    <div className="mt-3 pt-3 border-t border-dashed grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label className={t.label}>Bilangan Penjuru</label>
                            <select value={penjuru} onChange={(e) => setPenjuru(e.target.value)} className={t.input}>
                                <option value="">Pilih Penjuru</option>
                                {penjuruOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </select>
                        </div>
                        {parties.map((p, i) => (
                            <div key={i}>
                                <label className={t.label}>Parti {i + 1}</label>
                                <select
                                    value={p.keahlian_parti_id || ''}
                                    onChange={(e) => onPickParty(i, e.target.value)}
                                    className={t.input}
                                >
                                    <option value="">Pilih Parti</option>
                                    {partiList.map((pt) => <option key={pt.id} value={pt.id}>{pt.nama}</option>)}
                                </select>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Note when geography incomplete */}
            {!geographyComplete && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Pilih Negeri &gt; Parlimen &gt; DUN untuk di isi.</span>
                </div>
            )}

            {/* No reference data for chosen DUN */}
            {geographyComplete && !hasData && !loading && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Data Borang 14 (saluran & pengundi berdaftar) belum tersedia untuk DUN ini.</span>
                </div>
            )}

            {/* Prompt to pick penjuru */}
            {geographyComplete && hasData && !penjuru && !loading && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Pilih bilangan penjuru dan parti untuk memaparkan jadual.</span>
                </div>
            )}

            {loading && (
                <div className={`flex items-center gap-2 ${t.subtext} py-8 justify-center`}>
                    <Loader2 className="h-5 w-5 animate-spin" /> Memuatkan…
                </div>
            )}

            {/* Tables */}
            {canShowTables && !loading && (
                <>
                    <div className="flex items-center justify-between mb-4">
                        <div className={`text-sm ${t.subtext}`}>
                            {reference.negeri} · {reference.parlimen} · <span className={`font-semibold ${t.text}`}>DUN {reference.dun}</span>
                        </div>
                        <button type="button" onClick={downloadPdf} className={t.buttonPrimary}>
                            <Download className="h-4 w-4" /> Muat Turun PDF
                        </button>
                    </div>

                    <div className="grid grid-cols-1 gap-4">
                        {blocks.map((b, i) => (
                            <VoteTable
                                key={`${b.dm}-${b.pusat}-${i}`}
                                block={b}
                                partyNames={partyNames}
                                votes={votes}
                                onSave={saveVote}
                            />
                        ))}
                    </div>

                    <div className="mt-4">
                        <UndiAwalPosTable
                            partyNames={partyNames}
                            votes={votes}
                            onSave={saveVote}
                            berdaftarByRow={{
                                'UNDI AWAL': reference?.undi_awal?.berdaftar ?? 0,
                                'UNDI POS': reference?.undi_pos?.berdaftar ?? 0,
                            }}
                        />
                    </div>
                </>
            )}
        </>
    );
}
